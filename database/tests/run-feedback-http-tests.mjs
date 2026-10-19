import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const php = process.env.CHEF_PHP_EXE || (fs.existsSync('C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe') ? 'C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe' : null);
if (!php || !fs.existsSync(php)) throw new Error('CHEF_PHP_EXE is unavailable.');

const defaultTask05 = path.join(os.tmpdir(), 'chef-task05');
const defaultConfigFile = path.join(defaultTask05, 'private', 'config.php');
const defaultCredentials = path.join(defaultTask05, 'private', 'admin.credentials');
const defaultFixtures = path.join(defaultTask05, 'fixtures');

const existingConfig = process.env.CHEF_CONFIG_FILE || (fs.existsSync(defaultConfigFile) ? defaultConfigFile : null);
const existingCreds = process.env.CHEF_TEST_CREDENTIALS || (fs.existsSync(defaultCredentials) ? defaultCredentials : null);
const existingFixtures = process.env.CHEF_MEDIA_FIXTURES || (fs.existsSync(defaultFixtures) ? defaultFixtures : null);

const reuseExisting = Boolean(existingConfig && existingCreds && existingFixtures);
const privateRoot = reuseExisting ? null : fs.mkdtempSync(path.join(os.tmpdir(), 'chef-task08-'));
const commonEnv = {
  ...process.env,
  CHEF_PHP_EXE: php,
  CHEF_TEST_ALLOW_RESET: 'task02-isolated-docker',
  CHEF_TEST_HOST: process.env.CHEF_TEST_HOST ?? '127.0.0.1',
  CHEF_TEST_PORT: process.env.CHEF_TEST_PORT ?? '33306',
  CHEF_TEST_USER: process.env.CHEF_TEST_USER ?? 'root',
  CHEF_TEST_PASSWORD: process.env.CHEF_TEST_PASSWORD ?? '',
  CHEF_TEST_PRIVATE_ROOT: privateRoot ?? process.env.CHEF_TEST_PRIVATE_ROOT,
  CHEF_CONFIG_FILE: existingConfig ?? path.join(privateRoot, 'config.php'),
  CHEF_TEST_CREDENTIALS: existingCreds ?? path.join(privateRoot, 'admin.credentials'),
  CHEF_MEDIA_FIXTURES: existingFixtures ?? path.join(privateRoot, 'fixtures'),
};

function run(command, args, env = commonEnv) {
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

let server;
try {
  if (!reuseExisting) {
    run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
    run(php, [path.join(repo, 'database/tests/media-fixtures.php'), commonEnv.CHEF_MEDIA_FIXTURES]);
  }
  server = spawn(php, ['-d', 'upload_max_filesize=6M', '-d', 'post_max_size=6M', '-S', '127.0.0.1:38086', '-t', repo], {
    cwd: repo,
    env: commonEnv,
    windowsHide: true,
    stdio: 'ignore',
  });
  await waitFor('http://127.0.0.1:38086/admin/api/feedback.php');
  run(process.execPath, [path.join(repo, 'database/tests/feedback-http.mjs')], {
    ...commonEnv,
    CHEF_HTTP_BASE: 'http://127.0.0.1:38086',
  });
} finally {
  if (server && !server.killed) server.kill();
  if (privateRoot !== null) {
    const resolved = path.resolve(privateRoot);
    if (path.dirname(resolved) !== path.resolve(os.tmpdir()) || !path.basename(resolved).startsWith('chef-task08-')) {
      throw new Error('Refused to remove an unexpected test path.');
    }
    fs.rmSync(resolved, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
  }
}
