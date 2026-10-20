# Chef API Contract

Status: Task 07 menu integration contract.

## Public menu

Route: `GET /admin/api/menu.php`.

```json
{
  "version": 1,
  "currency": "TRY",
  "categories": [
    {
      "id": "et",
      "name": { "tr": "Et Menü", "en": "Meat Menu", "ar": "قائمة اللحوم" },
      "image": null
    }
  ],
  "items": [
    {
      "id": "izgara-kofte",
      "category": "et",
      "name": { "tr": "Izgara Köfte", "en": "Grilled Meatballs", "ar": "كفتة مشوية" },
      "description": { "tr": "160gr Köfte + Patates Kızartması + Salata", "en": "160g Meatballs + French Fries + Salad", "ar": "١٦٠غ كفتة + بطاطس مقلية + سلطة" },
      "image": null,
      "price": 415.00,
      "ingredients": null,
      "calories": null
    }
  ],
  "wifi": {
    "network": "Chef Guest",
    "password": null
  },
  "campaign": {
    "enabled": true,
    "image": "/media/campaign/chef.webp",
    "text": { "tr": null, "en": null, "ar": null }
  }
}
```

- Public relationships use `category`, not numeric `categoryId`.
- Localized objects contain `tr`, `en`, and `ar`.
- Unknown translations are `null`. The server does not invent text.
- Image values are public URLs or `null`. They never contain server paths.
- Ingredients and calories are `null` until staff verification.
- Verified ingredients contain localized line arrays.
- WiFi contains only the guest network name and optional password.
- Campaign data exists only for an enabled campaign with a public image.
- A visible menu mutation increments `menu_version` in its database transaction.
- A menu read never changes schema or data.

### Public menu caching

- The cache key is the request origin and menu route.
- The response does not vary by user, session, or language.
- `ETag` uses the committed `menu_version` and the API file time: `"chef-menu-v<version>-b<mtime>"`. A deploy changes it.
- `Cache-Control` is `public, no-cache, max-age=0, must-revalidate`.
- `Vary` is `Accept-Encoding`.
- A matching `If-None-Match` returns `304` without a body.
- The response does not set a session cookie.
- Admin, feedback, private media, and CSRF data never enter this cache.

## Admin catalog imports

Routes: `POST /admin/import-products.php` and `POST /admin/bulk-prices.php`.

- Both routes require an admin session, CSRF token, and one-use submission token.
- Each import accepts a file up to 2 MiB and 500 data rows.
- Product CSV fields are `category_name`, `product_name`, `description`, `price`, `image_url`, and `sort_order`.
- The CSV header is optional. The field order is fixed.
- Product JSON must be a top-level list of flat objects using only those fields.
- Nested values and unknown JSON fields are rejected.
- `image_url` must be empty. Staff upload images through the product form.
- Invalid files apply zero rows. The result reports exact applied and rejected counts.
- Valid product imports create missing categories and increment `menu_version` once.
- Price CSV accepts `id,price` or `id,name,price`.
- Duplicate IDs, missing products, and invalid prices reject the complete price file.
- A single comma or period can be the decimal separator with one or two decimal digits.
- Grouped values such as `1.234,56` and `1,234.56` are accepted.
- Ambiguous values such as `1.234` and `1,234` are rejected.
- CSV exports prefix risky text cells that start with `=`, `+`, `-`, or `@`.

## Editable translations

- Category and product forms edit English and Arabic names and descriptions.
- Turkish names remain required. Other translation fields are optional.
- The public API returns missing translations as `null`.
- The guest selects the requested language, then falls back to Turkish.
- Translation edits increment `menu_version` in the catalog transaction.
- The application sends no menu text to an external translation service.

## Feedback

Methods: `POST` and `OPTIONS`.
Content types: JSON or multipart form data.

| Field | Rule |
|---|---|
| `topic` | `taste`, `service`, `staff`, `hygiene`, or `other` |
| `rating` | Integer from 1 through 5 |
| `message` | Required. Maximum 2000 characters |
| `contact` | Optional. Maximum 150 characters |
| `language` | `tr`, `en`, or `ar` |
| `image` | Optional JPG, PNG, or WebP. Maximum 4 MB |
| `customer_name` | Not accepted or required |

The preview still has a 160-character contact limit. Task 08 must change it to 150 during integration.

The optional `Idempotency-Key` header has this behavior:

- First accepted request: `201`.
- Same key and same payload: `200` with the first ID and `duplicate: true`.
- Same key and different payload: `409`.
- No key: create a normal new submission.

The server stores only a SHA-256 key hash and a request fingerprint.

## Admin and errors

Admin endpoints use PHP server sessions. They do not use JWT.

| Status | Meaning |
|---|---|
| `400` | Invalid JSON or request syntax |
| `401` | Authentication required |
| `403` | Authorization or CSRF failure |
| `405` | Unsupported method. Include `Allow` |
| `409` | Idempotency conflict |
| `422` | Field validation failure |
| `429` | Login throttle. Include `Retry-After` |
| `500` | Generic server error with `requestId` |
| `503` | Configuration, schema, or required storage unavailable |

Error responses are valid JSON. Public errors do not contain SQL, paths, credentials, or stack traces.
Task 03 implements the complete authentication and error behavior.

## Private feedback images

Method: `GET`.
Route: `/admin/private-image.php?id=<managed-upload-id>`.

The route requires a valid administrator session.
The server resolves the file from the managed upload record.
It never accepts a filesystem path from the request.

Successful responses use these headers:

- `Content-Type: image/webp`.
- `X-Content-Type-Options: nosniff`.
- `Cache-Control: no-store`.

Missing records return `404`.
Anonymous requests follow the administrator authentication response.
