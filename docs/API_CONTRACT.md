# Chef API Contract

Status: Database contract for Task 07 and Task 08 integration.
The current legacy menu endpoint keeps its old response until Task 07.

## Public menu

Method: `GET`.

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
  "wifi": null,
  "campaign": null
}
```

- Public relationships use `category`, not numeric `categoryId`.
- Localized objects contain `tr`, `en`, and `ar`.
- Unknown translations are `null`. The server does not invent text.
- Image values are public URLs or `null`. They never contain server paths.
- Ingredients and calories are `null` until staff verification.
- A visible menu mutation increments `menu_version` in its database transaction.
- A menu read never changes schema or data.

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
