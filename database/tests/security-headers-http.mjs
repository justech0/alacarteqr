import assert from 'node:assert/strict';

const devBase = process.env.CHEF_HTTP_BASE;
const prodBase = process.env.CHEF_PROD_BACKEND;
assert(devBase && prodBase, 'CHEF_HTTP_BASE and CHEF_PROD_BACKEND are required.');

async function request(base, route, options = {}) {
  const response = await fetch(new URL(route, base), { redirect: 'manual', ...options });
  return { status: response.status, headers: response.headers, body: await response.text() };
}

function assertHeaders(response, label) {
  const csp = response.headers.get('content-security-policy') ?? '';
  assert(csp.includes("frame-ancestors 'none'"), `${label}: CSP frame-ancestors is missing.`);
  assert(csp.includes("script-src 'self'") && !csp.includes('unsafe-'), `${label}: CSP script-src is unsafe.`);
  assert.equal(response.headers.get('x-frame-options'), 'DENY', `${label}: X-Frame-Options is missing.`);
  assert.equal(response.headers.get('x-content-type-options'), 'nosniff', `${label}: nosniff is missing.`);
  assert.equal(response.headers.get('referrer-policy'), 'same-origin', `${label}: Referrer-Policy is missing.`);
}

const login = await request(devBase, '/admin/login.php');
assert.equal(login.status, 200, 'Login page failed.');
assertHeaders(login, 'login.php');
assert.equal(login.headers.get('strict-transport-security'), null, 'HSTS must not be sent over plain development HTTP.');

const protectedPage = await request(devBase, '/admin/products.php');
assert.equal(protectedPage.status, 302, 'Protected page did not redirect.');
assertHeaders(protectedPage, 'products.php redirect');

const menu = await request(devBase, '/admin/api/menu.php');
assert.equal(menu.status, 200, 'Menu API failed.');
assertHeaders(menu, 'api/menu.php');
assert.match(menu.headers.get('cache-control') ?? '', /public/, 'Menu API cache policy changed.');

const privateImage = await request(devBase, '/admin/private-image.php?id=1');
assert.equal(privateImage.status, 302, 'Anonymous private image request was not redirected.');
assertHeaders(privateImage, 'private-image.php');

const feedback = (base, origin) => request(base, '/admin/api/feedback.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Origin: origin },
  body: JSON.stringify({ topic: 'other', rating: 5, message: 'Origin test.' }),
});

for (const origin of ['null', 'https://attacker.example']) {
  const response = await feedback(devBase, origin);
  assert.equal(response.status, 403, `Development API accepted Origin ${origin}.`);
}
const prodLoopback = await feedback(prodBase, 'http://localhost');
assert.equal(prodLoopback.status, 403, 'Production API accepted a loopback Origin.');
const prodNull = await feedback(prodBase, 'null');
assert.equal(prodNull.status, 403, 'Production API accepted an opaque Origin.');
const prodSameHost = await feedback(prodBase, new URL(prodBase).origin);
assert.notEqual(prodSameHost.status, 403, 'Production API rejected its own Origin.');

console.log('Security header HTTP checks: PASS');
