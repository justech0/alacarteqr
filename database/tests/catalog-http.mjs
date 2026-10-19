import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const base = new URL(process.env.CHEF_HTTP_BASE);
const php = process.env.CHEF_PHP_EXE;
const configFile = process.env.CHEF_CONFIG_FILE;
const credentialsFile = process.env.CHEF_TEST_CREDENTIALS;
const fixtureRoot = process.env.CHEF_MEDIA_FIXTURES;
const credentials = JSON.parse(fs.readFileSync(credentialsFile, 'utf8'));
const repo = path.resolve(import.meta.dirname, '..', '..');
const renderedOnly = process.argv.includes('--rendered-only');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function request(route, options = {}) {
  const headers = new Headers(options.headers ?? {});
  if (options.cookie) headers.set('Cookie', options.cookie);
  let body = options.body;
  if (options.form) {
    body = new URLSearchParams();
    for (const [key, value] of Object.entries(options.form)) {
      if (Array.isArray(value)) value.forEach(item => body.append(key, String(item)));
      else body.set(key, String(value));
    }
    headers.set('Content-Type', 'application/x-www-form-urlencoded');
  }
  const response = await fetch(new URL(route, base), {
    method: options.method ?? 'GET', headers, body, redirect: 'manual',
  });
  return { response, body: await response.text() };
}

function cookie(response) {
  return (response.headers.get('set-cookie') ?? '').split(';')[0];
}

function tokens(body) {
  const csrf = body.match(/name="csrf_token" value="([a-f0-9]+)"/)?.[1];
  const submission = body.match(/name="submission_token" value="([a-f0-9]+)"/)?.[1];
  assert(csrf && submission, 'Form tokens were not present.');
  return { csrf_token: csrf, submission_token: submission };
}

function check(name) {
  const result = spawnSync(php, [path.join(repo, 'database/tests/readonly-check.php'), name], {
    cwd: repo,
    env: { ...process.env, CHEF_CONFIG_FILE: configFile, CHEF_TEST_CREDENTIALS: credentialsFile },
    encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`Readonly check failed: ${name}`);
  return JSON.parse(result.stdout).result;
}

async function page(route, sessionCookie) {
  const result = await request(route, { cookie: sessionCookie });
  assert(result.response.status === 200, `Page failed: ${route}`);
  return result;
}

async function tokenPair(route, sessionCookie) {
  return tokens((await page(route, sessionCookie)).body);
}

async function post(route, sessionCookie, form) {
  return request(route, { method: 'POST', cookie: sessionCookie, form });
}

async function postFile(route, sessionCookie, fields, fieldName, fixtureName) {
  const form = new FormData();
  for (const [key, value] of Object.entries(fields)) form.set(key, String(value));
  if (fixtureName) form.set(fieldName, new Blob([fs.readFileSync(path.join(fixtureRoot, fixtureName))]), fixtureName);
  return request(route, { method: 'POST', cookie: sessionCookie, body: form });
}

function product(name) {
  return check('product-state').find(row => row.name === name);
}

function category(name) {
  return check('category-order').find(row => row.name === name);
}

function assertLabeledControls(html, route) {
  const controls = [...html.matchAll(/<(input|select|textarea)\b[^>]*>/gi)];
  for (const match of controls) {
    const tag = match[0];
    if (/type="(?:hidden|submit|button)"/i.test(tag)) continue;
    const id = tag.match(/\bid="([^"]+)"/i)?.[1];
    if (id && new RegExp(`<label\\b[^>]*for="${id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"`, 'i').test(html)) continue;
    const before = html.slice(0, match.index);
    const after = html.slice(match.index + tag.length);
    const wrapped = before.lastIndexOf('<label') > before.lastIndexOf('</label>') && after.indexOf('</label>') >= 0;
    assert(wrapped, `Unlabeled control on ${route}: ${tag}`);
  }
}

