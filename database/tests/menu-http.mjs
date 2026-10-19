import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const base = new URL(process.env.CHEF_HTTP_BASE);
const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const credentials = JSON.parse(fs.readFileSync(process.env.CHEF_TEST_CREDENTIALS, 'utf8'));
const fixture = path.join(process.env.CHEF_MEDIA_FIXTURES, 'valid.png');

async function request(route, options = {}) {
  const headers = new Headers(options.headers ?? {});
  if (options.cookie) headers.set('Cookie', options.cookie);
  const response = await fetch(new URL(route, base), {
    method: options.method ?? 'GET',
    headers,
    body: options.body,
    redirect: 'manual',
  });
  return { response, body: await response.text() };
}

function tokens(html) {
  const csrf = html.match(/name="csrf_token" value="([a-f0-9]+)"/)?.[1];
  const submission = html.match(/name="submission_token" value="([a-f0-9]+)"/)?.[1];
  assert(csrf && submission, 'Admin form tokens are missing.');
  return { csrf_token: csrf, submission_token: submission };
}

function cookie(response) {
  return (response.headers.get('set-cookie') ?? '').split(';')[0];
}

function check(name) {
  const result = spawnSync(process.env.CHEF_PHP_EXE, [path.join(repo, 'database/tests/readonly-check.php'), name], {
    cwd: repo,
    env: process.env,
    encoding: 'utf8',
  });
  assert.equal(result.status, 0, `Readonly check failed: ${name}`);
  return JSON.parse(result.stdout).result;
}

async function formPage(route, sessionCookie) {
  const page = await request(route, { cookie: sessionCookie });
  assert.equal(page.response.status, 200, `Admin page failed: ${route}`);
  return { body: page.body, tokens: tokens(page.body) };
}

async function postUrlEncoded(route, sessionCookie, fields) {
  return request(route, {
    method: 'POST',
    cookie: sessionCookie,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(fields),
  });
}

async function readMenu(etag = null) {
  const headers = { Accept: 'application/json' };
  if (etag) headers['If-None-Match'] = etag;
  return request('/hvk/admin/api/menu.php', { headers });
}

const initial = await readMenu();
assert.equal(initial.response.status, 200, 'Menu API did not return HTTP 200.');
assert.match(initial.response.headers.get('content-type') ?? '', /^application\/json\b/);
assert.match(initial.response.headers.get('cache-control') ?? '', /public/);
assert.match(initial.response.headers.get('cache-control') ?? '', /no-cache/);
assert.equal(initial.response.headers.get('set-cookie'), null, 'Public menu created a session cookie.');
const firstEtag = initial.response.headers.get('etag');
assert(firstEtag, 'Menu ETag is missing.');
const firstMenu = JSON.parse(initial.body);
assert.equal(firstMenu.currency, 'TRY');
assert.equal(firstMenu.categories.length, 4, 'Seed category count changed.');
assert.equal(firstMenu.items.length, 24, 'Seed product count changed.');
assert.equal(firstMenu.campaign, null, 'The campaign test requires the disposable seed state.');
assert(!('products' in firstMenu) && !('branches' in firstMenu), 'Legacy or private response fields remain.');
assert(firstMenu.items.every(item => !('categoryId' in item) && !('createdAt' in item)), 'Internal item fields remain.');

const cached = await readMenu(firstEtag);
assert.equal(cached.response.status, 304, 'Matching ETag did not return HTTP 304.');
assert.equal(cached.body, '', 'HTTP 304 returned a response body.');

const loginPage = await request('/hvk/admin/login.php');
const loginCookie = cookie(loginPage.response);
const login = await postUrlEncoded('/hvk/admin/login.php', loginCookie, {
  ...tokens(loginPage.body),
  username: credentials.admin_username,
  password: credentials.admin_password,
});
assert.equal(login.response.status, 302, 'Admin login failed.');
const sessionCookie = cookie(login.response) || loginCookie;

