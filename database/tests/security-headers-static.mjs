import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const read = (relative) => fs.readFileSync(path.join(repo, relative), 'utf8');
const hash = (text) => `'sha256-${crypto.createHash('sha256').update(text, 'utf8').digest('base64')}'`;

// Guest CSP hashes must match the inline blocks of the shipped index.html.
const guest = read('public_html/index.html');
const htaccess = read('public_html/.htaccess');
const csp = /Header always set Content-Security-Policy "([^"]+)"/.exec(htaccess)?.[1];
assert(csp, 'The guest Content-Security-Policy is missing.');
const directive = (name) => csp.split(';').map(part => part.trim()).find(part => part.startsWith(`${name} `)) ?? '';
const scriptHashes = [...guest.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)].map(match => hash(match[1]));
const styleHashes = [...guest.matchAll(/<style(?:\s[^>]*)?>([\s\S]*?)<\/style>/gi)].map(match => hash(match[1]));
assert.equal(directive('script-src'), ['script-src', ...scriptHashes].join(' '), 'Guest script-src hashes are stale. Recompute them.');
assert.equal(directive('style-src'), ['style-src', ...styleHashes].join(' '), 'Guest style-src hashes are stale. Recompute them.');
assert.equal(directive('frame-ancestors'), "frame-ancestors 'none'", 'Guest clickjacking protection is missing.');
assert.equal(directive('default-src'), "default-src 'none'", 'Guest default-src must be none.');
assert(!/unsafe-(?:inline|eval|hashes)/.test(csp), 'The guest policy contains an unsafe allowance.');
assert(/RedirectMatch 404 "\(\?i\)\(\?:\^\|\/\)database\/"/.test(htaccess), 'The public database directory rule is missing.');

// Admin CSP and headers come from security.php.
const security = read('admin/security.php');
const adminCsp = /const CHEF_ADMIN_CSP = ([\s\S]*?);\n/.exec(security)?.[1] ?? '';
assert(adminCsp.includes("frame-ancestors 'none'") && adminCsp.includes("script-src 'self'"), 'The admin policy is incomplete.');
assert(!/unsafe-(?:inline|eval|hashes)/.test(adminCsp), 'The admin policy contains an unsafe allowance.');
assert(/chef_request_id\(\);\n\s+chef_send_security_headers\(\);/.test(security), 'chef_bootstrap does not send security headers first.');
for (const file of fs.readdirSync(path.join(repo, 'admin')).filter(name => name.endsWith('.php'))) {
  const source = read(`admin/${file}`);
  assert(!/<script(?![^>]*\bsrc=)[^>]*>/i.test(source), `${file} contains an inline script that the admin policy blocks.`);
  assert(!/\sstyle="/i.test(source) && !/<style[\s>]/i.test(source), `${file} contains inline style that the admin policy blocks.`);
}

// The media hardening policy in PHP must equal the shipped media/.htaccess.
const media = read('admin/media-service.php');
const policy = /<<<'HTACCESS'\r?\n([\s\S]*?)\r?\nHTACCESS;/.exec(media)?.[1];
assert.equal(policy, read('media/.htaccess'), 'CHEF_MEDIA_HARDENING_POLICY differs from media/.htaccess.');

console.log('Security header static checks: PASS');
