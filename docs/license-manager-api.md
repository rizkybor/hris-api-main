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
| `key_prefix`  | 1–6 uppercase letters; new keys start with it plus the platform/plan code (`OC32-…`) |
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

Every license is for one **platform**, fixed at creation: `mac`, `ipad` or
`windows` (OCCA for Windows). A Mac key only activates on a Mac, an iPad key
only on an iPad, a Windows key only on a Windows PC (see `wrong_platform`
below). iPad has no Trial; Windows sells the same plans as Mac.

Creating with `platform: "both"` generates a Mac and an iPad license in one
go (same licensee, plan, period and device limit; each with its own key and
number). They share a `bundle_id`; each license's resource lists the other
key(s) under `bundle`. Not available for Trial. All or nothing: if one
platform has no numbers left, neither is created.

- Format: `{prefix}{platform}{plan}-XXXX-XXXX-XXXX-{sequence}`, e.g.
  `OC32-7KQM-2XRT-9HZP-0001`. Generated server-side, unique across all
  products.
  - `prefix`: the product's `key_prefix`, 1–6 uppercase letters (OCCA: `OC`).
  - platform digit (`License::PLATFORM_CODES`): `3` Mac, `5` iPad, `7` Windows.
  - plan digit (`License::PLAN_DIGITS`): `1` Trial, `2` Professional,
    `3` Lifetime, `4` Organization. So Mac keys are `31`–`34`, iPad keys
    `52`–`54` and Windows keys `71`–`74`.
  - three random groups from `A–Z` and `2–9` without the ambiguous `0`/`O`,
    `1`/`I`/`L`, so keys are hard to guess.
  - `sequence`: the license's number within its product **and platform**,
    across all plans, always 4 digits (`0001`–`9999`), so an OCCA key is
    always 20 characters (the length the app accepts); creating a license
    beyond `9999` for a platform is refused with a 422 (on `platform`).
    Mac, iPad and Windows count separately (`license_products.mac_license_sequence`,
    `ipad_license_sequence`, `windows_license_sequence`), each incremented
    under a row lock in the create transaction, so concurrent creates never
    collide; numbers are never reused (also after deletes). Stored on the
    license as `sequence`; the product resource has
    `next_sequences: {mac, ipad, windows}` (a preview only).
- **A plan change gives a new key.** The code names the plan, so changing
  the plan (upgrade or downgrade) generates a new key with the new plan's
  code and the **same number** (`OC32-…-0001` → `OC33-…-0001`). Every old
  key of the license (all device keys of a `per_device` license) is
  retired (`license_retired_keys`) and answers `key_replaced`; every device
  is signed out and has to activate again with the new key. The
  `plan_changed` event has `old_key`, `new_key`, `keys_replaced` and
  `devices_signed_out` in `meta`. The platform itself can never change.
- **One license per licensee.** A licensee (email, case-insensitive) holds
  at most one license per product and platform (a Mac, an iPad and a
  Windows license, or a Mac + iPad bundle plus a Windows license, are fine). A second one for the same email and
  platform is a 422 on `licensee_email` (create, edit and the CLI); a
  different plan is a plan change on the existing license. Deleted
  licenses do not count.
- `2026_11_10_000008` reset every key issued before the platform split
  (the apps were not released yet): all existing licenses became Mac
  licenses, renumbered in creation order, with a new key.
- Matching is case-insensitive and ignores surrounding spaces, and only
  looks within the product of the route: a key of another product answers
  `invalid_key`.

### One key per device (`key_mode`)

Chosen per license at creation (`key_mode`, fixed afterwards):

- `shared`: one key (`licenses.key`) used by up to `max_devices` devices.
- `per_device`: the license holds `max_devices` unique keys
  (`license_keys`, position 1 = `licenses.key`; the others have the same
  head and 4-digit number with fresh random groups). Each key binds to the
  first device that activates it (`in_use`). Once that device is released
  (from the app or by the admin) the key is `used` and never works again
  (`key_used`); another device presenting a bound key gets `key_in_use`.
  Raising Max devices adds keys; lowering it removes the highest available
  keys, and below the number of keys in use or used is a 422.

`2026_11_11_000001` turned every existing license into `per_device`: its
key stays key 1, bound to the first device already using it; keys are added
up to Max devices. Devices that already shared the old key keep refreshing,
but activating a new device needs one of the new keys.