const seedProduct = check('product-state').find(row => row.public_id === firstMenu.items[0].id);
assert(seedProduct, 'Seed product was not found for integration checks.');
const categories = check('category-order');
const originalCategory = categories.find(row => Number(row.id) === Number(seedProduct.category_id));
const targetCategory = categories.find(row => Number(row.id) !== Number(seedProduct.category_id));
assert(originalCategory && targetCategory, 'Category move fixtures are missing.');

const productPage = await formPage(`/hvk/admin/products.php?category=${seedProduct.category_id}&edit=${seedProduct.id}`, sessionCookie);
const productFields = {
  ...productPage.tokens,
  action: 'update', id: String(seedProduct.id), redirect_category: String(seedProduct.category_id),
  category_id: String(seedProduct.category_id), name: seedProduct.name,
  name_en: '', name_ar: '', description: seedProduct.description ?? '',
  description_en: '', description_ar: '', price: seedProduct.price,
  ingredients: seedProduct.ingredients ?? '', ingredients_en: seedProduct.ingredients_en ?? '',
  ingredients_ar: seedProduct.ingredients_ar ?? '', calories: seedProduct.calories ?? '',
};
if (seedProduct.ingredients_verified_at) productFields.ingredients_verified = '1';
if (seedProduct.calories_verified_at) productFields.calories_verified = '1';
const productUpdate = await postUrlEncoded('/hvk/admin/products.php', sessionCookie, productFields);
assert([302, 303].includes(productUpdate.response.status), 'Translation edit failed.');
const afterTranslation = await readMenu(firstEtag);
assert.equal(afterTranslation.response.status, 200, 'Translation edit did not invalidate the cached menu.');
const translationEtag = afterTranslation.response.headers.get('etag');
const translatedItem = JSON.parse(afterTranslation.body).items.find(item => item.id === seedProduct.public_id);
assert.deepEqual(translatedItem.name, { tr: seedProduct.name, en: null, ar: null }, 'Editable translations or Turkish fallback contract regressed.');

const movePage = await formPage(`/hvk/admin/products.php?category=${seedProduct.category_id}`, sessionCookie);
const moveResult = await postUrlEncoded('/hvk/admin/products.php', sessionCookie, {
  ...movePage.tokens, action: 'move_category', id: String(seedProduct.id),
  new_category_id: String(targetCategory.id), redirect_category: String(seedProduct.category_id),
});
assert([302, 303].includes(moveResult.response.status), 'Category move failed.');
const afterMove = await readMenu(translationEtag);
assert.equal(afterMove.response.status, 200, 'Category move did not invalidate the cached menu.');
const moveEtag = afterMove.response.headers.get('etag');
assert.equal(JSON.parse(afterMove.body).items.find(item => item.id === seedProduct.public_id)?.category, targetCategory.public_id, 'Category move is absent from the public menu.');

const pricePage = await formPage('/hvk/admin/bulk-prices.php', sessionCookie);
const priceForm = new FormData();
Object.entries({ ...pricePage.tokens, action: 'import_csv_prices' }).forEach(([key, value]) => priceForm.set(key, value));
priceForm.set('csv_file', new Blob([`ID,Urun_Adi,Fiyat\n${seedProduct.id},${seedProduct.name},"123,45"\n`]), 'prices.csv');
const priceUpdate = await request('/hvk/admin/bulk-prices.php', { method: 'POST', cookie: sessionCookie, body: priceForm });
assert([302, 303].includes(priceUpdate.response.status), 'Price CSV import failed.');
const afterPrice = await readMenu(moveEtag);
assert.equal(afterPrice.response.status, 200, 'Price import did not invalidate the cached menu.');
const priceEtag = afterPrice.response.headers.get('etag');
assert.equal(JSON.parse(afterPrice.body).items.find(item => item.id === seedProduct.public_id)?.price, 123.45, 'Imported price is absent from the public menu.');