const loginPage = await request('/hvk/admin/login.php');
assert(loginPage.response.status === 200, 'Login page did not return HTTP 200.');
assert(!/(?:src|href)="https?:\/\//i.test(loginPage.body), 'External browser resource found: login.php');
assert(!/\b(?:confirm|alert|prompt)\s*\(/.test(loginPage.body), 'Native dialog call found: login.php');
assert(loginPage.body.includes('data-testid="login-form"'), 'Stable login hooks are missing.');
assertLabeledControls(loginPage.body, 'login.php');
const loginCookie = cookie(loginPage.response);
const loginResult = await post('/hvk/admin/login.php', loginCookie, {
  ...tokens(loginPage.body), username: credentials.admin_username, password: credentials.admin_password,
});
assert(loginResult.response.status === 302, 'Catalog administrator login failed.');
const sessionCookie = cookie(loginResult.response) || loginCookie;

const adminPages = [
  'index.php', 'categories.php', 'category-order.php', 'products.php', 'product-order.php',
  'bulk-prices.php', 'import-products.php', 'branches.php', 'campaign.php', 'feedbacks.php',
];
for (const route of adminPages) {
  const html = (await page(`/hvk/admin/${route}`, sessionCookie)).body;
  assert(!/(?:src|href)="https?:\/\//i.test(html), `External browser resource found: ${route}`);
  assert(!/\b(?:confirm|alert|prompt)\s*\(/.test(html), `Native dialog call found: ${route}`);
  assert(html.includes('data-testid='), `Stable hooks are missing: ${route}`);
  assert(html.includes('data-testid="confirm-dialog"'), `Confirmation dialog hook is missing: ${route}`);
  assertLabeledControls(html, route);
}

const firstCategory = check('category-order')[0];
assert(firstCategory, 'Seed categories are missing.');
if (renderedOnly) {
  const preservedName = 'Task05 Preserved Validation Value';
  const preservedPrice = '1e2';
  const productCountBefore = Number(check('row-counts').products);
  const pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
  const result = await post('/hvk/admin/products.php', sessionCookie, {
    ...pair,
    action: 'create',
    name: preservedName,
    description: 'Validation state check',
    price: preservedPrice,
    category_id: String(firstCategory.id),
    ingredients: '',
    ingredients_en: '',
    ingredients_ar: '',
    calories: '',
  });
  assert(result.response.status === 303, 'Invalid product validation did not return HTTP 303.');
  assert(result.response.headers.get('location') === 'products.php', 'Invalid product redirect location is invalid.');
  assert(Number(check('row-counts').products) === productCountBefore, 'Invalid product validation changed the database.');
  const validationPage = await page('/hvk/admin/products.php', sessionCookie);
  assert(validationPage.body.includes(`value="${preservedName}"`), 'Product name did not survive validation.');
  assert(validationPage.body.includes(`value="${preservedPrice}"`), 'Product price did not survive validation.');
  assert(validationPage.body.includes('role="alert"'), 'Validation error message is missing.');
  console.log(JSON.stringify({
    status: 'PASS',
    mode: 'rendered-only',
    pages: adminPages.length + 1,
    redirect: { status: 303, location: 'products.php' },
    validationState: 'preserved',
    databaseChanged: false,
  }, null, 2));
  process.exit(0);
}
const emptyName = 'Task05 Empty Nutrition';
let pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
const createForm = {
  ...pair, action: 'create', name: emptyName, description: 'Servis açıklaması', price: '12.34',
  category_id: String(firstCategory.id), ingredients: '', ingredients_en: '', ingredients_ar: '', calories: '',
};
let result = await post('/hvk/admin/products.php', sessionCookie, createForm);
assert([302, 303].includes(result.response.status), 'Product creation failed.');
result = await post('/hvk/admin/products.php', sessionCookie, createForm);
assert([302, 303].includes(result.response.status), 'Duplicate submission lacked a controlled response.');
let created = product(emptyName);
assert(created && check('product-state').filter(row => row.name === emptyName).length === 1, 'Duplicate submission created more than one product.');
assert(created.ingredients === null && created.calories === null && created.ingredients_verified_at === null && created.calories_verified_at === null, 'Empty nutrition state is invalid.');

pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await post('/hvk/admin/products.php', sessionCookie, {
  ...pair, action: 'create', name: 'Task05 Invalid Price', price: '1e2', category_id: String(firstCategory.id),
});
assert(!product('Task05 Invalid Price'), 'Invalid price created a product.');
const invalidPage = await page('/hvk/admin/products.php', sessionCookie);
assert(invalidPage.body.includes('Task05 Invalid Price') && invalidPage.body.includes('role="alert"'), 'Validation did not preserve safe product values.');

pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await post('/hvk/admin/products.php', sessionCookie, {
  ...pair, action: 'create', name: 'Task05 Missing Category', price: '10.00', category_id: '99999999',
});
assert(!product('Task05 Missing Category'), 'Missing category created a product.');

const beforeMalformed = created.category_id;
pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await post('/hvk/admin/products.php', sessionCookie, {
  ...pair, action: 'move_category', id: '01', new_category_id: String(firstCategory.id),
});
assert(product(emptyName).category_id === beforeMalformed, 'Malformed product ID changed data.');

async function updateProduct(fields, fixture = null) {
  const current = product(emptyName);
  const route = `/hvk/admin/products.php?edit=${current.id}`;
  const currentTokens = await tokenPair(route, sessionCookie);
  const baseFields = {
    ...currentTokens, action: 'update', id: current.id, category_id: current.category_id,
    name: emptyName, description: 'Servis açıklaması', price: '12.34',
    ingredients: '', ingredients_en: '', ingredients_ar: '', calories: '',
    ...fields,
  };
  return fixture
    ? postFile('/hvk/admin/products.php', sessionCookie, baseFields, 'image', fixture)
    : post('/hvk/admin/products.php', sessionCookie, baseFields);
}

await updateProduct({ ingredients: 'Su, tuz', calories: '0', ingredients_verified: '1', calories_verified: '1' });
created = product(emptyName);
assert(created.ingredients_verified_at && created.calories_verified_at && Number(created.calories) === 0, 'Verified nutrition state is invalid.');
const ingredientStamp = created.ingredients_verified_at;
const calorieStamp = created.calories_verified_at;
await updateProduct({ ingredients: 'Su, tuz', calories: '0', ingredients_verified: '1', calories_verified: '1' });
created = product(emptyName);
assert(created.ingredients_verified_at === ingredientStamp && created.calories_verified_at === calorieStamp, 'Unchanged verified values refreshed timestamps.');
await new Promise(resolve => setTimeout(resolve, 1100));
await updateProduct({ ingredients: 'Su, tuz, kekik', calories: '0', ingredients_verified: '1', calories_verified: '1' });
created = product(emptyName);
assert(created.ingredients_verified_at !== ingredientStamp && created.calories_verified_at === calorieStamp, 'Changed nutrition timestamps are invalid.');
await updateProduct({ ingredients: 'Su, tuz, kekik', calories: '0' });
created = product(emptyName);
assert(created.ingredients_verified_at === null && created.calories_verified_at === null, 'Unverified timestamps were not cleared.');

await updateProduct({}, 'valid.png');
const activeImage = product(emptyName).image_path;
assert(activeImage, 'Valid product image was not stored.');
await updateProduct({}, 'malformed.png');
assert(product(emptyName).image_path === activeImage, 'Failed image replacement changed the active image.');

const deleteCategoryName = 'Task05 Delete Category';
pair = await tokenPair('/hvk/admin/categories.php', sessionCookie);
await postFile('/hvk/admin/categories.php', sessionCookie, {
  ...pair, action: 'create', name: deleteCategoryName, description: 'Silme deneyi',
}, 'image', 'valid.png');
const deleteCategory = category(deleteCategoryName);
assert(deleteCategory, 'Cleanup category was not created.');
pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await postFile('/hvk/admin/products.php', sessionCookie, {
  ...pair, action: 'create', name: 'Task05 Delete Product', description: '', price: '25.00',
  category_id: deleteCategory.id, ingredients: '', ingredients_en: '', ingredients_ar: '', calories: '',
}, 'image', 'valid.jpg');
const deleteProduct = product('Task05 Delete Product');
assert(deleteProduct, 'Cleanup product was not created.');
pair = await tokenPair('/hvk/admin/categories.php', sessionCookie);
await post('/hvk/admin/categories.php', sessionCookie, { ...pair, action: 'delete', id: deleteCategory.id, delete_dependents: '' });
assert(category(deleteCategoryName) && product('Task05 Delete Product'), 'Category deletion ignored the dependent confirmation contract.');
pair = await tokenPair('/hvk/admin/categories.php', sessionCookie);
await post('/hvk/admin/categories.php', sessionCookie, { ...pair, action: 'delete', id: deleteCategory.id, delete_dependents: '1' });
assert(!category(deleteCategoryName) && !product('Task05 Delete Product'), 'Confirmed category cleanup left database rows.');
const cleanupMedia = check('media-file-presence').filter(row =>
  (row.owner_type === 'category' && row.owner_id === Number(deleteCategory.id))
  || (row.owner_type === 'product' && row.owner_id === Number(deleteProduct.id))
);
assert(cleanupMedia.length >= 4 && cleanupMedia.every(row => row.state === 'deleted' && Object.values(row.files).every(value => value === false)), 'Category cleanup left media files.');

const categories = check('category-order');
const secondCategory = categories.find(row => row.id !== firstCategory.id);
assert(secondCategory, 'A second category is required.');
pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await post('/hvk/admin/products.php', sessionCookie, {
  ...pair, action: 'move_category', id: String(created.id), new_category_id: String(secondCategory.id),
});
assert(product(emptyName).category_id === secondCategory.id, 'Product move failed.');

const categoryOrder = check('category-order');
pair = await tokenPair('/hvk/admin/category-order.php', sessionCookie);
const reverseCategories = [...categoryOrder].reverse().map(row => String(row.id));
await post('/hvk/admin/category-order.php', sessionCookie, { ...pair, 'order[]': reverseCategories });
const reorderedCategories = check('category-order');
assert(reorderedCategories.every((row, index) => Number(row.sort_order) === index + 1), 'Category order was not normalized.');

const categoryProducts = check('catalog-order').filter(row => row.category_id === secondCategory.id);
if (categoryProducts.length > 1) {
  pair = await tokenPair(`/hvk/admin/product-order.php?category=${secondCategory.id}`, sessionCookie);
  await post('/hvk/admin/product-order.php', sessionCookie, {
    ...pair, category_id: String(secondCategory.id), 'order[]': [...categoryProducts].reverse().map(row => String(row.id)),
  });
  const reorderedProducts = check('catalog-order').filter(row => row.category_id === secondCategory.id);
  assert(reorderedProducts.every((row, index) => Number(row.sort_order) === index + 1), 'Product order was not normalized.');
}

pair = await tokenPair('/hvk/admin/bulk-prices.php', sessionCookie);
await post('/hvk/admin/bulk-prices.php', sessionCookie, { ...pair, action: 'bulk_update_prices', [`price[${created.id}]`]: '88,50' });
assert(product(emptyName).price === '88.50', 'Bulk price update regressed.');
pair = await tokenPair('/hvk/admin/bulk-prices.php', sessionCookie);
await post('/hvk/admin/bulk-prices.php', sessionCookie, { ...pair, action: 'bulk_update_prices', [`price[${created.id}]`]: '1e2' });
assert(product(emptyName).price === '88.50', 'Invalid bulk price changed data.');

pair = await tokenPair('/hvk/admin/bulk-prices.php', sessionCookie);
const priceCsv = new FormData();
for (const [key, value] of Object.entries({ ...pair, action: 'import_csv_prices' })) priceCsv.set(key, value);
priceCsv.set('csv_file', new Blob([`ID,Urun_Adi,Fiyat\n${created.id},${emptyName},91.25\n`]), 'prices.csv');
await request('/hvk/admin/bulk-prices.php', { method: 'POST', cookie: sessionCookie, body: priceCsv });
assert(product(emptyName).price === '91.25', 'Bulk price CSV import regressed.');

pair = await tokenPair('/hvk/admin/import-products.php', sessionCookie);
const importCsv = new FormData();
for (const [key, value] of Object.entries(pair)) importCsv.set(key, value);
importCsv.set('file', new Blob(['Task05 Import Category,Task05 Imported Product,Servis,44.00,,1\n']), 'products.csv');
await request('/hvk/admin/import-products.php', { method: 'POST', cookie: sessionCookie, body: importCsv });
assert(product('Task05 Imported Product'), 'Product import route regressed.');

created = product(emptyName);
pair = await tokenPair('/hvk/admin/products.php', sessionCookie);
await post('/hvk/admin/products.php', sessionCookie, { ...pair, action: 'delete', id: String(created.id) });
assert(!product(emptyName), 'Product delete failed.');
const finalOrder = check('catalog-order');
for (const row of check('category-order')) {
  const items = finalOrder.filter(productRow => productRow.category_id === row.id);
  assert(items.every((item, index) => Number(item.sort_order) === index + 1), `Final product order is invalid for category ${row.id}.`);
}

console.log(JSON.stringify({
  status: 'PASS', pages: adminPages.length,
  checks: ['CRUD', 'duplicate submit', 'validation', 'nutrition timestamps', 'media rollback', 'dependent cleanup', 'move', 'delete', 'reorder', 'bulk prices', 'product import'],
}, null, 2));
