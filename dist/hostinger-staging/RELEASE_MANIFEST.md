# Chef Hostinger staging manifest

Status: STAGING ONLY.
Source branch: `feature/chef-redesign`.
Source commit: `8fd4a8762eecbd3e1776219e11f427af5b24550e` with preserved working-tree changes.
Task 05 status: `PARTIAL (local static scope)`.

## Artifacts

| File | SHA-256 |
|---|---|
| `public_html.zip` | `577a2318af0eb3df472905a199a6d7e9b3ed38e1014d2eabfc8b5e3e88a96fa3` |
| `chef-private-setup.zip` | `bbc2769eb7ef914d2a5351beabb2f60888a9e12a55b0e0217471aedfb0260c61` |
| `INSTALL_TR.md` | `8252064f60d6b647f503de548645a6b373b79a80154190a1acb458eee3640d8f` |

## Guest baseline

- Packaged `index.html`: `afa9d953fffa936b3206b874f1d66179f370826b4e6adf83c165150e1cfb8928`.
- `current_revision`: `afa9d953fffa936b3206b874f1d66179f370826b4e6adf83c165150e1cfb8928`.
- Result: `PASS`.

## Apache files

`public_html.zip` contains these `.htaccess` files:

- `hvk/admin/.htaccess`
- `media/.htaccess`

`chef-private-setup.zip` contains no `.htaccess` file.

The admin and public media rules are present. The media rule disables listing and denies executable or double-extension names.

## Extracted archive scans

- Both archives were extracted into separate temporary directories.
- All ZIP entry paths use `/`; backslash, absolute, traversal, and duplicate paths are absent.
- A random marker provided a positive scanner control for each archive.
- The scanner found each marker. It removed each control file before the real scan.
- Extracted paths and contents contained no runtime test password.
- Extracted paths and contents contained no historical Task 03 password candidate.
- Extracted paths and contents contained no credential file name or test credential path.
- All extraction directories were removed after scanning.
- Git has no tracked `database/tests` history. Local Codex execution history supplied six historical literal candidates without recording their values.

## Included runtime

- `public_html.zip` contains the guest runtime from `public_html/`.
- It contains the admin runtime under `hvk/admin/`.
- It contains `database/lib.php` and the public media Apache rule.
- It contains only the built `hvk/admin/assets/admin.css` stylesheet.
- `chef-private-setup.zip` contains CLI, migrations, seed, recovery, media CLI, and placeholder configuration.

## Exclusions

- Tests, credentials, `*.credentials`, reports, and evidence.
- `node_modules`, repository metadata, and editor configuration.
- `chef-workflow`, `.agents`, `.impeccable`, and `.vscode`.
- `hvk/assets`, `hvk/components`, `hvk/index.html`, and `hvk/admin/uploads`.
- `hvk/logo.jpeg`, `hvk/thk.png`, `menuler`, and `cheflogo.pdf`.
- `chef-onizleme.html` and root source `menu-data.js`.
- Root development copies of `app.js` and `style.css`; public runtime copies came from `public_html/`.
- `package.json`, `package-lock.json`, Tailwind configuration, and `admin-tailwind.css`.

## Deployment prerequisites

- Verify Docker CRUD, nonce, nutrition, media, and reorder suites on Hostinger staging.
- Verify LiteSpeed upload execution denial on Hostinger staging.
- Verify the automatic media cleanup cron on Hostinger staging.
