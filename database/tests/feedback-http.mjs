import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const base = new URL(process.env.CHEF_HTTP_BASE ?? 'http://127.0.0.1:38084');
const php = process.env.CHEF_PHP_EXE || (fs.existsSync('C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe') ? 'C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe' : 'php');
const configFile = process.env.CHEF_CONFIG_FILE;
const fixtureRoot = process.env.CHEF_MEDIA_FIXTURES;
const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const credentials = JSON.parse(fs.readFileSync(process.env.CHEF_TEST_CREDENTIALS, 'utf8'));

function control(command) {
  const result = spawnSync(php, [path.join(repo, 'database/tests/media-db-control.php'), command], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: configFile },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`Control ${command} failed: ${result.stderr || result.stdout}`);
  return result.stdout.trim();
}

function sql(query) {
  const script = `
    $cfg = require getenv('CHEF_CONFIG_FILE');
    $db = $cfg['database'];
    $pdo = new PDO($db['dsn'], $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query(${JSON.stringify(query)});
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  `;
  const result = spawnSync(php, ['-r', script], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: configFile },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`SQL query failed: ${result.stderr || result.stdout}`);
  return JSON.parse(result.stdout.trim());
}

function sqlExec(statement) {
  const script = `
    $cfg = require getenv('CHEF_CONFIG_FILE');
    $db = $cfg['database'];
    $pdo = new PDO($db['dsn'], $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(${JSON.stringify(statement)});
  `;
  const result = spawnSync(php, ['-r', script], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: configFile },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`SQL exec failed: ${result.stderr || result.stdout}`);
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
    csrf_token: csrf(page.body),
    submission_token: submission(page.body),
    username: credentials.admin_username,
    password: credentials.admin_password,
  });
  const result = await request('/hvk/admin/login.php', {
    method: 'POST',
    cookie: loginCookie,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form,
  });
  assert(result.response.status === 302, 'Login redirect was not returned.');
  return cookie(result.response);
}

// Reset any feedback throttles from previous runs
sqlExec("DELETE FROM admin_login_throttles WHERE signal_type = 'feedback'");

// ── 1. HTTP Methods and Content-Type Checks ──
let res = await request('/hvk/admin/api/feedback.php', { method: 'OPTIONS' });
assert.equal(res.response.status, 200, 'OPTIONS did not return 200.');
assert(res.response.headers.get('access-control-allow-methods')?.includes('POST'), 'OPTIONS Allow header missing.');

res = await request('/hvk/admin/api/feedback.php', { method: 'GET' });
assert.equal(res.response.status, 405, 'GET did not return 405.');
assert(res.response.headers.get('allow')?.includes('POST'), 'GET 405 Allow header missing.');

res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'text/plain' },
  body: 'plain text payload',
});
assert.equal(res.response.status, 415, 'text/plain did not return 415.');

// ── 2. Origin Control Check ──
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Origin': 'https://malicious-site.example.com',
  },
  body: JSON.stringify({ topic: 'taste', rating: 5, message: 'Malicious origin test' }),
});
assert.equal(res.response.status, 403, 'Untrusted remote origin was not rejected with 403.');

// ── 3. Field & Scalar Type Validations ──
// Empty message
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 5, message: '' }),
});
assert.equal(res.response.status, 422, 'Empty message was not rejected.');

// Array rating
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: [5], message: 'Valid message' }),
});
assert.equal(res.response.status, 422, 'Array rating was not rejected.');

// Out of range rating (0 and 6)
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 0, message: 'Valid message' }),
});
assert.equal(res.response.status, 422, 'Rating 0 was not rejected.');

res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 6, message: 'Valid message' }),
});
assert.equal(res.response.status, 422, 'Rating 6 was not rejected.');

// Non-integer rating (3.5)
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 3.5, message: 'Valid message' }),
});
assert.equal(res.response.status, 422, 'Float rating 3.5 was not rejected.');

