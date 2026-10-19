import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const base = new URL(process.env.CHEF_HTTP_BASE);
const php = process.env.CHEF_PHP_EXE;
const configFile = process.env.CHEF_CONFIG_FILE;
const fixtureRoot = process.env.CHEF_MEDIA_FIXTURES;
const repo = path.resolve(import.meta.dirname, '..', '..');
const credentials = JSON.parse(fs.readFileSync(process.env.CHEF_TEST_CREDENTIALS, 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function control(command) {
  const result = spawnSync(php, [path.join(repo, 'database/tests/media-db-control.php'), command], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: configFile },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`Control ${command} failed.`);
  return result.stdout.trim();
}

async function request(route, options = {}) {
  const headers = new Headers(options.headers ?? {});
  if (options.cookie) headers.set('Cookie', options.cookie);
  const response = await fetch(new URL(route, base), {
    method: options.method ?? 'GET',
    headers,
    body: options.body,
    redirect: 'manual',
  });
  const body = options.binary ? Buffer.from(await response.arrayBuffer()) : await response.text();
  return { response, body };
}

function cookie(response) {
  return (response.headers.get('set-cookie') ?? '').split(';')[0];
}

function csrf(body) {
  const match = body.match(/name="csrf_token" value="([a-f0-9]+)"/);
  assert(match, 'CSRF token was not present.');
  return match[1];
}

function submission(body) {
  const match = body.match(/name="submission_token" value="([a-f0-9]+)"/);
  assert(match, 'Submission token was not present.');
  return match[1];
}

async function login() {
  const page = await request('/hvk/admin/login.php');
  const loginCookie = cookie(page.response);
  const form = new URLSearchParams({
    csrf_token: csrf(page.body), submission_token: submission(page.body),
    username: credentials.admin_username, password: credentials.admin_password,
  });
  const result = await request('/hvk/admin/login.php', {
    method: 'POST', cookie: loginCookie,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form,
  });
  assert(result.response.status === 302, 'Administrator login failed.');
  return cookie(result.response) || loginCookie;
}

async function pageToken(route, sessionCookie) {
  const page = await request(route, { cookie: sessionCookie });
  assert(page.response.status === 200, `Protected page failed: ${route}`);
  return { csrf: csrf(page.body), submission: submission(page.body) };
}

async function campaignUpload(sessionCookie, filename, submittedName = filename, extraBytes = 0) {
  const token = await pageToken('/hvk/admin/campaign.php', sessionCookie);
  let data = fs.readFileSync(path.join(fixtureRoot, filename));
  if (extraBytes > 0) data = Buffer.concat([data, Buffer.alloc(extraBytes, 65)]);
  const form = new FormData();
  form.set('csrf_token', token.csrf);
  form.set('submission_token', token.submission);
  form.set('action', 'save');
  form.set('is_active', '1');
  form.set('image', new Blob([data]), submittedName);
  return request('/hvk/admin/campaign.php', { method: 'POST', cookie: sessionCookie, body: form });
}

function campaignState() {
  return JSON.parse(control('campaign-json'));
}

let response = await request('/database/media-cli.php');
assert(response.response.status === 404, 'media-cli.php was accessible through HTTP.');
response = await request('/hvk/admin/private-image.php?id=1');
assert([302, 303].includes(response.response.status), 'Private media allowed anonymous access.');

const sessionCookie = await login();
let previousReference = null;
for (const [file, name] of [['valid.jpg', 'valid.jpg'], ['valid.png', 'valid.png'], ['valid.webp', 'valid.webp'], ['menu.php.jpg', 'menu.php.jpg']]) {
  const result = await campaignUpload(sessionCookie, file, name);
  assert(result.response.status === 302, `Valid upload failed: ${file}`);
  const state = campaignState();
  assert(state.owner?.image_path?.startsWith('/media/campaigns/'), `Upload did not use controlled public storage: ${file}`);
  assert(state.owner.image_path.endsWith('.webp'), `Upload did not use WebP output: ${file}`);
  assert(state.owner.image_path !== previousReference, `Replacement did not change the media reference: ${file}`);
  const active = state.uploads.filter(row => row.state === 'active');
  assert(active.length === 2 && active.every(row => row.storage_path.endsWith('.webp')), `Main and thumbnail were not active: ${file}`);
  const main = active.find(row => row.file_role === 'main');
  const thumbnail = active.find(row => row.file_role === 'thumbnail');
  assert(Number(main.width) === 640 && Number(main.height) === 360, `Main image was upscaled or distorted: ${file}`);
  assert(Number(thumbnail.width) === 320 && Number(thumbnail.height) === 180, `Thumbnail aspect ratio is invalid: ${file}`);
  previousReference = state.owner.image_path;
}

for (const file of ['forged.jpg', 'malformed.png', 'huge.png', 'animated.png', 'animated.webp', 'oversized.jpg']) {
  const before = campaignState().owner.image_path;
  const result = await campaignUpload(sessionCookie, file);
  assert(result.response.status === 302, `Invalid upload lacked a controlled response: ${file}`);
  assert(campaignState().owner.image_path === before, `Invalid upload replaced the active image: ${file}`);
}

const requestLimitBefore = campaignState().owner.image_path;
const requestLimit = await campaignUpload(sessionCookie, 'valid.jpg', 'too-large.jpg', 5 * 1024 * 1024);
assert(requestLimit.response.status === 302, 'Oversized multipart request lacked a controlled response.');
assert(campaignState().owner.image_path === requestLimitBefore, 'Oversized multipart request changed the owner.');
control('expire-incomplete');
control('cleanup');
const recoveredUploads = JSON.parse(control('all-json'));
assert(recoveredUploads.filter(row => ['upload_staged', 'encoded', 'activation_pending', 'replacement_pending'].includes(row.state)).length === 0, 'Interrupted upload records were not recovered.');

let form = new FormData();
form.set('topic', 'service');
form.set('rating', '5');
form.set('message', 'Database rollback test');
form.set('language', 'tr');
form.set('branch_id', '999999');
form.set('image', new Blob([fs.readFileSync(path.join(fixtureRoot, 'valid.jpg'))]), 'rollback.jpg');
response = await request('/hvk/admin/api/feedback.php', { method: 'POST', body: form });
assert(response.response.status === 500, 'Database failure after upload did not fail safely.');

form = new FormData();
form.set('topic', 'taste');
form.set('rating', '5');
form.set('message', 'Private image test');
form.set('language', 'tr');
form.set('image', new Blob([fs.readFileSync(path.join(fixtureRoot, 'valid.png'))]), 'feedback.png');
response = await request('/hvk/admin/api/feedback.php', { method: 'POST', body: form });
assert(response.response.status === 201, 'Valid feedback upload failed.');
const feedback = JSON.parse(control('feedback-json'));
assert(feedback.owner?.image_path?.startsWith('managed:'), 'Feedback image did not use a private server record.');
const uploadId = Number(feedback.owner.image_path.slice(8));
response = await request(`/hvk/admin/private-image.php?id=${uploadId}`);
assert([302, 303].includes(response.response.status), 'Anonymous private image request was accepted.');
response = await request(`/hvk/admin/private-image.php?id=${uploadId}`, { cookie: sessionCookie, binary: true });
assert(response.response.status === 200, 'Authorized private image request failed.');
assert(response.response.headers.get('content-type') === 'image/webp', 'Private image MIME is invalid.');
assert(response.response.headers.get('x-content-type-options') === 'nosniff', 'Private image nosniff header is missing.');
assert((response.response.headers.get('cache-control') ?? '').includes('no-store'), 'Private image was cacheable.');

let token = await pageToken('/hvk/admin/feedbacks.php', sessionCookie);
let encoded = new URLSearchParams({ csrf_token: token.csrf, submission_token: token.submission, id: String(feedback.owner.id) });
response = await request('/hvk/admin/feedbacks.php', {
  method: 'POST', cookie: sessionCookie,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: encoded,
});
assert(response.response.status === 303, 'Feedback deletion failed.');
const allAfterFeedbackDelete = JSON.parse(control('all-json')).filter(row => row.owner_type === 'feedback' && Number(row.owner_id) === Number(feedback.owner.id));
assert(allAfterFeedbackDelete.length > 0 && allAfterFeedbackDelete.every(row => row.state === 'deleted'), 'Feedback media deletion is incomplete.');
assert(allAfterFeedbackDelete.every(row => Object.values(row.files).every(exists => exists === false)), 'Feedback media still exists on disk.');

token = await pageToken('/hvk/admin/campaign.php', sessionCookie);
encoded = new URLSearchParams({ csrf_token: token.csrf, submission_token: token.submission, action: 'delete_image' });
response = await request('/hvk/admin/campaign.php', {
  method: 'POST', cookie: sessionCookie,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: encoded,
});
assert(response.response.status === 302, 'Campaign image deletion failed.');
const campaignDeleted = campaignState();
assert(campaignDeleted.owner.image_path === null, 'Campaign image reference remains after deletion.');
assert(campaignDeleted.uploads.every(row => row.state === 'deleted'), 'Campaign media deletion is incomplete.');
assert(campaignDeleted.uploads.every(row => Object.values(row.files).every(exists => exists === false)), 'Campaign media still exists on disk.');

console.log(JSON.stringify({ status: 'PASS', validFormats: 4, rejectedFiles: 7, privateUploadId: uploadId }, null, 2));
