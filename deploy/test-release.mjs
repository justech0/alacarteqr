// Fresh installation test for the release archives in dist/release.
// It extracts both archives into new temporary directories, creates disposable database containers,
// follows INSTALL_TR.md, and runs HTTPS smoke checks through a separate local Apache instance.
// Variant A: MySQL 8.4.3 with the CLI steps. Variant B: MariaDB 11.8.9 with the phpMyAdmin SQL file.
// It uploads nothing and connects to no hosted service.

import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const release = path.join(repo, 'dist', 'release');
const phpDir = 'C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64';
const php = path.join(phpDir, 'php.exe');
const apacheRoot = 'C:\\laragon\\bin\\apache\\httpd-2.4.68-260617-Win64-VS18';
const httpd = path.join(apacheRoot, 'bin', 'httpd.exe');
// The Laragon Apache openssl.exe does not start without its DLL path. Git for Windows ships a working build.
const openssl = 'C:\\Program Files\\Git\\ucrt64\\bin\\openssl.exe';
const port = 38443;
const base = `https://127.0.0.1:${port}`;
const schemaVersion = fs.readFileSync(path.join(repo, 'database', 'lib.php'), 'utf8').match(/CHEF_SCHEMA_VERSION = '(\d+)'/)[1];
const baseline = JSON.parse(fs.readFileSync(path.join(repo, 'chef-workflow', 'docs', 'APPROVED_BASELINE.json'), 'utf8'));
const expectedGuestHash = baseline.current_revision.files.find(f => f.relative_path === 'public_html/index.html').sha256;

// Self-signed local certificate only. Scoped to this test process.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const results = [];
function record(variant, check, status, detail = '') {
  results.push({ variant, check, status, detail });
  console.log(`${status.padEnd(4)} [${variant}] ${check}${detail ? ` - ${detail}` : ''}`);
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, { encoding: 'utf8', windowsHide: true, ...options });
  if (!options.allowFailure && result.status !== 0) {
    throw new Error(`${path.basename(command)} ${args[0] ?? ''} failed: ${(result.stderr || result.stdout || '').trim().slice(0, 400)}`);
  }
  return result;
}

const random = (bytes = 24) => crypto.randomBytes(bytes).toString('hex');
const sha256 = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const fwd = p => p.replaceAll('\\', '/');

function restrictAcl(file) {
  const user = `${process.env.USERDOMAIN}\\${process.env.USERNAME}`;
  run('icacls', [file, '/inheritance:r', '/grant:r', `${user}:(F)`, '*S-1-5-18:(F)', '*S-1-5-32-544:(F)']);
}

function listFiles(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { recursive: true, withFileTypes: true })
    .filter(entry => entry.isFile())
    .map(entry => path.join(entry.parentPath ?? entry.path, entry.name));
}

async function startDatabase(kind) {
  const name = `chef-release-${kind}-${random(4)}`;
  const rootPassword = random();
  const image = kind === 'mysql' ? 'mysql:8.4.3' : 'mariadb:11.8.9';
  const hostPort = kind === 'mysql' ? 33318 : 33319;
  const envName = kind === 'mysql' ? 'MYSQL_ROOT_PASSWORD' : 'MARIADB_ROOT_PASSWORD';
  run('docker', ['run', '-d', '--rm', '--name', name, '-e', `${envName}=${rootPassword}`, '-p', `127.0.0.1:${hostPort}:3306`, image]);
  const client = kind === 'mysql' ? 'mysql' : 'mariadb';
  const sql = (statement, input) => run('docker', ['exec', '-i', '-e', `MYSQL_PWD=${rootPassword}`, name, client, '-h127.0.0.1', '-uroot', '-e', statement], { input, allowFailure: true });
  for (let attempt = 0; attempt < 120; attempt++) {
    if (sql('SELECT 1').status === 0) break;
    await new Promise(resolve => setTimeout(resolve, 1000));
    if (attempt === 119) throw new Error(`${image} did not start.`);
  }
  // Documented step: one new empty database and one application user.
  const database = 'chef_fresh';
  const user = 'chef_app';
  const password = random();
  const created = sql(`CREATE DATABASE ${database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '${user}'@'%' IDENTIFIED BY '${password}'; GRANT ALL PRIVILEGES ON ${database}.* TO '${user}'@'%';`);
  if (created.status !== 0) throw new Error(`Database creation failed: ${created.stderr}`);
  const importSql = file => run('docker', ['exec', '-i', '-e', `MYSQL_PWD=${password}`, name, client, '-h127.0.0.1', `-u${user}`, '--default-character-set=utf8mb4', database], { input: fs.readFileSync(file, 'utf8'), allowFailure: true });
  const version = sql('SELECT VERSION()').stdout.trim().split(/\r?\n/).pop();
  return { name, hostPort, database, user, password, importSql, version, stop: () => run('docker', ['rm', '-f', name], { allowFailure: true }) };
}