## Public endpoints

No authentication. Rate limit: 20 requests per minute per IP
(`throttle:20,1`; a 429 uses the app-wide `{success, message, data}` envelope).
All requests are JSON `POST`s:

```
POST /api/licenses/{product}/activate
POST /api/licenses/{product}/refresh
POST /api/licenses/{product}/deactivate
POST /api/licenses/{product}/restore
POST /api/licenses/{product}/rebind
```

`/api/occa/license/activate|refresh|deactivate|restore|rebind` stays as an alias for
`{product} = occa` (the path compiled into the OCCA app).

Errors are always:

```json
{ "error": "<code>", "message": "<human readable text>" }
```

### activate

```json
{
  "key": "OC32-7KQM-2XRT-9HZP-0001",
  "device_id": "5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44",
  "device_name": "Rizky's MacBook Pro",
  "platform": "macOS",
  "app_version": "1.4.0"
}
```

`platform` is **required**: `macOS` on a Mac, `iPadOS` on an iPad (`iOS` is
accepted for older iPads), `Windows` on a Windows PC (case, spaces and
digits such as "Windows 11" do not matter). A missing or unknown value is
`invalid_request`; a key of another platform is `wrong_platform`. `device_name` and `app_version` are optional. Re-activating a
`device_id` that is already registered never consumes another slot; it only
updates `last_seen_at`, `device_name`, `platform` and `app_version`.

Response `200`:

```json
{
  "token": "eyJ2IjoxLCJwcm9kdWN0Ijoib2NjYSIs…In0.q3n1d5…",
  "license": {
    "product": "occa",
    "key": "OC32-7KQM-2XRT-9HZP-0001",
    "plan": "professional",
    "platform": "mac",
    "type": "term",
    "licensee": "Jane Orienteer",
    "expires_at": "2027-05-09T00:00:00Z",
    "max_devices": 2,
    "devices_used": 1,
    "key_mode": "per_device",
    "key_position": 1,
    "keys_total": 2
  }
}
```

`key` is the key this device uses. `key_mode` is `shared` or `per_device`;
for `per_device`, `key_position` / `keys_total` say which key this is
("key 1 of 2"); both are `null` for `shared`. With `per_device` the app
should warn before releasing: the released key can never be used again.

### refresh

`{ "key": "...", "device_id": "...", "platform": "macOS", "app_version": "1.1.0" }`.
Same checks as activate, plus the device must already be activated.
`platform` is optional here; when it is not sent, the platform the device
activated with is checked. Always updates `last_seen_at`. `app_version` is
optional; a version different from the one stored for the device is saved
and logged as `app_updated` (`meta`: from, to). Same response shape.

### restore

```json
{
  "device_id": "5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44",
  "platform": "macOS",
  "app_version": "1.1.0",
  "device_name": "Rizky's MacBook Pro"
}
```

