import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';

const repo = path.resolve(import.meta.dirname, '..', '..');
const php = process.env.CHEF_PHP_EXE;
const privateRoot = process.env.CHEF_TEST_PRIVATE_ROOT;
const configFile = path.join(privateRoot, 'config.php');
const productionConfig = path.join(privateRoot, 'config.production-test.php');
const credentialsFile = path.join(privateRoot, 'admin.credentials');
const fixtures = path.join(privateRoot, 'fixtures');

function run(command, args, extraEnv = {}) {
  const result = spawnSync(command, args, {
    cwd: repo,
    env: { ...process.env, ...extraEnv },
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
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

run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
const commonArgs = ['-d', 'upload_max_filesize=6M', '-d', 'post_max_size=6M', '-S'];
const dev = spawn(php, [...commonArgs, '127.0.0.1:38082', '-t', repo], {
  cwd: repo, env: { ...process.env, CHEF_CONFIG_FILE: configFile }, windowsHide: true, stdio: 'ignore',
});
const production = spawn(php, [...commonArgs, '127.0.0.1:38083', '-t', repo], {
  cwd: repo, env: { ...process.env, CHEF_CONFIG_FILE: productionConfig }, windowsHide: true, stdio: 'ignore',
});

try {
  await waitFor('http://127.0.0.1:38082/hvk/admin/login.php');
  await waitFor('http://127.0.0.1:38083/hvk/admin/login.php');
  run(process.execPath, [path.join(repo, 'database/tests/security-headers-http.mjs')], {
    CHEF_HTTP_BASE: 'http://127.0.0.1:38082',
    CHEF_PROD_BACKEND: 'http://127.0.0.1:38083',
  });
  run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
  run(process.execPath, [path.join(repo, 'database/tests/auth-http.mjs')], {
    CHEF_HTTP_BASE: 'http://127.0.0.1:38082',
    CHEF_PROD_BACKEND: 'http://127.0.0.1:38083',
    CHEF_CONFIG_FILE: configFile,
    CHEF_PRODUCTION_CONFIG_FILE: productionConfig,
    CHEF_PHP_EXE: php,
    CHEF_TEST_CREDENTIALS: credentialsFile,
    CHEF_TLS_KEY: path.join(path.dirname(privateRoot), 'localhost-key.pem'),
    CHEF_TLS_CERT: path.join(path.dirname(privateRoot), 'localhost-cert.pem'),
  });
  run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
  run(php, [path.join(repo, 'database/tests/media-fixtures.php'), fixtures]);
  run(process.execPath, [path.join(repo, 'database/tests/media-http.mjs')], {
    CHEF_HTTP_BASE: 'http://127.0.0.1:38082',
    CHEF_CONFIG_FILE: configFile,
    CHEF_PHP_EXE: php,
    CHEF_MEDIA_FIXTURES: fixtures,
    CHEF_TEST_CREDENTIALS: credentialsFile,
  });
  run(php, [path.join(repo, 'database/tests/setup-auth-test.php')]);
  run(php, [path.join(repo, 'database/tests/media-fixtures.php'), fixtures]);
  run(php, [path.join(repo, 'database/tests/catalog-unit.php')], {
    CHEF_CONFIG_FILE: configFile,
    CHEF_TEST_CREDENTIALS: credentialsFile,
  });
  run(process.execPath, [path.join(repo, 'database/tests/catalog-http.mjs')], {
    CHEF_HTTP_BASE: 'http://127.0.0.1:38082',
    CHEF_CONFIG_FILE: configFile,
    CHEF_PHP_EXE: php,
    CHEF_MEDIA_FIXTURES: fixtures,
    CHEF_TEST_CREDENTIALS: credentialsFile,
  });
} finally {
  dev.kill();
  production.kill();
}
