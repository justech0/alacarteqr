import { copyFile, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const source = path.join(repo, 'index.html');
const output = path.join(repo, 'public_html', 'index.html');

await copyFile(source, output);
const [sourceBytes, outputBytes] = await Promise.all([readFile(source), readFile(output)]);
if (!sourceBytes.equals(outputBytes)) {
  throw new Error('The guest build output does not match index.html.');
}

console.log('Guest build: PASS');
