import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import http from 'node:http';
import https from 'node:https';
import { spawn, spawnSync } from 'node:child_process';

const httpBase = new URL(process.env.CHEF_HTTP_BASE);
const productionBackend = new URL(process.env.CHEF_PROD_BACKEND);
const php = process.env.CHEF_PHP_EXE;
const configFile = process.env.CHEF_CONFIG_FILE;
const productionConfig = process.env.CHEF_PRODUCTION_CONFIG_FILE;
const privateRoot = process.env.CHEF_TEST_PRIVATE_ROOT;
const repo = path.resolve(import.meta.dirname, '..', '..');
const credentials = JSON.parse(fs.readFileSync(process.env.CHEF_TEST_CREDENTIALS, 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function control(command, extra = [], config = configFile) {
  const result = spawnSync(php, [path.join(repo, 'database/tests/auth-db-control.php'), command, ...extra], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: config },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`Control ${command} failed.`);
  return result.stdout.trim();
}

function request(base, route, options = {}) {
  const target = new URL(route, base);
  const transport = target.protocol === 'https:' ? https : http;
  const body = options.form ? new URLSearchParams(options.form).toString() : (options.body ?? '');
  const headers = { ...(options.headers ?? {}) };
  if (options.cookie) headers.Cookie = options.cookie;
  if (body !== '') {
    headers['Content-Type'] = headers['Content-Type'] ?? 'application/x-www-form-urlencoded';
    headers['Content-Length'] = Buffer.byteLength(body);
  }
  return new Promise((resolve, reject) => {
    const req = transport.request(target, {
      method: options.method ?? 'GET', headers, rejectUnauthorized: false,
    }, response => {
      const chunks = [];
      response.on('data', chunk => chunks.push(chunk));
      response.on('end', () => resolve({
        status: response.statusCode,
        headers: response.headers,
        body: Buffer.concat(chunks).toString('utf8'),
      }));
    });
    req.on('error', reject);
    if (body !== '') req.write(body);
    req.end();
  });
}

function firstCookie(response) {
  const header = response.headers['set-cookie']?.[0] ?? '';
  return header.split(';')[0];
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

async function newLoginSession() {
  const response = await request(httpBase, '/admin/login.php');
  assert(response.status === 200, 'Login GET failed.');
  assert((response.headers['cache-control'] ?? '').includes('no-store'), 'Login was cacheable.');
  const setCookie = response.headers['set-cookie']?.[0] ?? '';
  assert(/httponly/i.test(setCookie) && /samesite=strict/i.test(setCookie) && !/;\s*secure/i.test(setCookie), 'Local cookie flags are invalid.');
  return { cookie: firstCookie(response), token: csrf(response.body), submission: submission(response.body) };
}

async function refreshLoginSession(cookie) {
  const response = await request(httpBase, '/admin/login.php', { cookie });
  assert(response.status === 200, 'Login refresh failed.');
  return { cookie: firstCookie(response) || cookie, token: csrf(response.body), submission: submission(response.body) };
}

async function login(username, password) {
  const session = await newLoginSession();
  const oldCookie = session.cookie;
  const response = await request(httpBase, '/admin/login.php', {
    method: 'POST', cookie: session.cookie,
    form: { csrf_token: session.token, submission_token: session.submission, username, password },
  });
  const newCookie = firstCookie(response) || session.cookie;
  return { response, cookie: newCookie, oldCookie };
}

function snapshotUploads() {
  const root = path.join(repo, 'admin/uploads');
  const result = {};
  const walk = directory => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) walk(absolute);
      else if (entry.isFile()) result[path.relative(root, absolute)] = crypto.createHash('sha256').update(fs.readFileSync(absolute)).digest('hex');
    }
  };
  // The legacy upload directory is absent in a clean checkout. Absent means no files.
  if (fs.existsSync(root)) walk(root);
  return result;
}

function concurrentAttempt() {
  return new Promise((resolve, reject) => {
    const child = spawn(php, [path.join(repo, 'database/tests/login-attempt-cli.php')], {
      cwd: repo, env: { ...process.env, CHEF_CONFIG_FILE: configFile }, stdio: ['pipe', 'pipe', 'pipe'],
    });
    let output = '';
    child.stdout.on('data', chunk => output += chunk);
    child.on('error', reject);
    child.on('exit', code => code === 0 ? resolve(JSON.parse(output)) : reject(new Error('Concurrent login process failed.')));
    child.stdin.end(JSON.stringify({ username: 'parallel-owner', password: 'wrong', address: '198.51.100.8' }));
  });
}

const inventory = JSON.parse(control('inventory-json'));
const beforeUnauthorizedDb = control('counts-json');
const beforeUnauthorizedFiles = JSON.stringify(snapshotUploads());
for (const route of Object.keys(inventory.protected)) {
  const response = await request(httpBase, `/admin/${route}`);
  assert([302, 303].includes(response.status), `Unauthorized route accepted: ${route}`);
}
assert(control('counts-json') === beforeUnauthorizedDb, 'Unauthorized reads changed the database.');
assert(JSON.stringify(snapshotUploads()) === beforeUnauthorizedFiles, 'Unauthorized reads changed files.');