// Invalid topic
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'accounting', rating: 5, message: 'Valid message' }),
});
assert.equal(res.response.status, 422, 'Invalid topic was not rejected.');

// Message too long (> 2000 chars)
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 5, message: 'a'.repeat(2001) }),
});
assert.equal(res.response.status, 422, 'Overlong message (>2000) was not rejected.');

// Contact too long (> 150 chars per contract)
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 5, message: 'Good food', contact: 'a'.repeat(151) }),
});
assert.equal(res.response.status, 422, 'Overlong contact (>150) was not rejected.');

// Invalid language
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ topic: 'taste', rating: 5, message: 'Good food', language: 'de' }),
});
assert.equal(res.response.status, 422, 'Invalid language "de" was not rejected.');

// ── 4. Idempotency Key Behavior ──
const idempKey = 'fb-test-key-' + Date.now();
const idempPayload = {
  topic: 'service',
  rating: 4,
  message: 'First submission with idempotency key',
  contact: 'guest@example.com',
  language: 'tr',
};

// First submission -> 201 Created
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Idempotency-Key': idempKey,
  },
  body: JSON.stringify(idempPayload),
});
assert.equal(res.response.status, 201, 'First idempotent submission did not return 201.');
const createdData = JSON.parse(res.body);
assert(createdData.id, 'Created feedback ID missing.');
assert.equal(createdData.duplicate, false, 'First submission duplicate must be false.');
const feedbackId = createdData.id;

// Second identical submission -> 200 OK with duplicate: true and same ID
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Idempotency-Key': idempKey,
  },
  body: JSON.stringify(idempPayload),
});
assert.equal(res.response.status, 200, 'Duplicate idempotent submission did not return 200.');
const dupeData = JSON.parse(res.body);
assert.equal(dupeData.id, feedbackId, 'Duplicate submission did not return same ID.');
assert.equal(dupeData.duplicate, true, 'Duplicate submission duplicate flag must be true.');

// Third submission with same key but DIFFERENT message -> 409 Conflict
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Idempotency-Key': idempKey,
  },
  body: JSON.stringify({ ...idempPayload, message: 'Altered message with same key' }),
});
assert.equal(res.response.status, 409, 'Idempotency conflict did not return 409.');

// ── 5. Physical Media Lifecycle & Filesystem Verification ──
const imageFixture = path.join(fixtureRoot, 'valid.png');
assert(fs.existsSync(imageFixture), 'valid.png fixture does not exist.');

const form = new FormData();
form.set('topic', 'hygiene');
form.set('rating', '5');
form.set('message', 'Clean restaurant, image attached test');
form.set('language', 'tr');
form.set('image', new Blob([fs.readFileSync(imageFixture)]), 'hygiene.png');

res = await request('/hvk/admin/api/feedback.php', { method: 'POST', body: form });
assert.equal(res.response.status, 201, 'Multipart feedback with image failed.');

const mediaFeedback = JSON.parse(control('feedback-json'));
assert(mediaFeedback.owner, 'Feedback record was not created.');
assert(mediaFeedback.owner.image_path?.startsWith('managed:'), 'Feedback image reference does not use managed: prefix.');
const uploadId = Number(mediaFeedback.owner.image_path.slice(8));
assert(mediaFeedback.uploads.length >= 1, 'Expected managed uploads for feedback.');
const mainUpload = mediaFeedback.uploads.find(u => u.file_role === 'main');
const thumbUpload = mediaFeedback.uploads.find(u => u.file_role === 'thumbnail');
assert(mainUpload, 'Main upload missing.');
assert.equal(mainUpload.storage_scope, 'private', 'Feedback upload storage scope is not private.');
if (thumbUpload) {
  assert.equal(thumbUpload.storage_scope, 'private', 'Thumbnail upload storage scope is not private.');
}