function extract(zip, target) {
  fs.mkdirSync(target, { recursive: true });
  // Windows bsdtar reads ZIP files. GNU tar from Git Bash does not.
  run(path.join(process.env.SystemRoot ?? 'C:\\Windows', 'System32', 'tar.exe'), ['-xf', zip, '-C', target]);
}

function writeConfig(home, db) {
  const docroot = path.join(home, 'public_html');
  const privateRoot = path.join(home, 'chef-private');
  const dummyHash = run(php, ['-r', `echo password_hash('${random()}', PASSWORD_DEFAULT);`]).stdout.trim();
  let text = fs.readFileSync(path.join(privateRoot, 'config.production.example.php'), 'utf8');
  const values = {
    '<DATABASE_NAME>': db.database,
    '<DATABASE_USER>': db.user,
    '<DATABASE_PASSWORD>': db.password,
    '<RANDOM_HMAC_KEY>': random(32),
    '<DUMMY_PASSWORD_HASH>': dummyHash,
    '<PRIVATE_ROOT>': fwd(privateRoot),
    '<DOCUMENT_ROOT>': fwd(docroot),
    '<BASE_PATH>': '',
  };
  for (const [key, value] of Object.entries(values)) text = text.replaceAll(key, value);
  text = text.replaceAll('host=localhost;port=3306', `host=127.0.0.1;port=${db.hostPort}`);
  assert(!text.split('\n').some(line => !line.trim().startsWith('//') && /<[A-Z_]+>/.test(line)), 'A configuration placeholder remains.');
  const configFile = path.join(privateRoot, 'config.php');
  fs.writeFileSync(configFile, text);
  restrictAcl(configFile);
  return configFile;
}

function writeApacheConf(work, docroot) {
  const cert = path.join(work, 'cert.pem');
  const key = path.join(work, 'key.pem');
  run(openssl, ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', key, '-out', cert, '-days', '1', '-subj', '/CN=127.0.0.1'], {
    env: { ...process.env, OPENSSL_CONF: path.join(apacheRoot, 'conf', 'openssl.cnf') },
  });
  const modules = ['authz_core', 'alias', 'dir', 'env', 'headers', 'mime', 'log_config', 'setenvif', 'socache_shmcb', 'ssl'];
  const conf = [
    `ServerRoot "${fwd(apacheRoot)}"`,
    `Listen 127.0.0.1:${port}`,
    ...modules.map(m => `LoadModule ${m}_module modules/mod_${m}.so`),
    `LoadFile "${fwd(phpDir)}/libcrypto-3-x64.dll"`,
    `LoadFile "${fwd(phpDir)}/libssl-3-x64.dll"`,
    `LoadModule php_module "${fwd(phpDir)}/php8apache2_4.dll"`,
    `PHPIniDir "${fwd(phpDir)}"`,
    'AddType application/x-httpd-php .php',
    'TypesConfig conf/mime.types',
    'ServerName 127.0.0.1',
    `PidFile "${fwd(work)}/httpd.pid"`,
    `ErrorLog "${fwd(work)}/error.log"`,
    'LogFormat "%h %r %>s" common',
    `CustomLog "${fwd(work)}/access.log" common`,
    'SSLSessionCache none',
    '<Directory />',
    '    AllowOverride None',
    '    Require all denied',
    '</Directory>',
    '<Files ".ht*">',
    '    Require all denied',
    '</Files>',
    `DocumentRoot "${fwd(docroot)}"`,
    `<Directory "${fwd(docroot)}">`,
    '    Options FollowSymLinks',
    '    AllowOverride All',
    '    Require all granted',
    '</Directory>',
    'DirectoryIndex index.html index.php',
    // Documented hosting PHP settings.
    'php_admin_value upload_max_filesize 6M',
    'php_admin_value post_max_size 6M',
    'php_admin_flag display_errors off',
    `<VirtualHost 127.0.0.1:${port}>`,
    '    SSLEngine on',
    `    SSLCertificateFile "${fwd(cert)}"`,
    `    SSLCertificateKeyFile "${fwd(key)}"`,
    '</VirtualHost>',
  ].join('\n');
  const file = path.join(work, 'httpd.conf');
  fs.writeFileSync(file, conf + '\n');
  run(httpd, ['-t', '-f', file]);
  return file;
}