for (const route of inventory.includes) {
  const response = await request(httpBase, `/admin/${route}`);
  assert(response.status === 404, `Shared include is directly accessible: ${route}`);
}

const menu = await request(httpBase, '/admin/api/menu.php');
assert(menu.status === 200 && !menu.headers['set-cookie'], 'Anonymous menu started a session.');
const menuPost = await request(httpBase, '/admin/api/menu.php', { method: 'POST' });
assert(menuPost.status === 405 && menuPost.headers.allow === 'GET', 'Menu method control failed.');
const feedbackGet = await request(httpBase, '/admin/api/feedback.php');
assert(feedbackGet.status === 405 && feedbackGet.headers.allow === 'POST, OPTIONS', 'Feedback method control failed.');

let session = await newLoginSession();
let response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { username: 'owner', password: 'wrong' } });
assert(response.status === 403, 'Missing login CSRF was accepted.');
response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: 'bad', username: 'owner', password: 'wrong' } });
assert(response.status === 403, 'Invalid login CSRF was accepted.');

control('reset-throttle');
const wrong = await login('owner', 'wrong');
control('reset-throttle');
const unknown = await login('missing-owner', 'wrong');
assert(wrong.response.status === 401 && unknown.response.status === 401, 'Credential errors use different status codes.');
assert(wrong.response.body.includes('Kullanıcı adı veya şifre hatalı.') && unknown.response.body.includes('Kullanıcı adı veya şifre hatalı.'), 'Credential errors use different public messages.');

control('reset-throttle');
session = await newLoginSession();
for (let attempt = 0; attempt < 5; attempt++) {
  response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: 'limited-owner', password: 'wrong' } });
  assert(response.status === 401, 'Account threshold blocked too early.');
  session = await refreshLoginSession(session.cookie);
}
response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: 'limited-owner', password: 'wrong' } });
assert(response.status === 429 && Number(response.headers['retry-after']) > 0, 'Account threshold did not return 429.');
const blockedSnapshot = control('throttle-json');
session = await refreshLoginSession(session.cookie);
response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: 'limited-owner', password: 'wrong' } });
assert(response.status === 429 && control('throttle-json') === blockedSnapshot, 'Blocked request changed block expiration or counters.');

control('reset-throttle');
session = await newLoginSession();
for (let attempt = 0; attempt < 10; attempt++) {
  response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: `address-${attempt}`, password: 'wrong' } });
  assert(response.status === 401, 'Full-address threshold blocked too early.');
  session = await refreshLoginSession(session.cookie);
}
response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: 'address-final', password: 'wrong' } });
assert(response.status === 429, 'Full-address threshold did not block.');

control('reset-throttle');
await Promise.all(Array.from({ length: 12 }, () => concurrentAttempt()));
const concurrentRows = JSON.parse(control('throttle-json'));
const accountRow = concurrentRows.find(row => row.signal_type === 'account');
assert(accountRow && Number(accountRow.failure_count) === 5 && accountRow.blocked_until, 'Concurrent account counter was not atomic.');

control('reset-throttle');
session = await newLoginSession();
control('break-throttle');
response = await request(httpBase, '/admin/login.php', { method: 'POST', cookie: session.cookie, form: { csrf_token: session.token, submission_token: session.submission, username: 'owner', password: 'wrong' } });
assert(response.status === 503 && response.body.includes('Request ID') && !response.body.includes('admin_login_throttles'), 'Throttle storage failure leaked details or failed open.');
control('restore-throttle');

control('reset-throttle');
let valid = await login(credentials.admin_username, credentials.admin_password);
assert(valid.response.status === 302 && valid.cookie !== valid.oldCookie, 'Successful login did not renew the session ID.');
response = await request(httpBase, '/admin/index.php', { cookie: valid.oldCookie });
assert([302, 303].includes(response.status), 'The old pre-login cookie restored access.');
response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
assert(response.status === 200, 'Valid administrator session was rejected.');
for (const [route, methods] of Object.entries(inventory.protected)) {
  if (!methods.includes('GET')) continue;
  const protectedPage = await request(httpBase, `/admin/${route}`, { cookie: valid.cookie });
  const expectedStatus = route === 'private-image.php' ? 404 : 200;
  assert(protectedPage.status === expectedStatus, `Authenticated route failed: ${route}`);
  assert((protectedPage.headers['cache-control'] ?? '').includes('no-store'), `Authenticated route was cacheable: ${route}`);
}

control('break-admin-storage');
try {
  response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
  assert(response.status === 503 && response.body.includes('Request ID') && !response.body.includes('admins_unavailable'), 'Administrator storage failure did not fail closed.');
} finally {
  control('restore-admin-storage');
}
response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
assert(response.status === 200, 'Administrator session did not recover after storage restoration.');

