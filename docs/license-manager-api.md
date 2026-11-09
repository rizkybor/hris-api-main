# License Manager API

The License Manager issues license keys for the owner's apps. Each app is a
**product** (OCCA is the first and only seeded one; more are added on the
Products page). An app activates its key against the public endpoints below
and receives a signed token that it verifies **offline** with the Ed25519
public key built into the app. One keypair signs the tokens of every
product; the token's `product` field says which product it is for.

## Products

| Column        | Meaning                                                                 |
|---------------|-------------------------------------------------------------------------|
| `code`        | Lowercase slug, `^[a-z0-9]+(-[a-z0-9]+)*$`, unique. Used in every path as `{product}`. Never changes after creation. |
| `name`        | Display name, e.g. `OCCA`                                                |
| `key_prefix`  | 1–6 uppercase letters; new keys start with it plus the plan code (`OC02-…`) |
| `description` | Optional                                                                 |
| `is_active`   | Off: activate / refresh answer `403 product_inactive`; deactivate still works |

Seeded: `occa` / `OCCA` / `OC` (the prefix was `OCCA` before `2026_11_10_000007`). Every license belongs to exactly one
product (`licenses.product_id`); rows that existed before the License Manager
were backfilled to OCCA.

## Plans

Plans are global (the same for every product).

| `plan`         | Label        | `category`     | `type`     | Who                                         |
|----------------|--------------|----------------|------------|---------------------------------------------|
| (no key)       | Starter      | individual     | –          | Free mode of the app; never stored          |
| `trial`        | Trial        | `individual`   | `term`     | Try every feature for a limited time        |
| `professional` | Professional | `individual`   | `term`     | Paid per period (default)                   |
| `lifetime`     | Lifetime     | `individual`   | `lifetime` | One-time payment, never expires             |
| `organization` | Organization | `organization` | `term`     | Teams, communities, clubs and federations   |

The **type follows the plan** and is set by the server: `lifetime` for the
Lifetime plan, `term` for every other plan. A `type` sent by a client is
ignored. Every term license needs a `duration` or an `expires_at` (otherwise
`422` on `duration`).

Renamed values: the draft `pro` became `professional`
(`2026_11_09_000003`), and `enterprise` became `lifetime`
(`2026_11_09_000006`). Neither old value is accepted anywhere.

## Licensee details per category

`licensee_name` / `licensee_email` are kept for both categories (the token's
`licensee` is `licensee_name`).

| Field               | Individual | Organization | Rule                                               |
|---------------------|:----------:|:------------:|----------------------------------------------------|
| `licensee_name`     | required (full name) | required (organization name) | string ≤ 255          |
| `licensee_email`    | required   | required (contact email) | email ≤ 255                           |
| `phone`             | required   | required (contact phone) | 6–20 chars of digits, spaces, `+`, `-` |
| `country` … `address` | see **Location** below | | |
| `occupation`        | optional   | –            | free text (Mapper, Course setter, Coach, …)        |
| `affiliation`       | optional   | –            | club or school                                     |
| `organization_type` | –          | required     | `team` \| `community` \| `club` \| `federation`    |
| `contact_name`      | –          | required     | contact person                                     |
| `contact_position`  | –          | optional     |                                                    |
| `website`           | –          | optional     | `url`                                              |
| `tax_id`            | –          | optional     | NPWP, ≤ 50                                         |
| `member_count`      | –          | optional     | integer ≥ 1                                        |

- The category comes from the plan. On store all of the category's required
  fields are required. On update the stored plan is used when `plan` is not
  sent; a field that is sent must satisfy the rules (it cannot be blanked),
  so partial updates such as `{"is_active": false}` still work on older
  rows. When the plan moves to the **other** category, all of the new
  category's required fields must be present after merging the request with
  the stored values (shared fields carry over).
- The other category's own fields are always cleared (set to `null`) on
  save; shared fields (`licensee_name`, `licensee_email`, `phone` and the
  whole location) are kept.
