import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const php = process.env.CHEF_PHP_EXE;
if (!php || !fs.existsSync(php)) throw new Error('CHEF_PHP_EXE is unavailable.');

const reuseExisting = Boolean(process.env.CHEF_CONFIG_FILE && process.env.CHEF_TEST_CREDENTIALS && process.env.CHEF_MEDIA_FIXTURES);
const privateRoot = reuseExisting ? null : fs.mkdtempSync(path.join(os.tmpdir(), 'chef-task07-'));
const outageConfig = path.join(os.tmpdir(), `chef-task07-outage-${process.pid}.php`);
const commonEnv = {
  ...process.env,
  CHEF_TEST_ALLOW_RESET: 'task02-isolated-docker',
  CHEF_TEST_HOST: process.env.CHEF_TEST_HOST ?? '127.0.0.1',
  CHEF_TEST_PORT: process.env.CHEF_TEST_PORT ?? '3306',
  CHEF_TEST_USER: process.env.CHEF_TEST_USER ?? 'root',
  CHEF_TEST_PASSWORD: process.env.CHEF_TEST_PASSWORD ?? '',
  CHEF_TEST_PRIVATE_ROOT: privateRoot ?? process.env.CHEF_TEST_PRIVATE_ROOT,
  CHEF_CONFIG_FILE: process.env.CHEF_CONFIG_FILE ?? path.join(privateRoot, 'config.php'),
  CHEF_TEST_CREDENTIALS: process.env.CHEF_TEST_CREDENTIALS ?? path.join(privateRoot, 'admin.credentials'),
  CHEF_MEDIA_FIXTURES: process.env.CHEF_MEDIA_FIXTURES ?? path.join(privateRoot, 'fixtures'),
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
let outageServer;
try {
  if (!reuseExisting) {
    run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
    run(php, [path.join(repo, 'database/tests/media-fixtures.php'), commonEnv.CHEF_MEDIA_FIXTURES]);
  }
  run(php, [path.join(repo, 'database/tests/setup-outage-config.php'), commonEnv.CHEF_CONFIG_FILE, outageConfig]);
  server = spawn(php, ['-d', 'upload_max_filesize=6M', '-d', 'post_max_size=6M', '-S', '127.0.0.1:38084', '-t', repo], {
    cwd: repo,
    env: commonEnv,
    windowsHide: true,
    stdio: 'ignore',
  });
  await waitFor('http://127.0.0.1:38084/admin/api/menu.php');
  run(process.execPath, [path.join(repo, 'database/tests/menu-http.mjs')], {
    ...commonEnv,
    CHEF_HTTP_BASE: 'http://127.0.0.1:38084',
  });
  outageServer = spawn(php, ['-S', '127.0.0.1:38085', '-t', repo], {
    cwd: repo,
    env: { ...commonEnv, CHEF_CONFIG_FILE: outageConfig },
    windowsHide: true,
    stdio: 'ignore',
  });
  await waitFor('http://127.0.0.1:38085/admin/api/menu.php');
  const outage = await fetch('http://127.0.0.1:38085/admin/api/menu.php');
  const outageBody = await outage.text();
  if (outage.status !== 503 || !/^application\/json\b/.test(outage.headers.get('content-type') ?? '')) {
    throw new Error('Database outage did not return the public JSON 503 response.');
  }
  const outagePayload = JSON.parse(outageBody);
  if (outagePayload.error !== 'Service unavailable.' || typeof outagePayload.requestId !== 'string' || /mysql|PDO|SQL|[A-Z]:\\|\/Users\//i.test(outageBody)) {
    throw new Error('Database outage exposed internal details or false menu data.');
  }
  process.stdout.write(JSON.stringify({ status: 'PASS', checks: ['database outage 503', 'generic public error'] }) + '\n');
} finally {
  if (server && !server.killed) server.kill();
  if (outageServer && !outageServer.killed) outageServer.kill();
  const resolvedOutage = path.resolve(outageConfig);
  if (path.dirname(resolvedOutage) !== path.resolve(os.tmpdir()) || !path.basename(resolvedOutage).startsWith('chef-task07-outage-')) {
    throw new Error('Refused to remove an unexpected outage configuration path.');
  }
  fs.rmSync(resolvedOutage, { force: true });
  if (privateRoot !== null) {
    const resolved = path.resolve(privateRoot);
    if (path.dirname(resolved) !== path.resolve(os.tmpdir()) || !path.basename(resolved).startsWith('chef-task07-')) {
      throw new Error('Refused to remove an unexpected test path.');
    }
    fs.rmSync(resolved, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
  }
}
