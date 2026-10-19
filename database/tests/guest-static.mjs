import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const source = fs.readFileSync(path.join(repo, 'index.html'), 'utf8');
const output = fs.readFileSync(path.join(repo, 'public_html', 'index.html'), 'utf8');

assert.equal(output, source, 'public_html/index.html differs from the editable guest source.');
assert(!source.includes('var h={categories:[{'), 'The production guest still contains bundled menu rows.');
assert(!source.includes('Izgara K\\xF6fte') && !source.includes('Izgara Köfte'), 'A demonstration product remains in the production guest.');
assert(source.includes('admin/api/menu.php'), 'The production guest does not request the public menu API.');
assert(source.includes('validMenuPayload'), 'The production guest does not validate the menu response.');
assert(source.includes('menuRequestState="loading"'), 'The accessible loading state is missing.');
assert(source.includes('menuRequestState="error"'), 'The accessible failure state is missing.');
assert(source.includes('loadMenu():Oe()'), 'The retry action is missing.');
assert(source.includes('textContent') && source.includes('replaceChildren'), 'Safe text rendering is missing.');
assert(source.includes('e?.[n.lang]||e?.tr||""'), 'Turkish translation fallback is missing.');

const scripts = [...source.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)];
assert(scripts.length > 0, 'No guest script was found.');
scripts.forEach((match, index) => new vm.Script(match[1], { filename: `guest-inline-${index + 1}.js` }));

console.log('Guest static checks: PASS');