- Errors use the standard `422 {"message", "errors": {field: [...]}}` with
  the field names above. Rules live in `app/Http/Requests/LicenseFieldRules.php`
  and are shared by the HTTP requests and `license:create`.

## Location

Both categories have a complete location (the old `city` column was moved
into `regency` and dropped by `2026_11_10_000003`).

| Field          | Indonesia                                   | Other countries              |
|----------------|---------------------------------------------|------------------------------|
| `country`      | required (`Indonesia`, the default)         | required (English country name) |
| `province_code`, `regency_code`, `district_code`, `village_code` | **required**; must exist and belong to their parent | cleared |
| `province`, `regency`, `district`, `village` | filled from the codes (client names are ignored) | `province` (state) and `regency` (city) required, `district` optional, `village` cleared |
| `postal_code`  | required, 5 digits                          | required, 2–20 letters / digits / spaces / `-` |
| `address`      | street address: required for organizations, optional for individuals | same |

- `regency` is the city / regency (Kota/Kabupaten), `district` the
  Kecamatan, `village` the Kelurahan/Desa.
- On update, sending any location field validates the location as a whole;
  fields not sent come from the stored record.
- The resource adds `full_address`: street, village, district, regency,
  province + postal code, country.

### Indonesian regions

Tables `regions_provinces`, `regions_regencies`, `regions_districts`,
`regions_villages` (`code`, parent code, `name`), filled from
`database/data/regions/*.csv` (cahyadsn/wilayah, Kepmendagri No 300.2.2-2138
Tahun 2025, 38 provinces, MIT; see the README there) with:

```
php artisan regions:import            # idempotent: upsert on code, stale codes removed
php artisan regions:import --path=…   # another directory with the same four CSVs
```

Read endpoints (any signed-in user; `Cache-Control: private, max-age=86400`;
lists cached server-side for a day, refreshed by the import). Each returns
`data: [{code, name}]` sorted by name:

```
GET /api/v1/regions/provinces
GET /api/v1/regions/provinces/{code}/regencies
GET /api/v1/regions/regencies/{code}/districts
GET /api/v1/regions/districts/{code}/villages
```

## Active period

Every license resource carries:

| Field             | Meaning                                                                 |
|-------------------|-------------------------------------------------------------------------|
| `starts_at`       | Set on create; set to today when a term license gets an expiry but has no start |
| `expires_at`      | `null` for lifetime                                                     |
| `duration_days`   | Days from `starts_at` to `expires_at` (partial days round up); `null` for lifetime |
| `remaining_days`  | Whole days left, rounded up; `0` once expired; `null` for lifetime      |
| `elapsed_percent` | 0–100 (one decimal); `null` for lifetime                                |
| `status`          | `inactive` (switched off) > `expired` > `expiring_soon` (term, ≤ 14 days left) > `active` |
| `last_activity_at`| Newest device `last_seen_at` or `last_activated_at`                     |

## Keys

- Format: `{prefix}{planCode}-XXXX-XXXX-XXXX-{sequence}`, e.g.
  `OC01-7KQM-2XRT-9HZP-0001`. Generated server-side, unique across all
  products.
  - `prefix`: the product's `key_prefix`, 1–6 uppercase letters (OCCA: `OC`).
  - `planCode`: `01` Trial, `02` Professional, `03` Lifetime,
    `04` Organization (`License::PLAN_CODES`).
  - three random groups from `A–Z` and `2–9` without the ambiguous `0`/`O`,
    `1`/`I`/`L`, so keys are hard to guess.
  - `sequence`: the license's number within its product, across all plans,
    always 4 digits (`0001`–`9999`), so an OCCA key is always 20 characters
    (the length the app accepts); creating a license beyond `9999` is refused
    with a 422.
    A per-product counter (`license_products.license_sequence`) is
    incremented under a row lock in the create transaction, so concurrent
    creates never collide; numbers are never reused (also after deletes).
    Stored on the license as `sequence` and returned in the resource; the
    product resource has `next_sequence` (a preview only).