async function startApache(confFile) {
  const child = spawn(httpd, ['-f', confFile], { windowsHide: true, stdio: 'ignore' });
  for (let attempt = 0; attempt < 60; attempt++) {
    try {
      await fetch(`${base}/`, { redirect: 'manual' });
      return child;
    } catch {
      await new Promise(resolve => setTimeout(resolve, 250));
    }
  }
  throw new Error('Apache did not start.');
}

function stopApache(child) {
  if (child?.pid) run('taskkill', ['/PID', String(child.pid), '/T', '/F'], { allowFailure: true });
}

async function request(route, options = {}) {
  const headers = new Headers(options.headers ?? {});
  if (options.cookie) headers.set('Cookie', options.cookie);
  const response = await fetch(new URL(route, base), { method: options.method ?? 'GET', headers, body: options.body, redirect: 'manual' });
  const body = options.binary ? Buffer.from(await response.arrayBuffer()) : await response.text();
  return { response, body, status: response.status };
}
const cookieOf = response => (response.headers.get('set-cookie') ?? '').split(';')[0];
const field = (body, name) => {
  const match = body.match(new RegExp(`name="${name}" value="([a-f0-9]+)"`));
  assert(match, `${name} was not present.`);
  return match[1];
};
async function tokens(route, cookie) {
  const page = await request(route, { cookie });
  assert.equal(page.status, 200, `${route} did not load.`);
  return { csrf_token: field(page.body, 'csrf_token'), submission_token: field(page.body, 'submission_token') };
}

function dbQuery(configFile, query) {
  const script = `$c = require getenv('CHEF_CONFIG_FILE'); $d = $c['database'];
    $p = new PDO($d['dsn'], $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo json_encode($p->query(${JSON.stringify(query)})->fetchAll(PDO::FETCH_ASSOC));`;
  return JSON.parse(run(php, ['-r', script], { env: { ...process.env, CHEF_CONFIG_FILE: configFile } }).stdout.trim());
}

async function check(variant, name, fn) {
  try {
    const detail = await fn();
    record(variant, name, 'PASS', detail ?? '');
  } catch (error) {
    record(variant, name, 'FAIL', error.message);
  }
}