// Verify physical files exist on disk for all active roles (main and thumbnail)
const activeUploads = mediaFeedback.uploads.filter(u => u.state === 'active');
assert(activeUploads.length >= 1, 'No active uploads found for feedback.');
for (const upload of activeUploads) {
  assert(Object.values(upload.files).length > 0, `No physical files tracked for ${upload.file_role}.`);
  assert(Object.values(upload.files).every(exists => exists === true), `Private ${upload.file_role} file does not physically exist on disk.`);
}

// Anonymous access to private image -> redirected to login
res = await request(`/hvk/admin/private-image.php?id=${uploadId}`);
assert([302, 303].includes(res.response.status), 'Anonymous private image access was not redirected to login.');

// Authenticated access with admin session
const sessionCookie = await login();
res = await request(`/hvk/admin/private-image.php?id=${uploadId}`, { cookie: sessionCookie, binary: true });
assert.equal(res.response.status, 200, 'Authenticated private image access failed.');
assert.equal(res.response.headers.get('content-type'), 'image/webp', 'Private image Content-Type is not image/webp.');
assert.equal(res.response.headers.get('x-content-type-options'), 'nosniff', 'Private image nosniff header missing.');
assert(res.response.headers.get('cache-control')?.includes('no-store'), 'Private image Cache-Control no-store missing.');
assert(res.body.length > 0, 'Private image binary body is empty.');

// ── 6. Admin Inbox Filtering and XSS Escaping ──
// Submit feedback with XSS string
const xssPayload = {
  topic: 'staff',
  rating: 3,
  message: '<script>alert("xss")</script> Personel nazikti.',
  contact: '<img src=x onerror=alert(1)>',
  language: 'tr',
};
res = await request('/hvk/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(xssPayload),
});
assert.equal(res.response.status, 201, 'XSS test submission failed.');

// Read admin inbox
res = await request('/hvk/admin/feedbacks.php', { cookie: sessionCookie });
assert.equal(res.response.status, 200, 'Admin feedbacks page did not load.');
assert(!res.body.includes('<script>alert("xss")</script>'), 'Unescaped script tag present in admin HTML.');
assert(res.body.includes('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;') || res.body.includes('&lt;script&gt;'), 'HTML escaping is missing.');
assert(!res.body.includes('<img src=x onerror=alert(1)>'), 'Unescaped img XSS tag present in admin HTML.');

// Filter by topic
res = await request('/hvk/admin/feedbacks.php?topic=hygiene', { cookie: sessionCookie });
assert.equal(res.response.status, 200, 'Filtered by topic page failed.');
assert(res.body.includes('Clean restaurant, image attached test'), 'Filtered topic item missing.');

// Filter by rating
res = await request('/hvk/admin/feedbacks.php?rating=3', { cookie: sessionCookie });
assert.equal(res.response.status, 200, 'Filtered by rating page failed.');
assert(res.body.includes('Personel nazikti.'), 'Filtered rating item missing.');

// ── 7. Admin Deletion Lifecycle & Physical File Deletion ──
// Get tokens from feedbacks page
const pageRes = await request('/hvk/admin/feedbacks.php', { cookie: sessionCookie });
const csrfToken = csrf(pageRes.body);
const subToken = submission(pageRes.body);

const deleteForm = new URLSearchParams({
  csrf_token: csrfToken,
  submission_token: subToken,
  id: String(mediaFeedback.owner.id),
});

res = await request('/hvk/admin/feedbacks.php', {
  method: 'POST',
  cookie: sessionCookie,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: deleteForm,
});
assert.equal(res.response.status, 303, 'Feedback deletion did not redirect with 303.');

// Confirm row is deleted from feedbacks table
const rowsAfter = sql(`SELECT id FROM feedbacks WHERE id = ${Number(mediaFeedback.owner.id)}`);
assert.equal(rowsAfter.length, 0, 'Feedback row still exists in database after deletion.');