- **Keys never change.** The plan code is the plan at creation: changing the
  plan later keeps the key (an `OC02-…` key may belong to a Lifetime license).
- Keys issued in the earlier `OCCA-XXXX-XXXX-XXXX-XXXX` format stay valid;
  `2026_11_10_000007` numbered those licenses in creation order without
  touching their keys and started each product's counter there.
- Matching is case-insensitive and ignores surrounding spaces, and only
  looks within the product of the route: a key of another product answers
  `invalid_key`.

## Public endpoints

No authentication. Rate limit: 20 requests per minute per IP
(`throttle:20,1`; a 429 uses the app-wide `{success, message, data}` envelope).
All requests are JSON `POST`s:

```
POST /api/licenses/{product}/activate
POST /api/licenses/{product}/refresh
POST /api/licenses/{product}/deactivate
```

`/api/occa/license/activate|refresh|deactivate` stays as an alias for
`{product} = occa` (the path compiled into the OCCA app).

Errors are always:

```json
{ "error": "<code>", "message": "<human readable text>" }
```

### activate

```json
{
  "key": "OC02-7KQM-2XRT-9HZP-0001",
  "device_id": "5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44",
  "device_name": "Rizky's MacBook Pro",
  "platform": "macOS",
  "app_version": "1.4.0"
}
```

`device_name`, `platform` and `app_version` are optional. Re-activating a
`device_id` that is already registered never consumes another slot; it only
updates `last_seen_at`, `device_name`, `platform` and `app_version`.

Response `200`:

```json
{
  "token": "eyJ2IjoxLCJwcm9kdWN0Ijoib2NjYSIs…In0.q3n1d5…",
  "license": {
    "product": "occa",
    "key": "OC02-7KQM-2XRT-9HZP-0001",
    "plan": "professional",
    "type": "term",
    "licensee": "Jane Orienteer",
    "expires_at": "2027-05-09T00:00:00Z",
    "max_devices": 2,
    "devices_used": 1
  }
}
```

### refresh

`{ "key": "...", "device_id": "..." }`. Same checks as activate, plus the
device must already be activated. Always updates `last_seen_at`. Same
response shape.

### deactivate

`{ "key": "...", "device_id": "..." }`. Removes this device's activation
(frees its slot). Always `200 {"ok": true}`, also for an unknown product,
key or device, and also when the product is switched off.

### Error codes

| HTTP | `error`            | When                                                       |
|------|--------------------|------------------------------------------------------------|
| 422  | `invalid_request`  | Missing / malformed fields (`message` names the first one) |
| 404  | `invalid_product`  | Unknown product code                                       |
| 403  | `product_inactive` | The product is switched off                                |
| 404  | `invalid_key`      | Unknown (or deleted) key, or a key of another product      |
| 403  | `inactive`         | The owner switched the license off                         |
| 403  | `expired`          | `expires_at` is in the past                                |
| 409  | `device_limit`     | New device while `devices_used >= max_devices`             |
| 404  | `not_activated`    | `refresh` for a device that has no activation              |
| 500  | `server_error`     | Unexpected failure (e.g. signing key missing)              |

Checks run in this order: product (`invalid_product`, `product_inactive`),
`invalid_key`, `inactive`, `expired`, then `device_limit` / `not_activated`.

## Token format

```
token     = base64url(payloadJSON) + "." + base64url(signature)
signature = Ed25519 detached signature (libsodium sodium_crypto_sign_detached)
            over the base64url payload string (ASCII bytes), not the raw JSON
base64url = RFC 4648 §5 alphabet, WITHOUT "=" padding
```

Payload (snake_case keys, in this order):

