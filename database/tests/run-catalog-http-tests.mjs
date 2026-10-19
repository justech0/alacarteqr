import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const php = process.env.CHEF_PHP_EXE;
for (const name of ['CHEF_PHP_EXE', 'CHEF_CONFIG_FILE', 'CHEF_TEST_CREDENTIALS', 'CHEF_MEDIA_FIXTURES']) {
  if (!process.env[name]) throw new Error(`${name} is unavailable.`);
}

function run(command, args, env = process.env) {
  const result = spawnSync(command, args, { cwd: repo, env, encoding: 'utf8', windowsHide: true });
  if (result.stdout) process.stdout.write(result.stdout);
  if (result.status !== 0) {
    if (result.stderr) process.stderr.write(result.stderr);
    throw new Error(`${path.basename(command)} failed.`);
  }
}

async function waitFor(url) {
  for (let attempt = 0; attempt < 40; attempt++) {
    try {
      const response = await fetch(url, { redirect: 'manual' });
      if (response.status > 0) return;
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  throw new Error(`Server did not start: ${url}`);
}

const server = spawn(php, ['-d', 'upload_max_filesize=6M', '-d', 'post_max_size=6M', '-S', '127.0.0.1:38082', '-t', repo], {
  cwd: repo,
  env: process.env,
  windowsHide: true,
  stdio: 'ignore',
});
try {
  await waitFor('http://127.0.0.1:38082/admin/login.php');
  run(php, [path.join(repo, 'database/tests/catalog-unit.php')]);
  run(process.execPath, [path.join(repo, 'database/tests/catalog-http.mjs')], {
    ...process.env,
    CHEF_HTTP_BASE: 'http://127.0.0.1:38082',
  });
} finally {
  if (!server.killed) server.kill();
}