const importPage = await formPage('/hvk/admin/import-products.php', sessionCookie);
const productImport = new FormData();
Object.entries(importPage.tokens).forEach(([key, value]) => productImport.set(key, value));
productImport.set('file', new Blob([JSON.stringify([{
  category_name: targetCategory.name, product_name: 'Task07 API Import', description: 'Fallback test',
  price: '67.80', image_url: '', sort_order: '',
}])]), 'products.json');
const importResult = await request('/hvk/admin/import-products.php', { method: 'POST', cookie: sessionCookie, body: productImport });
assert([302, 303].includes(importResult.response.status), 'Product JSON import failed.');
const afterImport = await readMenu(priceEtag);
assert.equal(afterImport.response.status, 200, 'Product import did not invalidate the cached menu.');
const importEtag = afterImport.response.headers.get('etag');
const importedItem = JSON.parse(afterImport.body).items.find(item => item.name.tr === 'Task07 API Import');
assert(importedItem && importedItem.name.en === null && importedItem.description.en === null, 'Imported product fallback data is invalid.');

const branchPage = await formPage('/hvk/admin/branches.php', sessionCookie);
const branchUpdate = await postUrlEncoded('/hvk/admin/branches.php', sessionCookie, {
  ...branchPage.tokens,
  wifi_name: 'Chef Part A Test',
  wifi_password: 'part-a-only',
});
assert([302, 303].includes(branchUpdate.response.status), 'WiFi update failed.');
const afterWifi = await readMenu(importEtag);
assert.equal(afterWifi.response.status, 200, 'WiFi update did not invalidate the cached menu.');
const wifiEtag = afterWifi.response.headers.get('etag');
assert.notEqual(wifiEtag, importEtag, 'WiFi update did not change the ETag.');
assert.deepEqual(JSON.parse(afterWifi.body).wifi, { network: 'Chef Part A Test', password: 'part-a-only' });

const campaignPage = await formPage('/hvk/admin/campaign.php', sessionCookie);
const campaignForm = new FormData();
Object.entries({ ...campaignPage.tokens, action: 'save', is_active: '1' }).forEach(([key, value]) => campaignForm.set(key, value));
campaignForm.set('image', new Blob([fs.readFileSync(fixture)], { type: 'image/png' }), 'campaign.png');
const campaignUpdate = await request('/hvk/admin/campaign.php', {
  method: 'POST',
  cookie: sessionCookie,
  body: campaignForm,
});
assert([302, 303].includes(campaignUpdate.response.status), 'Campaign update failed.');
const afterCampaign = await readMenu(wifiEtag);
assert.equal(afterCampaign.response.status, 200, 'Campaign update did not invalidate the cached menu.');
const campaign = JSON.parse(afterCampaign.body).campaign;
assert.equal(campaign?.enabled, true, 'Enabled campaign is missing.');
assert.match(campaign?.image ?? '', /^\/media\/.+\.webp$/);
assert.deepEqual(campaign.text, { tr: null, en: null, ar: null });

const cleanupCampaignPage = await formPage('/hvk/admin/campaign.php', sessionCookie);
const campaignCleanup = await postUrlEncoded('/hvk/admin/campaign.php', sessionCookie, {
  ...cleanupCampaignPage.tokens,
  action: 'delete_image',
});
assert([302, 303].includes(campaignCleanup.response.status), 'Campaign cleanup failed.');
const restoreBranchPage = await formPage('/hvk/admin/branches.php', sessionCookie);
const wifiCleanup = await postUrlEncoded('/hvk/admin/branches.php', sessionCookie, {
  ...restoreBranchPage.tokens,
  wifi_name: firstMenu.wifi?.network ?? '',
  wifi_password: firstMenu.wifi?.password ?? '',
});
assert([302, 303].includes(wifiCleanup.response.status), 'WiFi cleanup failed.');
const restored = JSON.parse((await readMenu()).body);
assert.deepEqual(restored.wifi, firstMenu.wifi, 'WiFi state was not restored.');
assert.equal(restored.campaign, null, 'Campaign state was not restored.');

console.log(JSON.stringify({
  status: 'PASS',
  checks: ['public shape', '24 products', 'cache headers', 'ETag 304', 'translation fallback', 'category move invalidation', 'price invalidation', 'product import invalidation', 'WiFi invalidation', 'campaign invalidation'],
}));