| Field           | Type           | Meaning                                                        |
|-----------------|----------------|----------------------------------------------------------------|
| `v`             | int            | Token format version, currently `1`                            |
| `product`       | string         | Product code, e.g. `occa`; the app must check it                |
| `lid`           | int            | License id                                                     |
| `key`           | string         | Full license key                                               |
| `plan`          | string         | `trial`, `professional`, `lifetime` or `organization`          |
| `type`          | string         | `lifetime` or `term`                                           |
| `licensee`      | string \| null | Licensee name                                                  |
| `expires_at`    | string \| null | ISO 8601 UTC, e.g. `2027-05-09T00:00:00Z`; `null` for lifetime |
| `device_id`     | string         | The device the token was issued to                             |
| `iat`           | int            | Issued at (unix seconds)                                       |
| `refresh_after` | int            | `iat + 7 days`: the app should call `refresh` after this       |
| `offline_until` | int            | `min(iat + 30 days, expires_at)`; `iat + 30 days` for lifetime |

Example payload:

```json
{"v":1,"product":"occa","lid":12,"key":"OC02-7KQM-2XRT-9HZP-0001","plan":"professional","type":"term","licensee":"Jane Orienteer","expires_at":"2027-05-09T00:00:00Z","device_id":"5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44","iat":1794239819,"refresh_after":1794844619,"offline_until":1796831819}
```

App-side verification: split on `.`, base64url-decode the signature, verify it
against the **payload segment as received** (ASCII) with the built-in public
key, then base64url-decode and parse the payload, and check `product`,
`device_id` and `offline_until`.

Signing lives in `app/Services/LicenseTokenService.php`; the activation
flow in `app/Services/LicenseService.php`.

## Key setup

`config/licensing.php` reads two base64 values (libsodium Ed25519 keys). The
env names keep their `OCCA_` prefix from when OCCA was the only product; the
one pair signs every product's tokens:

```
OCCA_LICENSE_SECRET_KEY=   # 64-byte secret key, server only, never commit
OCCA_LICENSE_PUBLIC_KEY=   # 32-byte public key, also compiled into each app
```

Generate a pair (prints only, never writes a file):

```
php artisan license:keys
```

Changing the pair invalidates every token already issued and requires app
releases with the new public key, so generate it once and keep the secret
key backed up.

## Creating licenses from the command line

```
# The owner's key (Lifetime plan, never expires); Indonesia: the village code
# gives province, city / regency and district:
php artisan license:create --product=occa --plan=lifetime --name="Rizky Ajie Kurniawan" \
    --email=rizky@example.com --phone="+62 812-0000-0000" \
    --village-code=31.71.01.1001 --postal-code=10110 --max-devices=5

php artisan license:create --product=occa --plan=trial --days=14 --max-devices=1 \
    --name="Jane Orienteer" --email=jane@example.com --phone=08123456789 \
    --country=Malaysia --province=Selangor --city="Shah Alam" --postal-code=40000

php artisan license:create --product={product} --plan=organization --months=12 --max-devices=10 \
    --name="Club X" --org-type=club --contact-name="Budi" --email=club@example.com \
    --phone="021 555 1234" --address="Jl. Sudirman 1" --village-code=31.71.01.1002 --postal-code=10150
```