const authPage = response.body;
const authToken = csrf(authPage);
const beforeCsrfDb = control('counts-json');
const beforeCsrfFiles = JSON.stringify(snapshotUploads());
const seeOtherRoutes = new Set(['categories.php', 'category-order.php', 'feedbacks.php', 'product-order.php', 'products.php']);
for (const [route, actions] of Object.entries(inventory.actions)) {
  for (const action of actions) {
    for (const token of ['', 'invalid']) {
      const form = { action };
      if (token !== '') form.csrf_token = token;
      const result = await request(httpBase, `/admin/${route}`, { method: 'POST', cookie: valid.cookie, form });
      const expected = route === 'logout.php' ? 403 : (seeOtherRoutes.has(route) ? 303 : 302);
      assert(result.status === expected, `CSRF control failed: ${route} ${action}`);
    }
  }
}
assert(control('counts-json') === beforeCsrfDb, 'Rejected CSRF requests changed the database.');
assert(JSON.stringify(snapshotUploads()) === beforeCsrfFiles, 'Rejected CSRF requests changed files.');

response = await request(httpBase, '/admin/index.php', { method: 'PUT', cookie: valid.cookie });
assert(response.status === 405 && response.headers.allow === 'GET, POST', 'Protected method control failed.');
response = await request(httpBase, '/admin/logout.php', { method: 'GET', cookie: valid.cookie });
assert(response.status === 405 && response.headers.allow === 'POST', 'Logout GET was accepted.');

valid = await login(credentials.admin_username, credentials.admin_password);
const passwordVersionBefore = Number(control('auth-version'));
control('change-owner-password');
assert(Number(control('auth-version')) === passwordVersionBefore + 1, 'Password change did not increment auth_version.');
response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
assert([302, 303].includes(response.status), 'Password change did not invalidate the old session.');

valid = await login(credentials.admin_username, credentials.changed_admin_password);
const recoveryPath = path.join(privateRoot, 'owner-recovery.json');
control('make-recovery-file', [recoveryPath]);
const versionBefore = Number(control('auth-version'));
const recovery = spawnSync(php, [path.join(repo, 'database/recover-owner.php'), `--file=${recoveryPath}`], {
  cwd: repo, env: { ...process.env, CHEF_CONFIG_FILE: configFile }, encoding: 'utf8',
});
assert(recovery.status === 0 && !fs.existsSync(recoveryPath), 'Local CLI recovery failed or retained its file.');
assert(Number(control('auth-version')) === versionBefore + 1, 'Owner recovery did not increment auth_version.');
response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
assert([302, 303].includes(response.status), 'Owner recovery did not invalidate the old session.');

valid = await login(credentials.admin_username, credentials.recovered_admin_password);
response = await request(httpBase, '/admin/index.php', { cookie: valid.cookie });
const logoutToken = csrf(response.body);
const logoutSubmission = submission(response.body);
const oldAuthenticatedCookie = valid.cookie;
response = await request(httpBase, '/admin/logout.php', { method: 'POST', cookie: valid.cookie, form: { csrf_token: logoutToken, submission_token: logoutSubmission } });
const expiredCookie = response.headers['set-cookie']?.join(';') ?? '';
assert(response.status === 303 && /path=\/admin/i.test(expiredCookie) && /expires=/i.test(expiredCookie), 'Logout did not expire the matching cookie.');
response = await request(httpBase, '/admin/index.php', { cookie: oldAuthenticatedCookie });
assert([302, 303].includes(response.status), 'The old logout cookie restored access.');

const key = fs.readFileSync(process.env.CHEF_TLS_KEY);
const cert = fs.readFileSync(process.env.CHEF_TLS_CERT);
const directProduction = await request(productionBackend, '/admin/login.php');
assert(directProduction.status === 503, 'Production accepted an untrusted HTTP request.');
const proxy = https.createServer({ key, cert }, (incoming, outgoing) => {
  const proxyRequest = http.request({
    hostname: productionBackend.hostname,
    port: productionBackend.port,
    path: incoming.url,
    method: incoming.method,
    headers: { ...incoming.headers, host: productionBackend.host, 'x-forwarded-proto': 'https', 'x-forwarded-for': incoming.socket.remoteAddress },
  }, proxyResponse => {
    outgoing.writeHead(proxyResponse.statusCode, proxyResponse.headers);
    proxyResponse.pipe(outgoing);
  });
  incoming.pipe(proxyRequest);
});
await new Promise(resolve => proxy.listen(38443, '127.0.0.1', resolve));
try {
  const secureLogin = await request(new URL('https://127.0.0.1:38443'), '/admin/login.php');
  const secureCookie = secureLogin.headers['set-cookie']?.[0] ?? '';
  assert(secureLogin.status === 200 && /;\s*secure/i.test(secureCookie) && /httponly/i.test(secureCookie) && /samesite=strict/i.test(secureCookie), 'Production HTTPS cookie flags are invalid.');
} finally {
  await new Promise(resolve => proxy.close(resolve));
}

console.log(JSON.stringify({ status: 'PASS', routes: Object.keys(inventory.protected).length, includes: inventory.includes.length }, null, 2));