async function installAndSmoke(variant, kind) {
  const work = fs.mkdtempSync(path.join(os.tmpdir(), `chef-release-${variant}-`));
  const home = path.join(work, 'home');
  const docroot = path.join(home, 'public_html');
  const privateRoot = path.join(home, 'chef-private');
  let db;
  let apache;
  try {
    // INSTALL_TR.md sections 4 and 5.
    extract(path.join(release, 'public_html.zip'), docroot);
    extract(path.join(release, 'chef-private-setup.zip'), privateRoot);
    for (const dir of ['logs', 'media', 'tmp/media', 'legacy-uploads']) fs.mkdirSync(path.join(privateRoot, dir), { recursive: true });
    db = await startDatabase(kind);
    record(variant, 'Disposable database', 'PASS', db.version);
    const configFile = writeConfig(home, db);
    const htaccess = path.join(docroot, '.htaccess');
    fs.writeFileSync(htaccess, `SetEnv CHEF_CONFIG_FILE ${fwd(configFile)}\n` + fs.readFileSync(htaccess, 'utf8'));
    const cliEnv = { ...process.env, CHEF_DB_CONFIG: configFile, CHEF_CONFIG_FILE: configFile, DOCUMENT_ROOT: docroot };
    const cli = (script, args, allowFailure = false) => run(php, ['-d', 'variables_order=EGPCS', path.join(privateRoot, 'database', script), ...args], { cwd: privateRoot, env: cliEnv, allowFailure });
    const ownerUser = 'owner';
    const ownerPassword = random(16);

    if (kind === 'mysql') {
      // INSTALL_TR.md section 6A.
      await check(variant, 'CLI migrate', () => cli('cli.php', ['migrate']).stdout.trim());
      await check(variant, 'CLI seed', () => cli('cli.php', ['seed']).stdout.trim());
      await check(variant, 'CLI verify', () => cli('cli.php', ['verify']).stdout.trim());
      await check(variant, 'Media prepare', () => JSON.parse(cli('media-cli.php', ['prepare']).stdout).status);
      const ownerFile = path.join(privateRoot, 'owner.json');
      fs.writeFileSync(ownerFile, JSON.stringify({ username: ownerUser, password: ownerPassword }));
      restrictAcl(ownerFile);
      await check(variant, 'Owner create (--create)', () => {
        const out = cli('recover-owner.php', ['--create', `--file=${ownerFile}`]).stdout.trim();
        assert(!fs.existsSync(ownerFile), 'The consumed owner file remains.');
        return out;
      });
      await check(variant, 'Second --create refused', () => {
        const again = path.join(privateRoot, 'owner2.json');
        fs.writeFileSync(again, JSON.stringify({ username: 'second', password: random(16) }));
        restrictAcl(again);
        const result = cli('recover-owner.php', ['--create', `--file=${again}`], true);
        assert.equal(result.status, 1, 'A second owner was created.');
        assert(result.stderr.includes('An administrator already exists'), 'Refusal message is missing.');
        assert.equal(dbQuery(configFile, 'SELECT COUNT(*) AS n FROM admins')[0].n, 1);
        return 'exit 1';
      });
    } else {
      // INSTALL_TR.md section 6B. The mariadb client import stands in for phpMyAdmin.
      const sqlFile = path.join(privateRoot, 'sql', `chef-install-${schemaVersion}.sql`);
      await check(variant, 'SQL import into empty database', () => {
        const result = db.importSql(sqlFile);
        assert.equal(result.status, 0, result.stderr);
        return path.basename(sqlFile);
      });
      await check(variant, 'SQL import refused on populated database', () => {
        const result = db.importSql(sqlFile);
        assert.notEqual(result.status, 0, 'Second import succeeded.');
        assert(/already exists/i.test(result.stderr), result.stderr);
        return 'table exists error';
      });
      await check(variant, 'CLI verify after SQL import', () => cli('cli.php', ['verify']).stdout.trim());
      await check(variant, 'Migration checksums match SQL', () => {
        const out = cli('cli.php', ['migrate']).stdout.trim();
        assert.equal(out, 'No pending migrations.');
        return out;
      });
      await check(variant, 'Manual owner insert', () => {
        const hash = run(php, ['-r', 'echo password_hash(getenv("P"), PASSWORD_DEFAULT);'], { env: { ...process.env, P: ownerPassword } }).stdout.trim();
        const statement = `INSERT INTO admins (username, password_hash, auth_version) VALUES ('${ownerUser}', '${hash}', 1);`;
        const result = run('docker', ['exec', '-i', '-e', `MYSQL_PWD=${db.password}`, db.name, 'mariadb', '-h127.0.0.1', `-u${db.user}`, db.database, '-e', statement], { allowFailure: true });
        assert.equal(result.status, 0, result.stderr);
        return 'admins 1 row';
      });
      await check(variant, 'Manual media directories', () => {
        for (const dir of ['media/products', 'media/categories', 'media/campaigns', 'media/.pending']) fs.mkdirSync(path.join(docroot, dir), { recursive: true });
        for (const dir of ['media/feedback', 'media/.pending']) fs.mkdirSync(path.join(privateRoot, dir), { recursive: true });
        return JSON.parse(cli('media-cli.php', ['status']).stdout).status;
      });
    }

    await check(variant, 'Seed counts', () => {
      const [row] = dbQuery(configFile, 'SELECT (SELECT COUNT(*) FROM categories) AS c, (SELECT COUNT(*) FROM products) AS p, (SELECT COUNT(*) FROM feedbacks) AS f');
      assert.deepEqual([Number(row.c), Number(row.p), Number(row.f)], [4, 24, 0]);
      return '4 categories, 24 products, 0 feedback';
    });

    apache = await startApache(writeApacheConf(work, docroot));

    // Guest menu.
    await check(variant, 'Guest index', async () => {
      const r = await request('/');
      assert.equal(r.status, 200);
      assert.equal(crypto.createHash('sha256').update(r.body).digest('hex'), expectedGuestHash);
      assert(r.response.headers.get('content-security-policy')?.includes("frame-ancestors 'none'"), 'Guest CSP missing.');
      assert.equal(r.response.headers.get('x-frame-options'), 'DENY');
      return 'rev1 hash, CSP';
    });
    let menu;
    await check(variant, 'Guest menu API', async () => {
      const r = await request('/hvk/admin/api/menu.php');
      assert.equal(r.status, 200);
      menu = JSON.parse(r.body);
      assert.equal(menu.categories.length, 4);
      assert.equal(menu.items.length, 24);
      return `version ${menu.version}`;
    });

    // Admin login.
    let session;
    await check(variant, 'Admin login', async () => {
      const page = await request('/hvk/admin/login.php');
      assert.equal(page.status, 200);
      assert(page.response.headers.get('strict-transport-security'), 'HSTS missing on HTTPS.');
      const form = new URLSearchParams({ csrf_token: field(page.body, 'csrf_token'), submission_token: field(page.body, 'submission_token'), username: ownerUser, password: ownerPassword });
      const r = await request('/hvk/admin/login.php', { method: 'POST', cookie: cookieOf(page.response), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
      assert.equal(r.status, 302);
      const raw = r.response.headers.get('set-cookie') ?? '';
      assert(/;\s*secure/i.test(raw) && /;\s*httponly/i.test(raw) && /path=\/hvk\/admin/i.test(raw), 'Session cookie flags are wrong.');
      session = cookieOf(r.response);
      const dashboard = await request('/hvk/admin/index.php', { cookie: session });
      assert.equal(dashboard.status, 200);
      assert.equal(dashboard.response.headers.get('cache-control'), 'no-store, private, max-age=0');
      return 'Secure, HttpOnly, no-store';
    });

    // One catalog edit through bulk prices.
    await check(variant, 'Catalog price edit', async () => {
      const [product] = dbQuery(configFile, 'SELECT id, public_id FROM products ORDER BY id LIMIT 1');
      const pair = await tokens('/hvk/admin/bulk-prices.php', session);
      const form = new URLSearchParams({ ...pair, action: 'bulk_update_prices', [`price[${product.id}]`]: '321,50' });
      const r = await request('/hvk/admin/bulk-prices.php', { method: 'POST', cookie: session, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
      assert([302, 303].includes(r.status), `status ${r.status}`);
      const after = JSON.parse((await request('/hvk/admin/api/menu.php')).body);
      const item = after.items.find(i => i.id === (product.public_id ?? String(product.id)));
      assert.equal(item.price, 321.5);
      assert(after.version > menu.version, 'Menu version did not change.');
      return `price 321.5, version ${menu.version} -> ${after.version}`;
    });

    // Public media: campaign image upload and static delivery.
    const png = path.join(work, 'fixture.png');
    run(php, ['-r', `$i = imagecreatetruecolor(64, 64); imagefill($i, 0, 0, imagecolorallocate($i, 35, 126, 48)); imagepng($i, ${JSON.stringify(png)});`]);
    await check(variant, 'Public media upload and delivery', async () => {
      const pair = await tokens('/hvk/admin/campaign.php', session);
      const form = new FormData();
      for (const [k, v] of Object.entries({ ...pair, action: 'save', is_active: '1' })) form.set(k, v);
      form.set('image', new Blob([fs.readFileSync(png)], { type: 'image/png' }), 'campaign.png');
      const r = await request('/hvk/admin/campaign.php', { method: 'POST', cookie: session, body: form });
      assert([302, 303].includes(r.status), `status ${r.status}`);
      const current = JSON.parse((await request('/hvk/admin/api/menu.php')).body);
      assert(current.campaign?.image?.startsWith('/media/campaigns/'), 'Campaign image URL missing.');
      const image = await request(current.campaign.image, { binary: true });
      assert.equal(image.status, 200);
      assert.equal(image.response.headers.get('content-type'), 'image/webp');
      return current.campaign.image.replace(/[^/]+$/, '<file>.webp');
    });

    // Feedback with image, then physical deletion.
    const feedbackDir = path.join(privateRoot, 'media', 'feedback');
    let feedbackId;
    let uploadId;
    await check(variant, 'Feedback with image', async () => {
      const form = new FormData();
      form.set('topic', 'hygiene');
      form.set('rating', '5');
      form.set('message', 'Fresh install smoke test');
      form.set('language', 'tr');
      form.set('image', new Blob([fs.readFileSync(png)], { type: 'image/png' }), 'feedback.png');
      const r = await request('/hvk/admin/api/feedback.php', { method: 'POST', body: form, headers: { 'Idempotency-Key': crypto.randomUUID() } });
      assert.equal(r.status, 201, r.body);
      const [row] = dbQuery(configFile, 'SELECT id, image_path FROM feedbacks ORDER BY id DESC LIMIT 1');
      feedbackId = Number(row.id);
      uploadId = Number(String(row.image_path).replace('managed:', ''));
      const files = listFiles(feedbackDir).filter(f => !f.includes(`${path.sep}.pending`));
      assert(files.length >= 1, 'No private feedback file exists.');
      assert.equal(listFiles(path.join(docroot, 'media')).filter(f => f.includes('feedback')).length, 0, 'Feedback file is under the public root.');
      return `${files.length} private file(s)`;
    });
    await check(variant, 'Private image access control', async () => {
      const anonymous = await request(`/hvk/admin/private-image.php?id=${uploadId}`);
      assert([302, 303].includes(anonymous.status), `anonymous ${anonymous.status}`);
      const owner = await request(`/hvk/admin/private-image.php?id=${uploadId}`, { cookie: session, binary: true });
      assert.equal(owner.status, 200);
      assert.equal(owner.response.headers.get('content-type'), 'image/webp');
      return `anonymous ${anonymous.status}, owner 200`;
    });
    await check(variant, 'Feedback deletion removes files', async () => {
      const pair = await tokens('/hvk/admin/feedbacks.php', session);
      const form = new URLSearchParams({ ...pair, id: String(feedbackId) });
      const r = await request('/hvk/admin/feedbacks.php', { method: 'POST', cookie: session, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
      assert.equal(r.status, 303);
      assert.equal(dbQuery(configFile, `SELECT COUNT(*) AS n FROM feedbacks WHERE id = ${feedbackId}`)[0].n, 0);
      const remaining = listFiles(feedbackDir).filter(f => !f.includes(`${path.sep}.pending`));
      assert.equal(remaining.length, 0, `${remaining.length} file(s) remain.`);
      const gone = await request(`/hvk/admin/private-image.php?id=${uploadId}`, { cookie: session });
      assert.equal(gone.status, 404);
      return '0 files, image 404';
    });

    // Denied access through HTTP.
    await check(variant, 'Denied private paths', async () => {
      const expectations = {
        '/database/lib.php': [404],
        '/database/': [404],
        '/hvk/admin/config.php': [403],
        '/hvk/admin/security.php': [403],
        '/hvk/admin/functions.php': [403],
        '/hvk/admin/media-service.php': [403],
        '/hvk/admin/.htaccess': [403],
        '/.htaccess': [403],
        '/media/': [403],
        '/media/.htaccess': [403],
        '/hvk/admin/assets/': [403, 404],
        '/..%2fchef-private/config.php': [400, 403, 404],
        '/chef-private/config.php': [404],
      };
      const seen = [];
      for (const [route, allowed] of Object.entries(expectations)) {
        const r = await request(route);
        assert(allowed.includes(r.status), `${route} returned ${r.status}`);
        assert(!r.body.includes('<?php') && !r.body.includes('DATABASE_PASSWORD') && !r.body.includes(db.password), `${route} leaked content.`);
        seen.push(`${route}=${r.status}`);
      }
      return `${seen.length} paths`;
    });
    await check(variant, 'Upload execution denied', async () => {
      const probes = ['probe.php', 'probe.phtml', 'probe.phar', 'image.php.webp'];
      const out = [];
      for (const name of probes) {
        const file = path.join(docroot, 'media', 'campaigns', name);
        fs.writeFileSync(file, "<?php echo 'EXECUTED';");
        const r = await request(`/media/campaigns/${name}`);
        fs.rmSync(file);
        assert.equal(r.status, 403, `${name} returned ${r.status}`);
        assert(!r.body.includes('EXECUTED'), `${name} executed.`);
        out.push(`${name}=403`);
      }
      return out.join(', ');
    });
    await check(variant, 'Logout ends session', async () => {
      const pair = await tokens('/hvk/admin/index.php', session);
      const r = await request('/hvk/admin/logout.php', { method: 'POST', cookie: session, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(pair) });
      assert.equal(r.status, 303);
      const after = await request('/hvk/admin/products.php', { cookie: session });
      assert([302, 303].includes(after.status), `products ${after.status}`);
      return 'redirect to login';
    });
    await check(variant, 'Private log outside public root', () => {
      const log = path.join(privateRoot, 'logs', 'app.log');
      assert(fs.existsSync(log), 'Log file missing.');
      const text = fs.readFileSync(log, 'utf8');
      assert(!text.includes(db.password) && !text.includes(ownerPassword), 'Log contains a secret.');
      assert.equal(listFiles(docroot).filter(f => f.endsWith('.log')).length, 0);
      return 'no secret in log';
    });
  } finally {
    stopApache(apache);
    db?.stop();
    const resolved = path.resolve(work);
    if (process.env.CHEF_RELEASE_KEEP === '1') console.log(`Kept work directory: ${resolved}`);
    else if (path.dirname(resolved) === path.resolve(os.tmpdir()) && path.basename(resolved).startsWith(`chef-release-${variant}-`)) {
      fs.rmSync(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
  }
}

const archives = ['public_html.zip', 'chef-private-setup.zip'].map(name => ({ name, sha256: sha256(path.join(release, name)) }));
for (const archive of archives) console.log(`${archive.name} ${archive.sha256}`);
const variantArg = process.argv[2];
try {
  if (!variantArg || variantArg === 'A') await installAndSmoke('A', 'mysql');
  if (!variantArg || variantArg === 'B') await installAndSmoke('B', 'mariadb');
} catch (error) {
  record('-', 'Harness', 'FAIL', error.message);
}
const output = path.join(release, 'build', 'fresh-install-results.json');
fs.mkdirSync(path.dirname(output), { recursive: true });
fs.writeFileSync(output, JSON.stringify({ date: new Date().toISOString(), archives, results }, null, 2) + '\n');
const failed = results.filter(r => r.status !== 'PASS');
console.log(`\n${results.length - failed.length}/${results.length} PASS`);
process.exit(failed.length ? 1 : 0);