// Confirm managed_uploads row is marked deleted and physical file on disk is GONE
const allAfter = JSON.parse(control('all-json')).filter(r => r.owner_type === 'feedback' && Number(r.owner_id) === Number(mediaFeedback.owner.id));
assert(allAfter.length > 0, 'No managed_uploads record found after deletion.');
assert(allAfter.every(r => r.state === 'deleted'), 'managed_uploads state is not deleted.');
assert(allAfter.every(r => Object.values(r.files).every(exists => exists === false)), 'Private physical image file still exists on disk after deletion!');

// Confirm accessing deleted private image now returns 404
res = await request(`/hvk/admin/private-image.php?id=${uploadId}`, { cookie: sessionCookie });
assert.equal(res.response.status, 404, 'Deleted private image did not return 404.');

// ── 8. Repeat Deletion (Idempotency) ──
const pageRes2 = await request('/hvk/admin/feedbacks.php', { cookie: sessionCookie });
const deleteForm2 = new URLSearchParams({
  csrf_token: csrf(pageRes2.body),
  submission_token: submission(pageRes2.body),
  id: String(mediaFeedback.owner.id),
});
res = await request('/hvk/admin/feedbacks.php', {
  method: 'POST',
  cookie: sessionCookie,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: deleteForm2,
});
assert.equal(res.response.status, 303, 'Repeat deletion did not return 303.');

// ── 9. Failed Deletion & Cleanup Retry ──
// Insert a synthetic pending cleanup record
sqlExec(`INSERT INTO managed_uploads (operation_id, owner_type, owner_id, purpose, file_role, storage_scope, storage_path, state)
         VALUES ('sim_pending_op_123456789012', 'feedback', 999999, 'feedback', 'main', 'private', 'feedback/nonexistent_simulated.webp', 'cleanup_pending')`);

// Verify pending cleanup alert appears in admin feedbacks
res = await request('/hvk/admin/feedbacks.php', { cookie: sessionCookie });
assert(res.body.includes('data-testid="pending-cleanup-alert"'), 'Pending cleanup alert was not rendered.');
assert(res.body.includes('data-testid="retry-cleanup-button"'), 'Retry cleanup button was not rendered.');

// Trigger retry cleanup action
const retryForm = new URLSearchParams({
  csrf_token: csrf(res.body),
  submission_token: submission(res.body),
  action: 'retry_cleanup',
});
res = await request('/hvk/admin/feedbacks.php', {
  method: 'POST',
  cookie: sessionCookie,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: retryForm,
});
assert.equal(res.response.status, 303, 'Retry cleanup did not return 303.');

// Clean simulated row
sqlExec(`DELETE FROM managed_uploads WHERE operation_id = 'sim_pending_op_123456789012'`);

// ── 10. Rate Limiting (Abuse Protection) ──
// Rapidly send requests to trigger throttle limit
let throttled = false;
let retryAfterHeader = null;
for (let i = 0; i < 35; i++) {
  res = await request('/hvk/admin/api/feedback.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ topic: 'taste', rating: 5, message: `Throttling flood test ${i}` }),
  });
  if (res.response.status === 429) {
    throttled = true;
    retryAfterHeader = res.response.headers.get('retry-after');
    break;
  }
}
assert(throttled, 'Feedback submission throttle did not trigger 429 status.');
assert(retryAfterHeader !== null && Number(retryAfterHeader) > 0, 'Retry-After header missing on 429 response.');

// Clean up throttles and test records in database
sqlExec("DELETE FROM admin_login_throttles WHERE signal_type = 'feedback'");
sqlExec('DELETE FROM feedbacks');

console.log(JSON.stringify({
  status: 'PASS',
  checks: [
    'method gate',
    'origin control',
    'field validations',
    'scalar type enforcement',
    'idempotency (201/200/409)',
    'physical image upload & storage',
    'private image access control',
    'admin inbox filtering & escaping',
    'physical image & thumbnail deletion',
    'repeat deletion idempotency',
    'cleanup pending detection & retry',
    'rate limiting & Retry-After',
  ],
}));