`occa:license-create` is an alias of `license:create` (its `--product`
defaults to `occa`, as does `license:create`'s).

Options:
- `--product` (product code, default `occa`), `--plan` (`trial` |
  `professional` | `lifetime` | `organization`, default `professional`).
- Term plans need exactly one of `--months=N` / `--days=N`. `--plan=lifetime`
  needs neither (`--lifetime` is optional there); `--lifetime` with any other
  plan, or `--months` / `--days` with the Lifetime plan, is rejected.
- Licensee details, validated with the same rules as the API (errors name
  the option): `--name`, `--email`, `--phone`, `--country` (default
  `Indonesia`), `--village-code` (Indonesia) or `--province` / `--city` /
  `--district` (elsewhere), `--postal-code`, `--address` (required for
  organizations), `--occupation`, `--affiliation`; organization:
  `--org-type`, `--contact-name`, `--contact-position`, `--website`,
  `--tax-id`, `--member-count`.
- `--max-devices` (default 2), `--notes`.

## Admin API (back office)

`auth:sanctum`, under `/api/v1/license-manager`, standard
`{success, message, data}` envelope. License endpoints are nested under the
product code; a license of another product answers `404`.

| Method | Path                                                                  | Permission |
|--------|-----------------------------------------------------------------------|------------|
| GET    | `summary` (overview: counts per product and all together)             | `license-manager-list` |
| GET    | `expiring?days=30&limit=10`                                           | `license-manager-view` |
| GET    | `events/recent?limit=15`                                              | `license-manager-view` |
| GET    | `products`, `products/{product}`                                      | `license-manager-menu` or `-list` |
| POST   | `products`                                                            | `license-manager-create` |
| PUT    | `products/{product}` (name, key prefix, description, active, icon, terms; the code never changes) | `license-manager-edit` |
| DELETE | `products/{product}` (only without licenses, deleted ones included; else `422`) | `license-manager-delete` |
| GET    | `products/{product}/licenses`, `…/all/paginated`, `…/summary`         | `license-manager-list` |
| GET    | `products/{product}/licenses/{id}`                                    | `license-manager-view` (or `-edit`, for the edit page) |
| GET    | `products/{product}/licenses/{id}/events`                             | `license-manager-view` |
| GET    | `products/{product}/licenses/{id}/certificate` (PDF)                  | `license-manager-view` |
| POST   | `products/{product}/licenses`                                         | `license-manager-create` |
| PUT    | `products/{product}/licenses/{id}` (incl. the status toggle)          | `license-manager-edit` |
| DELETE | `products/{product}/licenses/{id}/activations/{activation}`           | `license-manager-edit` |
| DELETE | `products/{product}/licenses/{id}`                                    | `license-manager-delete` |

### Permissions

One module, like `team`: `license-manager` (label "License Manager", section
Company) with `menu`, `list`, `view`, `create`, `edit`, `delete`. Products are
managed with the same permissions.

| Permission               | Allows                                                                 |
|--------------------------|------------------------------------------------------------------------|
| `license-manager-menu`   | The sidebar group and the overview page; reading products              |
| `license-manager-list`   | License lists, paginated lists, per-product and overview summaries; reading products |
| `license-manager-view`   | One license, its events, the certificate PDF, the `expiring` and `events/recent` widgets |
| `license-manager-create` | New licenses and new products                                          |
| `license-manager-edit`   | Editing licenses (incl. the status toggle and releasing a device) and products (incl. icon, terms, on / off) |
| `license-manager-delete` | Deleting licenses and products                                         |

History: `occa-license-*` was renamed in place to `license-manager-*`
(`2026_11_09_000009`); `2026_11_10_000005` adds `license-manager-view` to
every role that has `license-manager-list` and removes the interim
`license-product-manage`; `2026_11_10_000006` removes any `license-product-*`
rows from an earlier draft.

- **List**: `per_page` / `row_per_page` (default 10), `search` (key, name,
  email, contact name, phone), `status` (`active` | `expiring_soon` |
  `expired` | `inactive`), `type`, `plan`, `sort_by` (`created_at` default |
  `expires_at`) and `sort_dir` (`asc` | `desc`; lifetime licenses sort last).
  `active` matches the status field (on, not expired, more than 14 days
  left); `expired` includes expired licenses that are switched off.
- **Resource**: `product {id, code, name, key_prefix, icon_url, is_active}`, `plan`,
  `plan_label`, `category`, `type`, all licensee fields above (+
  `organization_type_label`), the active-period fields, `max_devices`,
  `devices_used`, `is_active`, `notes`, `terms_version` (the product's terms
  version when the license was created), `last_activated_at`,
  `last_activity_at`; `show` also has `activations[]`.
- **Store / update body**: the licensee fields, `plan` (required on store),
  `duration` (`14_days` | `30_days` | `6_months` | `1_year` | `custom`),
  `expires_at` (`YYYY-MM-DD`, with `custom`), `max_devices`, `notes`,
  `is_active`. A preset on update counts from today; a past `expires_at` is
  accepted on update. `key` and `type` are never taken from the client.
- **Product summary** (`products/{product}/licenses/summary`):
  `{total, active, expiring_soon, expired, inactive, expiring_soon_days,
  by_plan: {trial, professional, lifetime, organization}}`.
- **Overview** (`summary`): `{all: {total, active, expiring_soon, expired,
  inactive}, expiring_soon_days, products: [{id, code, name, key_prefix,
  icon_url, is_active, total, active, expiring_soon, expired, inactive,
  by_plan: {trial, professional, lifetime, organization}}]}`.
- **Expiring** (`expiring?days=30&limit=10`, days 1–3650, limit 1–100):
  switched-on licenses of every product whose `expires_at` is within the
  next N days, soonest first; the standard license resource (with
  `product.icon_url`, `remaining_days`, `status`).
- **Recent events** (`events/recent?limit=15`, 1–100): newest first across
  all products: `{id, event, created_at, device_name, platform, ip,
  admin: {name} | null, license: {id, key, licensee_name}, product: {code,
  name, icon_url}}`.
- **Products** also return `icon_url`, `terms` (Markdown), `terms_version`,
  `terms_effective_at`. Icon: optional `icon` upload (png / jpg / webp,
  ≤ 1 MB; multipart, `_method=PUT` on update), `remove_icon=1` removes it.
  Terms: changing the `terms` text requires a new `terms_version` (`422` on
  `terms_version` otherwise); `terms_effective_at` defaults to today.
- Deleting a license (soft delete) also removes its activations; the key
  then answers `invalid_key`.

## License certificate (PDF)

`GET …/products/{product}/licenses/{id}/certificate` streams an A4 PDF
(`Content-Disposition: attachment; filename="OCCA-License-<KEY>.pdf"`) and
logs a `certificate_downloaded` event. Rendered by
`App\Services\LicenseCertificateService` from
`resources/views/pdf/license-certificate.blade.php` (dompdf; tables and
fixed positioning, images embedded as base64):

- Page 1, the certificate: product icon and name, "Software License
  Certificate", the JC Digital logo (`resources/license-products/jcd-logo.png`);
  the key in a holographic security-foil box (pearl silver with a soft
  diagonal diffraction band in teal-blue → periwinkle → rose-gold →
  champagne, a fine grating, a specular streak, a repeating star motif,
  micro-text and a thin grey border), embossed, with a silver plan chip, a
  27 mm seal whose guilloche rings shift hue around the circle (the product
  icon on its centre) and, under a hairline, the issue date and
  reference `LIC-<PRODUCT CODE>-<sequence, 4 digits>` (e.g. `LIC-OCCA-0001`); licensee block with the full
  address; license terms table; three activation steps (OCCA-specific, else
  generic) plus release / 30-day offline notes; short terms of use; and,
  when the product has terms, "Subject to the <Product> License Terms
  v<version>, attached as Appendix A".
- Appendix A (only when the product has terms): the product's terms
  Markdown rendered with `Str::markdown`, then an acknowledgement block
  (licensee, key, terms version, issue date).
- The holographic artwork is pre-rendered once with GD by
  `php artisan license:certificate-assets [--variant=a|b|c|all]` into
  `resources/license-products/` (`cert-foil-{v}.png`, `cert-seal-{v}.png`,
  `cert-chip-{v}.png`, plus `-occa-{v}` variants whose micro-text / ring
  text name OCCA). Variants differ only in colour intensity: `a` soft, `b`
  medium (default), `c` vivid but refined; pick one with
  `config('licensing.certificate_hologram')` (env
  `LICENSE_CERTIFICATE_HOLOGRAM`). Nothing is generated per request.
- Every page: thin frame with accent corners, header, wave bands, footer
  ("Issued by Jendela Cakra Digital", date, reference, "Verify at
  hris.jcdigital.co.id") and "Page X of Y". Dates like "10 November 2026"
  (Asia/Jakarta). No QR code (no QR library in composer.json).

The terms version on page 1 and in the acknowledgement is the license's
snapshot (`licenses.terms_version`); the appendix text is the product's
current terms.

OCCA's terms ship with the repo (`resources/license-products/terms/occa.md`,
v1.0, effective 2026-11-10) and are seeded by `2026_11_10_000004` when empty.

## Tracking (license_events)

Every license has a timeline in `license_events` (`license_id`, `event`,
`device_id`, `device_name`, `platform`, `app_version`, `ip`, `user_id`,
`meta`, `created_at`), written only by `App\Services\LicenseEventLogger`.
A logging failure is reported and swallowed, never breaking the request.

| Event                       | Written when                                                      |
|-----------------------------|-------------------------------------------------------------------|
| `created`                   | License created (`meta`: plan, type, expires_at, max_devices, source `admin` / `cli`) |
| `updated`                   | Fields changed, except those below (`meta.changes`: `{field: {old, new}}`) |
| `activated_by_admin` / `deactivated_by_admin` | `is_active` changed                             |
| `extended`                  | `expires_at` changed (`meta`: old / new expiry, and start when it changed too) |
| `plan_changed`              | `plan` changed (`meta`: old_plan, new_plan)                       |
| `device_released_by_admin`  | Admin released a device                                           |
| `deleted`                   | License deleted                                                   |
| `certificate_downloaded`    | Certificate PDF downloaded (`meta`: reference, terms_version)     |
| `device_activated`          | New device seat                                                   |
| `device_reactivated`        | Same device activated again                                       |
| `refreshed`                 | Refresh; at most once per device per 24 h (`last_seen_at` always updates) |
| `device_released`           | Deactivate from the app                                           |
| `denied_device_limit` / `denied_inactive` / `denied_expired` | Denied activate / refresh on an existing key of the product |

Admin events carry the admin's `user_id` and IP; app events carry the device
and IP. `GET …/licenses/{id}/events`: paginated (`per_page`, default 15),
newest first, optional `event` filter; each item has `label` and
`user_name`.

Field changes and device activity are also still written to the
activity log under `License Manager`.

## Migrations

| File | What |
|------|------|
| `2026_11_09_000001…000003` | Original OCCA tables and the `pro` → `professional` rename |
| `2026_11_09_000004_add_profile_fields_to_occa_licenses_table` | Licensee detail columns, index on `expires_at` |
| `2026_11_09_000005_create_occa_license_events_table` | Tracking table |
| `2026_11_09_000006_rename_occa_license_plan_enterprise_to_lifetime` | `enterprise` → `lifetime` |
| `2026_11_09_000007_create_license_products_table` | Products, seeds OCCA |
| `2026_11_09_000008_move_occa_licenses_to_license_manager` | Renames the tables to `licenses`, `license_activations`, `license_events`, `occa_license_id` → `license_id`, adds `licenses.product_id` (backfilled to OCCA) |
| `2026_11_09_000009_rename_occa_license_permissions_to_license_manager` | `occa-license-*` → `license-manager-*` (adds the interim `license-product-manage`) |
| `2026_11_10_000001_add_icon_path_to_license_products_table` | Product icons; copies the OCCA icon to the public disk |
| `2026_11_10_000002_create_regions_tables` | Indonesian region tables (filled by `regions:import`) |
| `2026_11_10_000003_add_location_fields_to_licenses_table` | Province / regency / district / village / postal code and region codes; `city` → `regency`, `city` dropped |
| `2026_11_10_000004_add_terms_to_license_products_and_licenses` | Product terms (Markdown, version, effective date), `licenses.terms_version`; seeds OCCA's terms |
| `2026_11_10_000005_split_license_manager_permissions` | Adds `license-manager-view` (granted with `-list`), removes `license-product-manage` |
| `2026_11_10_000006_remove_license_product_permissions` | Removes any `license-product-*` rows |
| `2026_11_10_000007_add_license_sequence_and_new_key_format` | Per-product `license_sequence`, `licenses.sequence` (existing licenses numbered in creation order), OCCA prefix `OCCA` → `OC` |