For an app that has no token any more (deleted and installed again, its
data cleared) but runs on a device that is still activated. No key is
sent: the server looks for an activation of this `device_id` on a license
of the product. When one is on, not expired, for this platform and its
per-device key is not `used`, the answer is the same as `refresh` (token
plus `license`, with the device's own key) and `device_restored` is
logged (plus `app_updated` for a new version). `platform` is required,
`app_version` and `device_name` optional (stored when sent).

It **never takes a seat**: a device without an activation answers
`404 not_activated` ("This device has no license activation to
restore."). Otherwise the errors of refresh: `invalid_request`,
`invalid_product`, `product_inactive`, `inactive`, `expired`,
`wrong_platform`, `key_used`. A key replaced after a plan change and a
released device (from the app or by the admin) have no activation, so they
answer `not_activated`. With several activations on one device id, the
newest usable one of the device's platform wins; otherwise the error of
the first one is returned. Denied restores are not logged (the app asks at
every launch while it has no token).

### rebind

```json
{
  "key": "OC32-7KQM-2XRT-9HZP-0001",
  "old_device_id": "5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44",
  "device_id": "0F6C2B9D-7E41-5A3C-8D12-6B9E4F0A1C77",
  "platform": "macOS",
  "app_version": "1.1.0",
  "device_name": "Rizky's MacBook Pro"
}
```

Moves this device's activation from `old_device_id` to `device_id` in one
call, for an app that changed how it identifies the device (OCCA 1.1 on the
Mac: from a random id to one derived from the hardware). The seat moves
along, and for a `per_device` license the key's binding too: nothing is
released, no key becomes `used`, so it is safe for both key modes. Same
checks as activate (`invalid_key`, `inactive`, `expired`, `wrong_platform`,
`key_replaced`, `key_used`; `platform` required); the old id must hold the
key (the shared key, or the per-device key bound to it), else
`not_activated`. The answer is a token for the new id (same shape as
refresh); `device_rebound` is logged with `meta.old_device_id`.

- A retry after a lost answer (old id gone, new id holds the key) answers
  for the new id and moves nothing.
- If the new id is already activated on the license, the answer is for the
  new id; the old id's shared seat is freed, a per-device key stays bound
  to the old id (an admin can release it).

### Stable device ids (apps)

`restore` only helps when the app sends the same `device_id` after a
reinstall. OCCA:

- **Mac:** the id is derived from the hardware UUID (IOKit
  `IOPlatformUUID`): SHA-256 of `occa-device:` + the UUID, formatted as a
  UUID string, so the raw hardware id never leaves the Mac. It survives
  deleting the app and its data. A Mac activated with the old random id
  moves itself to the new id with `rebind` once online; until then it keeps
  the old id.
- **iPad:** a random id in the Keychain (`AfterFirstUnlock`, not
  `ThisDeviceOnly`), which survives deleting the app. Erasing the iPad
  loses it (unless an iCloud / encrypted backup of the same iPad is
  restored); the key then has to be activated again as a new device.
- After "Release this device" the server has no activation, so `restore`
  does not bring the license back.

### releases (version list)

```
GET /api/licenses/{product}/releases?platform=macOS
GET /api/licenses/{product}/releases/latest?version=1.0.0&platform=macOS&key=OC33-...
```

`releases`: the published versions for the platform (`macOS` / `iPadOS` /
`Windows` or `mac` / `ipad` / `windows`; Mac and iPad include the shared
`both` releases, Windows only its own), newest first:
`{"product":"occa","releases":[{version, build, kind, platforms, title,
released_at, is_critical, download_url, changes:[{type, area, text}]}]}`.
`kind` is `major` (x.0.0), `minor` (x.y.0, features) or `patch` (x.y.z,
fixes); a change `type` is `feature`, `improvement` or `fix`.

`releases/latest` (update check; `version` required, `key` optional):

| Field               | Meaning                                                          |
|---------------------|------------------------------------------------------------------|
| `latest`            | Newest published release for the platform                        |
| `update`            | Newest release this license may install (Lifetime: within its major version), when newer than `version`; else null |
| `update_available`  | `update` is not null                                             |
| `upgrade_available` | Version of a newer major the license does not cover (a paid upgrade), else null |
| `required`          | A critical release is newer than `version`: the app should update |
| `updates_major`     | The key's covered major version (null = every version)          |

`GET /api/licenses/{product}/releases/{version}/download?key=OC32-...`
downloads the installer of a published version for a key of the product
that is on, not expired, covers the version and is of the installer's
platform: a Mac key for a Mac / shared version (.dmg), a Windows key for a
Windows version (.msi / .msix) (`upgrade_required` 403 for a Lifetime key of
an older major, `wrong_platform` otherwise). The `download_url` of a release
with a stored installer points here.

### deactivate

`{ "key": "...", "device_id": "..." }`. Removes this device's activation
(frees its slot). For a `per_device` license only the key bound to the
device releases it, and that key becomes `used` (dead). Always `200 {"ok": true}`, also for an unknown product,
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
| 403  | `wrong_platform`   | A key of another platform (a Mac key on an iPad or a Windows PC, …) |
| 409  | `device_limit`     | New device while `devices_used >= max_devices`             |
| 403  | `key_used`         | `per_device`: the key's device was released; the key is dead |
| 409  | `key_in_use`       | `per_device`: the key is activated on another device       |
| 403  | `key_replaced`     | The key was replaced by a new one after a plan change      |
| 404  | `not_activated`    | `refresh` / `rebind` for a device that has no activation; `restore` for a device id without an activation on the product |
| 500  | `server_error`     | Unexpected failure (e.g. signing key missing)              |

Checks run in this order: product (`invalid_product`, `product_inactive`),
`invalid_key`, `inactive`, `expired`, `wrong_platform` (activate), then
`device_limit` / `not_activated` (refresh checks `wrong_platform` after
`not_activated`).

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
| `updates_major` | int \| null    | Lifetime: the major version the license covers (OCCA 1.x = 1); the app refuses a higher major. Null for other plans |

Example payload:

```json
{"v":1,"product":"occa","lid":12,"key":"OC32-7KQM-2XRT-9HZP-0001","plan":"professional","platform":"mac","type":"term","updates_major":null,"licensee":"Jane Orienteer","expires_at":"2027-05-09T00:00:00Z","device_id":"5B0E7C1A-3F55-4C8B-9E1D-2A6F0D9B7C44","iat":1794239819,"refresh_after":1794844619,"offline_until":1796831819}
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

# A Windows key (OC7x-...) for OCCA for Windows:
php artisan license:create --product=occa --platform=windows --plan=professional --months=12 \
  --name="Jane Orienteer" --email=jane@example.com --phone="+62 812-1111-2222" \
  --village-code=32.73.01.1001 --postal-code=40151

# An iPad key (OC5x-...); iPad has no trial:
php artisan license:create --product=occa --platform=ipad --plan=professional --months=12 \
    --name="Jane Orienteer" --email=jane@example.com --phone=08123456789 \
    --village-code=31.71.01.1001 --postal-code=10110

php artisan license:create --product=occa --plan=trial --days=14 --max-devices=1 \
    --name="Jane Orienteer" --email=jane@example.com --phone=08123456789 \
    --country=Malaysia --province=Selangor --city="Shah Alam" --postal-code=40000

php artisan license:create --product={product} --plan=organization --months=12 --max-devices=10 \
    --name="Club X" --org-type=club --contact-name="Budi" --email=club@example.com \
    --phone="021 555 1234" --address="Jl. Sudirman 1" --village-code=31.71.01.1002 --postal-code=10150
```

`occa:license-create` is an alias of `license:create` (its `--product`
defaults to `occa`, as does `license:create`'s). `--platform` is `mac`
(default), `ipad`, `windows` or `both` (Mac + iPad; prints both keys).

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

Licenses carry `platform` (`mac` | `ipad` | `windows`), `platform_label`, `bundle_id`
and (on show / create) `bundle`: `[{id, key, platform, platform_label, plan,
is_active}]` of the licenses generated together with it. On create
`platform` is required (`mac`, `ipad`, `windows` or `both`) and the plan
must be sold on it (no Trial on iPad); `both` answers with the Mac license
(Windows is never bundled). On update the
platform may only be resent unchanged. The license lists accept a
`platform` filter.

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
  reference `LIC-<PRODUCT CODE>-<PLATFORM>-<sequence, 4 digits>` (e.g. `LIC-OCCA-MAC-0001`, `LIC-OCCA-IPAD-0001`); licensee block with the full
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

For a `per_device` license the key box says "key 1 of N" and a **Device
Keys** page lists every key with its status. `?key_id=` (download) or
`key_id` (`…/certificate/email`, `…/certificate/share-link`) makes the
certificate of **one key** instead: only that key ("key n of N", one
device), no Device Keys page, reference `LIC-OCCA-MAC-0001-K03`. The key
may belong to the license or its bundle; a used key is a 422, a key of
another license a 404. The share link signs the key into its URL; events
carry `key` and `key_position` in `meta`.

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
| `app_updated`               | A device reported another app version on activate / refresh (`meta`: from, to) |
| `device_released`           | Deactivate from the app                                           |
| `device_restored`           | `restore`: the app got its existing activation back after a reinstall (no new seat) |
| `device_rebound`            | `rebind`: the activation moved to the device's new id (`meta`: old_device_id) |
| `install_link_created` / `install_link_revoked` | Admin made / revoked an iPad install link for this license (`meta`: version, link_id, expires_at, device_name) |
| `install_link_used`         | The `.ipa` of a link for this license streamed completely (`platform` iPadOS, `app_version` = the version; `meta`: link_id, bytes, user_agent) |
| `denied_device_limit` / `denied_inactive` / `denied_expired` / `denied_wrong_platform` / `denied_key_used` / `denied_key_in_use` / `denied_key_replaced` | Denied activate / refresh / rebind on an existing key of the product (`restore` logs no denials) |

Admin events carry the admin's `user_id` and IP; app events carry the device
and IP. `GET …/licenses/{id}/events`: paginated (`per_page`, default 15),
newest first, optional `event` filter; each item has `label` and
`user_name`.

Field changes and device activity are also still written to the
activity log under `License Manager`.

## Versions (release history)

Every product has a version list in `license_releases`: `version`
(`major.minor.patch`, unique per product; `major` / `minor` / `patch` are
kept in sync), `build`, `platforms` (`both` = Mac + iPad / `mac` / `ipad` /
`windows`; OCCA for Windows has its own version line and installer), `title`,
`status` (`draft` / `published`; only published versions reach the apps),
`released_at` (required to publish), `is_critical` (older versions must
update), `download_url`, `changes` (`[{type: feature|improvement|fix, area,
text}]`) and internal `notes`. OCCA's first list (1.0.0, every feature of
the first release) comes from `resources/license-products/releases/occa.json`
as a draft.

Licenses and versions: `licenses.updates_major` is the major version a
license gets updates for. A new Lifetime license gets the product's current
major (newest published release, 1 before the first); a paid upgrade raises
it (edit the license). Every other plan has null = every version while it
runs. The value is in the signed token, so the app enforces it offline.

Each version also keeps its installers on the private `local` disk under
`license-releases/{product}/{version}/`, with size and SHA-256: the Mac
`.dmg` or, for a `windows` version, the Windows `.msi` / `.msix` (`file_*`)
and the iPad `.ipa` (`ipa_*`, see "iPad installers" below). A Mac version is
published only with its `.dmg`, a Windows version only with its `.msi`, an
iPad version only with its `.ipa`, a shared (`both`) version only with both. Files stay
when a version is deleted (soft delete). The server must accept large uploads
(PHP `upload_max_filesize` / `post_max_size`, nginx `client_max_body_size`;
up to 2 GB).

Permissions: own module `license-release-*` (Roles & Permissions › License
Versions): `menu` / `list` / `view` read, `create` adds, `edit` edits,
publishes and uploads the installer, `delete` deletes, `download` downloads
installers. Migration `…000014` gives every role with a `license-manager-*`
permission the matching one (`download` follows `view`).

Admin:

| Method | Path | |
|--------|------|-|
| GET    | `/api/v1/license-manager/products/{product}/releases?status=&platform=&search=` | `{current_major, latest_version, versions_in_use: [{version, platform, devices}], releases: [...]}` |
| POST   | `/api/v1/license-manager/products/{product}/releases` | Add a version |
| GET    | `/api/v1/license-manager/products/{product}/releases/{id}` | One version |
| PUT    | `/api/v1/license-manager/products/{product}/releases/{id}` | Edit / publish |
| DELETE | `/api/v1/license-manager/products/{product}/releases/{id}` | Delete (soft) |
| POST   | `/api/v1/license-manager/products/{product}/releases/{id}/file` | Upload / replace the installer (multipart `file`: .dmg, .zip, .pkg; for a `windows` version .msi, .msix, .exe) |
| GET    | `/api/v1/license-manager/products/{product}/releases/{id}/file` | Download the installer |
| DELETE | `/api/v1/license-manager/products/{product}/releases/{id}/file` | Remove it (drafts only) |
| POST   | `/api/v1/license-manager/products/{product}/releases/{id}/ipa` | Upload / replace the iPad installer (multipart `file`: .ipa) |
| GET    | `/api/v1/license-manager/products/{product}/releases/{id}/ipa` | Download it (`license-release-download`) |
| DELETE | `/api/v1/license-manager/products/{product}/releases/{id}/ipa` | Remove it (drafts, or versions that do not need it) |
| GET    | `/api/v1/license-manager/products/{product}/releases/{id}/install-links` | `{app_url_https, default_days, max_days, links: [...]}` (`-menu` / `-list` / `-view`) |
| POST   | `/api/v1/license-manager/products/{product}/releases/{id}/install-links` | New single-use install link (`-edit`) |
| POST   | `/api/v1/license-manager/products/{product}/releases/{id}/install-links/{link}/revoke` | Revoke it (`-edit`) |

Each release also has `kind`, `kind_label`, `change_counts`,
`licenses_covered` (running licenses that get it), `licenses_needing_upgrade`
(Lifetime licenses of an older major) and `devices_on_version` (devices whose
app last reported this version). `versions_in_use` is the log of app versions
the activated devices report. A release also has `ipa` (below), `needs_ipa`,
`install_links_active` and `install_links_used`.

### iPad installers (.ipa)

An Ad Hoc signed `.ipa` (OCCA: `Tools/make-ipa.sh`), up to 2 GB, uploaded
with `license-release-edit`. On upload `App\Services\IpaInspector` reads the
zip without unpacking it:

- `Payload/<App>.app/Info.plist` (binary or XML plist, `App\Support\Plist`):
  `CFBundleIdentifier`, `CFBundleShortVersionString`, `CFBundleVersion`,
  `MinimumOSVersion`. **Refused (422 on `file`)**: not an app archive, a
  bundle id other than the product's (`config('licensing.ios_bundle_ids')`,
  OCCA `id.cakrapencil.app`; products not listed there are not checked), a
  short version other than the release's version.
- `embedded.mobileprovision`: the XML between `<?xml` and `</plist>` inside
  the CMS envelope (the signature is not verified; iPadOS does that).
  Profile `type`: `ad_hoc` (devices listed, no `get-task-allow`),
  `development`, `enterprise` (`ProvisionsAllDevices`) or `app_store`.
- **Warnings** (stored, upload accepted): build differs from the release's
  `build`; no profile; profile not Ad Hoc; profile expired.

`ipa` in the release resource: `{name, size, sha256, uploaded_at, bundle_id,
short_version, build, minimum_os, profile: {name, type, type_label, team_id,
team_name, app_id, created_at, expires_at, expired, device_count, devices:
[UDIDs]} | null, warnings: []}`. `expired` is recomputed on every read.

### Install links (single-use OTA install)

Table `license_install_links`: `release_id`, `license_id` (optional, an iPad
license), `device_name`, `note`, `token_hash` (SHA-256, unique; lookup),
`token_encrypted` (APP_KEY; lets the back office show an active link again),
`created_by`, `expires_at`, `status` (`active` / `used` / `revoked`;
`expired` is computed on read from `expires_at`, no scheduler needed),
`used_at`, `used_ip`, `used_user_agent`, `revoked_at`, `revoked_by`,
`page_opened_at`, `manifest_fetched_at`, `download_attempts`,
`download_lease_id`, `download_lease_until`.

Admin (permissions reuse the version module, no new permission): listing
needs `license-release-menu` / `-list` / `-view`; creating and revoking need
`license-release-edit` (the permission that uploads the installer).

- **Create** body: `license_id` (optional; must be an iPad license of the
  product that is on, not expired and covers the version, else 422 on
  `license_id`), `device_name` (≤ 100), `note` (≤ 500), `expires_in_days`
  (1–30, default 7; `licensing.install_link_days` / `_max_days`). The
  version needs an `.ipa` (422 on `release`). Answer: the link resource plus
  `url`, `manifest_url` and `app_url_https`.
- **Link resource**: `id, release_id, version, status, status_label, url`
  (only while active), `expires_at, license {id, key, licensee_name,
  platform} | null, device_name, note, created_by {id, name}, created_at,
  page_opened_at, manifest_fetched_at, download_attempts, used_at, used_ip,
  used_user_agent, revoked_at, revoked_by`.
- **Revoke**: an active link becomes `revoked`; a used link is a 422; an
  expired link stays `expired` (200, no change).
- Logged: `install_link_created` / `install_link_revoked` /
  `install_link_used` in the license's events when the link has a license;
  every create / revoke / use also goes to the activity log (`License
  Manager`).

Public (no login, the token is the credential; `throttle:30,1`; token
`[A-Za-z0-9]{20,80}`, 40 characters today):

| Method | Path | |
|--------|------|-|
| GET | `/api/licenses/{product}/install/{token}` | Mobile HTML page: icon, version, "Install OCCA" → `itms-services://?action=download-manifest&url=<manifest URL>`, short steps; English / Indonesian (`?lang=en|id`, else `Accept-Language`). Used / expired / revoked: 410 with a message; unknown: 404 |
| GET | `/api/licenses/{product}/install/{token}/manifest.plist` | OTA manifest, `text/xml`: `items[0].assets[0]` `{kind: software-package, url: …/app.ipa}`, `metadata` `{bundle-identifier, bundle-version (short version), kind: software, title (product name)}`. May be fetched any number of times while active |
| GET | `/api/licenses/{product}/install/{token}/app.ipa` | The `.ipa`, `application/octet-stream`, `Content-Length`, `Accept-Ranges: none`, `X-Accel-Buffering: no`. Single use (below) |

Used, expired and revoked links answer **410** (plain text) on the manifest
and the `.ipa`.

**When a link counts as used.** The `.ipa` request first takes a download
lease with one atomic `UPDATE … WHERE status = active AND not expired AND
(no lease OR lease ran out)`; a second parallel download gets **409** ("being
downloaded right now"). The file then streams in 1 MB chunks
(`ignore_user_abort`, lease renewed every 10 s). Only when every byte was
written and `connection_aborted()` is false does the link become `used`
(again guarded by the lease id, so it happens once).

- Interrupted download (client gone, PHP notices on write): the lease is
  released at once; the link stays active and iPadOS' "Retry" (or tapping
  Install again) works.
- A download that died without PHP noticing: the lease runs out after 60 s,
  then a retry works.
- `HEAD` answers the headers only and never uses the link.
- **Range requests are not supported**: the whole file is always sent
  (`Accept-Ranges: none`), so "complete" is unambiguous; a client that asks
  for a range gets the full file (200).
- "Complete" means handed to the web server. Behind nginx the
  `X-Accel-Buffering: no` header turns response buffering off, so this is
  close to "received by the iPad"; the last socket buffer can still be lost,
  in which case the link is used although the install failed: make a new link.
- A link used on an iPad that is not in the profile still counts as used
  (iPadOS downloads, then refuses to install): make a new link after
  rebuilding with that iPad's UDID.

**Requirements.** iOS installs only over **https with a valid, trusted
certificate** (no self-signed); every URL is built from `config('app.url')`,
so `APP_URL` must be the public `https://` URL of this API. The admin list
and create answers carry `app_url_https` and the back office warns when it is
false.

## Deploying iPad installs

1. `php artisan migrate --path=database/migrations/2026_11_19_000001_add_ipa_installer_to_license_releases.php`
   and `…/2026_11_19_000002_create_license_install_links_table.php` (or a
   plain `php artisan migrate`). No seeder: permissions are unchanged.
2. `APP_URL` must be `https://…` with a valid certificate (iOS refuses
   anything else). Changing `APP_KEY` makes stored tokens undecryptable:
   active links keep working (hash lookup) but the back office cannot show
   their URL again.
3. Upload limits for `.ipa` files (like the `.dmg`): PHP `upload_max_filesize`
   and `post_max_size` (e.g. `2G`), nginx `client_max_body_size 2G;`, and
   enough `max_execution_time` / `fastcgi_read_timeout` for the upload and
   the streamed download (the download itself calls `set_time_limit(0)`).
4. nginx: keep the default that honours `X-Accel-Buffering` (do not set
   `fastcgi_ignore_headers X-Accel-Buffering`).
5. `php artisan config:clear` / `config:cache` (new keys in
   `config/licensing.php`: `ios_bundle_ids`, `install_link_days`,
   `install_link_max_days`).

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
| `2026_11_10_000009_add_bundle_id_to_licenses` | `licenses.bundle_id`: Mac + iPad keys generated together |
| `2026_11_10_000008_add_platform_to_licenses` | `licenses.platform`, separate Mac / iPad counters (`mac_license_sequence`, `ipad_license_sequence`), sequence unique per product + platform; resets existing keys to Mac keys in the new format |
| `2026_11_10_000010_create_license_releases_table` | Version list per product |
| `2026_11_10_000011_add_updates_major_to_licenses` | `licenses.updates_major` (existing Lifetime licenses: 1) |
| `2026_11_10_000012_import_occa_release_history` | OCCA 1.0.0 (draft) from `resources/license-products/releases/occa.json` |
| `2026_11_10_000013_add_installer_file_to_license_releases` | Installer file per version (path, name, size, SHA-256) |
| `2026_11_10_000014_add_license_release_permissions` | `license-release-*` permissions, copied from `license-manager-*` on existing roles |
| `2026_11_19_000001_add_ipa_installer_to_license_releases` | iPad installer per version (`ipa_path`, name, size, SHA-256, `ipa_meta`, uploaded_at) |
| `2026_11_19_000002_create_license_install_links_table` | Single-use iPad install links |
| `2026_11_23_000001_add_windows_license_sequence_to_license_products` | OCCA for Windows: `windows` platform (key digit 7) with its own counter `windows_license_sequence` |
