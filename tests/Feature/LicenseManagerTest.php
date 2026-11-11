<?php

use App\Interfaces\LicenseRepositoryInterface;
use App\Mail\LicenseCertificateMail;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use App\Models\User;
use App\Services\LicenseCertificateService;
use App\Services\LicenseTokenService;
use App\Support\IndonesianRegions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * The full migration history contains MySQL-only statements (ALTER ...
 * MODIFY) that cannot run on the in-memory SQLite test database from
 * phpunit.xml, so RefreshDatabase fails for every feature test. This file
 * migrates only the tables the OCCA license module touches instead. The
 * in-memory database is rebuilt for each test because every test boots a
 * fresh application.
 */
const LICENSE_TEST_MIGRATIONS = [
    'database/migrations/0001_01_01_000000_create_users_table.php',
    'database/migrations/2025_09_13_085527_create_permission_tables.php',
    'database/migrations/2025_09_17_083207_create_personal_access_tokens_table.php',
    'database/migrations/2026_08_16_071919_create_activity_log_table.php',
    'database/migrations/2026_08_16_071920_add_event_column_to_activity_log_table.php',
    'database/migrations/2026_08_16_071921_add_batch_uuid_column_to_activity_log_table.php',
    'database/migrations/2026_09_06_072304_add_is_active_to_users_table.php',
    'database/migrations/2026_11_09_000001_create_occa_licenses_table.php',
    'database/migrations/2026_11_09_000002_create_occa_license_activations_table.php',
    'database/migrations/2026_11_09_000003_rename_occa_license_plan_pro_to_professional.php',
    'database/migrations/2026_11_09_000004_add_profile_fields_to_occa_licenses_table.php',
    'database/migrations/2026_11_09_000005_create_occa_license_events_table.php',
    'database/migrations/2026_11_09_000006_rename_occa_license_plan_enterprise_to_lifetime.php',
    'database/migrations/2026_11_09_000007_create_license_products_table.php',
    'database/migrations/2026_11_09_000008_move_occa_licenses_to_license_manager.php',
    'database/migrations/2026_11_09_000009_rename_occa_license_permissions_to_license_manager.php',
    'database/migrations/2026_11_10_000001_add_icon_path_to_license_products_table.php',
    'database/migrations/2026_11_10_000002_create_regions_tables.php',
    'database/migrations/2026_11_10_000003_add_location_fields_to_licenses_table.php',
    'database/migrations/2026_11_10_000004_add_terms_to_license_products_and_licenses.php',
    'database/migrations/2026_11_10_000005_split_license_manager_permissions.php',
    'database/migrations/2026_11_10_000006_remove_license_product_permissions.php',
    'database/migrations/2026_11_10_000007_add_license_sequence_and_new_key_format.php',
    'database/migrations/2026_11_10_000008_add_platform_to_licenses.php',
    'database/migrations/2026_11_10_000009_add_bundle_id_to_licenses.php',
    'database/migrations/2026_11_10_000010_create_license_releases_table.php',
    'database/migrations/2026_11_10_000011_add_updates_major_to_licenses.php',
    'database/migrations/2026_11_10_000012_import_occa_release_history.php',
    'database/migrations/2026_11_10_000013_add_installer_file_to_license_releases.php',
    'database/migrations/2026_11_10_000014_add_license_release_permissions.php',
    'database/migrations/2026_11_11_000001_create_license_keys_table.php',
];

const LICENSE_ACTIONS = ['menu', 'list', 'view', 'create', 'edit', 'delete'];

const LICENSE_RELEASE_ACTIONS = ['menu', 'list', 'view', 'create', 'edit', 'delete', 'download'];

/**
 * OC + platform/plan code (Mac 31-34, iPad 52-54), three random groups,
 * the 4-digit sequence.
 */
const OCCA_KEY_REGEX = '/^OC(3[1-4]|5[2-4])-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-\d{4}$/';

/**
 * Indonesian address (fixture regions): Gambir, Jakarta Pusat.
 */
const JAKARTA = [
    'country' => 'Indonesia',
    'province_code' => '31',
    'regency_code' => '31.71',
    'district_code' => '31.71.01',
    'village_code' => '31.71.01.1001',
    'postal_code' => '10110',
];

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    // The icon migration copies the OCCA icon to the public disk.
    Storage::fake('public');
    Artisan::call('migrate', ['--path' => LICENSE_TEST_MIGRATIONS, '--force' => true]);
    // A few real region codes (Jakarta Pusat / Gambir, Bandung / Sukasari).
    Artisan::call('regions:import', ['--path' => base_path('tests/Fixtures/regions')]);

    // Throwaway signing keypair for this test run only.
    $keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($keypair);
    config([
        'licensing.license_secret_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        'licensing.license_public_key' => base64_encode($this->publicKey),
    ]);

    foreach (LICENSE_ACTIONS as $action) {
        Permission::firstOrCreate(['name' => 'license-manager-'.$action, 'guard_name' => 'sanctum']);
    }
    foreach (LICENSE_RELEASE_ACTIONS as $action) {
        Permission::firstOrCreate(['name' => 'license-release-'.$action, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'sanctum'])
        ->givePermissionTo(array_merge(
            array_map(fn ($action) => 'license-manager-'.$action, LICENSE_ACTIONS),
            array_map(fn ($action) => 'license-release-'.$action, LICENSE_RELEASE_ACTIONS),
        ));
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('superadmin');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
});

function occaActivate($test, License $license, string $deviceId = 'device-1', array $extra = [])
{
    return $test->postJson('/api/occa/license/activate', array_merge([
        'key' => $license->key,
        'device_id' => $deviceId,
        'device_name' => 'MacBook Pro',
        'platform' => 'macOS',
        'app_version' => '1.4.0',
    ], $extra));
}

/**
 * A complete individual (professional, term) store payload.
 */
function occaIndividual(array $overrides = []): array
{
    return array_merge([
        'licensee_name' => 'Jane Orienteer',
        'licensee_email' => 'jane@example.com',
        'phone' => '+62 812-1111-2222',
        'country' => 'Indonesia',
        'province_code' => '32',
        'regency_code' => '32.73',
        'district_code' => '32.73.01',
        'village_code' => '32.73.01.1001',
        'postal_code' => '40151',
        'platform' => 'mac',
        'plan' => 'professional',
        'type' => 'term',
        'duration' => '1_year',
    ], $overrides);
}

/**
 * A complete organization (term) store payload.
 */
function occaOrganization(array $overrides = []): array
{
    return array_merge([
        'licensee_name' => 'Jakarta Orienteering Club',
        'organization_type' => 'club',
        'contact_name' => 'Budi Santoso',
        'licensee_email' => 'club@example.com',
        'phone' => '021 555 1234',
        'address' => 'Jl. Sudirman 1',
        ...JAKARTA,
        'platform' => 'mac',
        'plan' => 'organization',
        'type' => 'term',
        'duration' => '1_year',
    ], $overrides);
}

function occaDecodeSegment(string $segment): string
{
    return LicenseTokenService::base64UrlDecode($segment);
}

// ---------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------

test('activation returns a token signed with the public key and the right payload', function () {
    $license = License::factory()->create([
        'licensee_name' => 'Jane Orienteer',
        'expires_at' => '2027-05-09 00:00:00',
        'max_devices' => 3,
    ]);

    $before = time();
    $response = occaActivate($this, $license, 'device-1', ['key' => '  '.strtolower($license->key).' ']);

    $response->assertOk()
        ->assertJsonPath('license.key', $license->key)
        ->assertJsonPath('license.plan', 'professional')
        ->assertJsonPath('license.type', 'term')
        ->assertJsonPath('license.licensee', 'Jane Orienteer')
        ->assertJsonPath('license.expires_at', '2027-05-09T00:00:00Z')
        ->assertJsonPath('license.max_devices', 3)
        ->assertJsonPath('license.devices_used', 1);

    $token = $response->json('token');
    expect($token)->not->toContain('=');
    [$encodedPayload, $encodedSignature] = explode('.', $token);

    // Signature is over the ASCII base64url payload string.
    expect(sodium_crypto_sign_verify_detached(occaDecodeSegment($encodedSignature), $encodedPayload, $this->publicKey))->toBeTrue();

    $payload = json_decode(occaDecodeSegment($encodedPayload), true);
    expect(array_keys($payload))->toBe(['v', 'product', 'lid', 'key', 'plan', 'platform', 'type', 'updates_major', 'licensee', 'expires_at', 'device_id', 'iat', 'refresh_after', 'offline_until']);
    expect($payload['v'])->toBe(1)
        ->and($payload['product'])->toBe('occa')
        ->and($payload['lid'])->toBe($license->id)
        ->and($payload['key'])->toBe($license->key)
        ->and($payload['plan'])->toBe('professional')
        ->and($payload['type'])->toBe('term')
        ->and($payload['licensee'])->toBe('Jane Orienteer')
        ->and($payload['expires_at'])->toBe('2027-05-09T00:00:00Z')
        ->and($payload['device_id'])->toBe('device-1')
        ->and($payload['iat'])->toBeGreaterThanOrEqual($before)
        ->and($payload['refresh_after'])->toBe($payload['iat'] + 7 * 86400)
        ->and($payload['offline_until'])->toBe($payload['iat'] + 30 * 86400);

    expect(app(LicenseTokenService::class)->verify($token))->toBe($payload);

    $license->refresh();
    expect($license->last_activated_at)->not->toBeNull();
    $this->assertDatabaseHas('license_activations', [
        'license_id' => $license->id,
        'device_id' => 'device-1',
        'device_name' => 'MacBook Pro',
        'platform' => 'macOS',
        'app_version' => '1.4.0',
    ]);
});

test('offline_until is capped by expires_at and lifetime has null expiry', function () {
    $soon = License::factory()->create(['expires_at' => now()->addDays(5)->startOfSecond()]);
    $payload = app(LicenseTokenService::class)->verify(occaActivate($this, $soon)->json('token'));
    expect($payload['offline_until'])->toBe($soon->expires_at->getTimestamp());

    $lifetime = License::factory()->lifetime()->create();
    $response = occaActivate($this, $lifetime);
    $response->assertOk()->assertJsonPath('license.expires_at', null)->assertJsonPath('license.type', 'lifetime');
    $payload = app(LicenseTokenService::class)->verify($response->json('token'));
    expect($payload['expires_at'])->toBeNull()
        ->and($payload['offline_until'])->toBe($payload['iat'] + 30 * 86400);
});

test('unknown key returns 404 invalid_key', function () {
    $this->postJson('/api/occa/license/activate', ['key' => 'OCCA-AAAA-BBBB-CCCC-DDDD', 'device_id' => 'x', 'platform' => 'macOS'])
        ->assertStatus(404)
        ->assertExactJson(['error' => 'invalid_key', 'message' => 'This license key does not exist.']);
});

test('missing fields return 422 invalid_request', function () {
    $this->postJson('/api/occa/license/activate', [])
        ->assertStatus(422)
        ->assertJsonPath('error', 'invalid_request');
});

test('inactive license returns 403 inactive', function () {
    $license = License::factory()->inactive()->create();

    occaActivate($this, $license)->assertStatus(403)->assertJsonPath('error', 'inactive');
});

test('expired license returns 403 expired', function () {
    $license = License::factory()->expired()->create();

    occaActivate($this, $license)->assertStatus(403)->assertJsonPath('error', 'expired');
});

test('new device beyond max_devices returns 409 device_limit', function () {
    $license = License::factory()->create(['max_devices' => 2]);

    occaActivate($this, $license, 'device-1')->assertOk();
    occaActivate($this, $license, 'device-2')->assertOk()->assertJsonPath('license.devices_used', 2);
    occaActivate($this, $license, 'device-3')->assertStatus(409)->assertJsonPath('error', 'device_limit');

    expect($license->activations()->count())->toBe(2);
});

test('re-activating the same device does not consume a slot', function () {
    $license = License::factory()->create(['max_devices' => 1]);

    occaActivate($this, $license, 'device-1', ['app_version' => '1.0.0'])->assertOk();
    occaActivate($this, $license, 'device-1', ['app_version' => '1.1.0', 'device_name' => 'Renamed Mac'])
        ->assertOk()
        ->assertJsonPath('license.devices_used', 1);

    expect($license->activations()->count())->toBe(1);
    $this->assertDatabaseHas('license_activations', [
        'device_id' => 'device-1',
        'app_version' => '1.1.0',
        'device_name' => 'Renamed Mac',
    ]);
});

test('refresh returns a new token for an activated device', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1')->assertOk();

    $this->travel(2)->days();

    $response = $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1']);
    $response->assertOk()->assertJsonPath('license.devices_used', 1);

    $payload = app(LicenseTokenService::class)->verify($response->json('token'));
    expect($payload['device_id'])->toBe('device-1');
    expect(LicenseActivation::first()->last_seen_at->isToday())->toBeTrue();
});

test('refresh for a device that was never activated returns 404 not_activated', function () {
    $license = License::factory()->create();

    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'ghost'])
        ->assertStatus(404)
        ->assertJsonPath('error', 'not_activated');
});

test('refresh after the admin deactivates the license returns 403 inactive', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1')->assertOk();

    Sanctum::actingAs($this->admin);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1'])
        ->assertStatus(403)
        ->assertJsonPath('error', 'inactive');
});

test('deactivate frees the slot and is idempotent', function () {
    $license = License::factory()->create(['max_devices' => 1]);
    occaActivate($this, $license, 'device-1')->assertOk();
    occaActivate($this, $license, 'device-2')->assertStatus(409);

    $this->postJson('/api/occa/license/deactivate', ['key' => $license->key, 'device_id' => 'device-1'])
        ->assertOk()->assertExactJson(['ok' => true]);
    $this->postJson('/api/occa/license/deactivate', ['key' => $license->key, 'device_id' => 'device-1'])
        ->assertOk()->assertExactJson(['ok' => true]);
    $this->postJson('/api/occa/license/deactivate', ['key' => 'OCCA-NOPE-NOPE-NOPE-NOPE', 'device_id' => 'device-1'])
        ->assertOk()->assertExactJson(['ok' => true]);

    occaActivate($this, $license, 'device-2')->assertOk();
});

// ---------------------------------------------------------------------
// Admin API
// ---------------------------------------------------------------------

test('admin endpoints require authentication', function () {
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10')->assertStatus(401);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', [])->assertStatus(401);
});

test('admin endpoints require license-manager permissions', function () {
    $license = License::factory()->create();
    Sanctum::actingAs($this->staff);

    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10')->assertStatus(403);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}")->assertStatus(403);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', ['licensee_name' => 'X', 'type' => 'lifetime'])->assertStatus(403);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['is_active' => false])->assertStatus(403);
    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$license->id}")->assertStatus(403);
});

test('admin can create a term license with a generated key', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaOrganization([
        'licensee_name' => 'Club Orienteering Jakarta',
        'max_devices' => 3,
        'notes' => 'Paid by transfer',
    ]));

    $response->assertStatus(201)
        ->assertJsonPath('data.plan', 'organization')
        ->assertJsonPath('data.plan_label', 'Organization')
        ->assertJsonPath('data.category', 'organization')
        ->assertJsonPath('data.type', 'term')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.max_devices', 3)
        ->assertJsonPath('data.devices_used', 0);

    $key = $response->json('data.key');
    // Organization plan code 04, first license of the product.
    expect($key)->toMatch(OCCA_KEY_REGEX)->toStartWith('OC34-')->toEndWith('-0001');
    expect($response->json('data.sequence'))->toBe(1);

    $license = License::where('key', $key)->firstOrFail();
    expect($license->expires_at->isSameDay(now()->addYear()))->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'License Manager',
        'subject_type' => License::class,
        'subject_id' => $license->id,
        'event' => 'created',
    ]);
});

test('admin can create a lifetime and a custom-expiry license', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_name' => 'Owner', 'plan' => 'lifetime', 'type' => 'lifetime', 'duration' => null]))
        ->assertStatus(201)
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.max_devices', 2);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_name' => 'Custom', 'duration' => 'custom']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expires_at']);

    $response = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual([
        'licensee_name' => 'Custom',
        'duration' => 'custom',
        'expires_at' => now()->addMonths(3)->toDateString(),
    ]))->assertStatus(201);

    expect(License::find($response->json('data.id'))->expires_at->isSameDay(now()->addMonths(3)))->toBeTrue();
});

test('admin update extends expiry and cannot change the key', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->create(['expires_at' => now()->addMonth()]);
    $originalKey = $license->key;

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", [
        'key' => 'OCCA-HACK-HACK-HACK-HACK',
        'duration' => '6_months',
        'max_devices' => 4,
    ])->assertOk()
        ->assertJsonPath('data.key', $originalKey)
        ->assertJsonPath('data.max_devices', 4);

    // A preset on update counts from today.
    expect($license->fresh()->expires_at->isSameDay(now()->addMonthsNoOverflow(6)))->toBeTrue();
});

test('status toggle resending a past expires_at is accepted on update', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->expired()->create();
    $pastDate = $license->expires_at->toDateString();

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", [
        'licensee_name' => $license->licensee_name,
        'licensee_email' => $license->licensee_email,
        'type' => 'term',
        'duration' => 'custom',
        'expires_at' => $pastDate,
        'max_devices' => $license->max_devices,
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_expired', true);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", [
        'type' => 'term',
        'duration' => null,
        'expires_at' => $pastDate,
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.status', 'expired');

    expect($license->fresh()->expires_at->toDateString())->toBe($pastDate);
});

test('validation errors use the standard 422 errors shape', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', ['type' => 'term', 'duration' => 'custom', 'expires_at' => '09/05/2027'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['licensee_name', 'expires_at']);
});

test('admin can list with search and filters, and show includes devices', function () {
    Sanctum::actingAs($this->admin);
    $active = License::factory()->create(['licensee_name' => 'Alpha Club']);
    License::factory()->inactive()->create(['licensee_name' => 'Beta Club']);
    License::factory()->expired()->create(['licensee_name' => 'Gamma Club']);
    License::factory()->lifetime()->create(['licensee_name' => 'Delta Club']);
    LicenseActivation::factory()->create(['license_id' => $active->id, 'device_id' => 'dev-a']);

    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10')
        ->assertOk()->assertJsonPath('data.meta.total', 4);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&search=alpha')
        ->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.data.0.devices_used', 1);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&search='.substr($active->key, 5, 9))
        ->assertOk()->assertJsonPath('data.data.0.id', $active->id);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&status=active')
        ->assertOk()->assertJsonPath('data.meta.total', 2);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&status=inactive')
        ->assertOk()->assertJsonPath('data.data.0.licensee_name', 'Beta Club');
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&status=expired')
        ->assertOk()->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.status', 'expired')
        ->assertJsonPath('data.data.0.is_expired', true);
    // Expired = term AND expires_at < now, even when also switched off.
    License::factory()->expired()->inactive()->create(['licensee_name' => 'Epsilon Club']);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=10&status=expired')
        ->assertOk()->assertJsonPath('data.meta.total', 2);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.meta.per_page', 2)
        ->assertJsonPath('data.meta.total', 5)
        ->assertJsonPath('data.meta.last_page', 3)
        ->assertJsonStructure(['data' => ['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']]]);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&type=lifetime')
        ->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.data.0.licensee_name', 'Delta Club');

    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$active->id}")
        ->assertOk()
        ->assertJsonPath('data.devices_used', 1)
        ->assertJsonPath('data.is_expired', false)
        ->assertJsonPath('data.activations.0.device_id', 'dev-a');
});

test('plan is required on store and must be a known plan', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', array_diff_key(occaIndividual(['licensee_name' => 'No Plan']), ['plan' => true]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan']);

    foreach (['pro', 'starter', 'free', 'Professional', 'enterprise'] as $plan) {
        $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_name' => 'Bad Plan', 'plan' => $plan]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    expect(License::count())->toBe(0);

    foreach (['professional' => ['Professional', 'individual', 'term'], 'trial' => ['Trial', 'individual', 'term'], 'lifetime' => ['Lifetime', 'individual', 'lifetime'], 'organization' => ['Organization', 'organization', 'term']] as $plan => [$label, $category, $type]) {
        $payload = $category === 'organization' ? occaOrganization() : occaIndividual();
        $this->postJson('/api/v1/license-manager/products/occa/licenses', array_merge($payload, ['licensee_name' => 'Plan '.$plan, 'plan' => $plan, 'type' => $type, 'duration' => $type === 'term' ? '1_year' : null]))
            ->assertStatus(201)
            ->assertJsonPath('data.plan', $plan)
            ->assertJsonPath('data.plan_label', $label)
            ->assertJsonPath('data.category', $category);
    }
});

test('plan can be changed on update and is kept when omitted', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->create();

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['max_devices' => 3])
        ->assertOk()->assertJsonPath('data.plan', 'professional');

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'pro'])
        ->assertStatus(422)->assertJsonValidationErrors(['plan']);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'enterprise'])
        ->assertStatus(422)->assertJsonValidationErrors(['plan']);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'organization', 'organization_type' => 'federation', 'contact_name' => 'Ana', 'address' => 'Jl. Merdeka 2'])
        ->assertOk()
        ->assertJsonPath('data.plan', 'organization')
        ->assertJsonPath('data.plan_label', 'Organization')
        ->assertJsonPath('data.category', 'organization');

    expect($license->fresh()->plan)->toBe('organization');
});

test('the dto defaults a missing plan to professional', function () {
    $license = app(LicenseRepositoryInterface::class)->create(LicenseProduct::findByCodeOrFail('occa'), [
        'licensee_name' => 'Internal',
        'type' => 'term',
        'duration' => '1_year',
    ]);

    expect($license->plan)->toBe('professional');
});

test('legacy pro rows are renamed to professional by the plan migration', function () {
    $legacy = License::factory()->create();
    $other = License::factory()->plan('lifetime')->create();
    DB::table('licenses')->where('id', $legacy->id)->update(['plan' => 'pro']);

    // The legacy migration targets the table under its old name.
    Schema::rename('licenses', 'occa_licenses');
    $migration = require base_path('database/migrations/2026_11_09_000003_rename_occa_license_plan_pro_to_professional.php');
    $migration->up();
    Schema::rename('occa_licenses', 'licenses');

    expect($legacy->fresh()->plan)->toBe('professional')
        ->and($other->fresh()->plan)->toBe('lifetime');
});

test('a trial license is saved, is individual, and is carried in the token', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual([
        'licensee_name' => 'Trial User',
        'plan' => 'trial',
        'duration' => '14_days',
        'max_devices' => 1,
    ]))->assertStatus(201)
        ->assertJsonPath('data.plan', 'trial')
        ->assertJsonPath('data.plan_label', 'Trial')
        ->assertJsonPath('data.category', 'individual');

    $license = License::findOrFail($response->json('data.id'));
    expect($license->expires_at->isSameDay(now()->addDays(14)))->toBeTrue();

    $activation = occaActivate($this, $license)->assertOk()->assertJsonPath('license.plan', 'trial');
    expect(app(LicenseTokenService::class)->verify($activation->json('token'))['plan'])->toBe('trial');
});

test('the type follows the plan and a sent type is ignored', function () {
    Sanctum::actingAs($this->admin);

    // Any client type is ignored: trial / professional / organization are term.
    foreach (['trial' => occaIndividual(['plan' => 'trial']), 'professional' => occaIndividual(), 'organization' => occaOrganization()] as $plan => $payload) {
        $id = $this->postJson('/api/v1/license-manager/products/occa/licenses', array_merge($payload, ['type' => 'lifetime', 'duration' => '30_days']))
            ->assertStatus(201)->assertJsonPath('data.type', 'term')->json('data.id');
        expect(License::find($id)->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
    }

    // The Lifetime plan is lifetime, whatever duration / type is sent.
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['plan' => 'lifetime', 'type' => 'term', 'duration' => '1_year']))
        ->assertStatus(201)->assertJsonPath('data.type', 'lifetime')->assertJsonPath('data.expires_at', null);

    // A term plan needs a duration or an expiry date.
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['duration' => null]))
        ->assertStatus(422)->assertJsonValidationErrors(['duration']);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaOrganization(['duration' => null, 'expires_at' => null]))
        ->assertStatus(422)->assertJsonValidationErrors(['duration']);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['duration' => null, 'expires_at' => now()->addMonths(2)->toDateString()]))
        ->assertStatus(201);

    // Update: moving to the Lifetime plan drops the expiry; moving back to a
    // term plan needs a duration or a date.
    $license = License::factory()->create();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'lifetime'])
        ->assertOk()->assertJsonPath('data.type', 'lifetime')->assertJsonPath('data.expires_at', null);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'trial'])
        ->assertStatus(422)->assertJsonValidationErrors(['duration']);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'trial', 'duration' => '30_days'])
        ->assertOk()->assertJsonPath('data.type', 'term');
    expect($license->fresh()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    // A type sent without a plan is ignored.
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['type' => 'lifetime'])
        ->assertOk()->assertJsonPath('data.type', 'term');
});

test('the 14_days and 30_days duration presets work for any term license', function () {
    Sanctum::actingAs($this->admin);

    $fourteen = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_name' => 'A', 'duration' => '14_days']))
        ->assertStatus(201);
    $thirty = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaOrganization(['licensee_name' => 'B', 'duration' => '30_days']))
        ->assertStatus(201);

    expect(License::find($fourteen->json('data.id'))->expires_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and(License::find($thirty->json('data.id'))->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    // On update a preset counts from today.
    $license = License::factory()->create(['expires_at' => now()->addYear()]);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['duration' => '30_days'])->assertOk();
    expect($license->fresh()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_name' => 'C', 'duration' => '7_days']))
        ->assertStatus(422)->assertJsonValidationErrors(['duration']);
});

test('admin can filter the list by plan', function () {
    Sanctum::actingAs($this->admin);
    License::factory()->create(['licensee_name' => 'Pro One']);
    License::factory()->create(['licensee_name' => 'Pro Two']);
    License::factory()->plan('lifetime')->create(['licensee_name' => 'Owner']);
    License::factory()->plan('organization')->create(['licensee_name' => 'Federation']);

    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&plan=professional')
        ->assertOk()->assertJsonPath('data.meta.total', 2);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&plan=lifetime')
        ->assertOk()->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.licensee_name', 'Owner')
        ->assertJsonPath('data.data.0.plan_label', 'Lifetime')
        ->assertJsonPath('data.data.0.category', 'individual');
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&plan=organization&type=term')
        ->assertOk()->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.licensee_name', 'Federation')
        ->assertJsonPath('data.data.0.category', 'organization');
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&plan=pro')
        ->assertStatus(422);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?row_per_page=10&plan=enterprise')
        ->assertStatus(422);
});

test('the token and license object carry the stored plan', function () {
    foreach (['lifetime', 'organization'] as $plan) {
        $license = License::factory()->plan($plan)->create();
        $response = occaActivate($this, $license)->assertOk()->assertJsonPath('license.plan', $plan);

        expect(app(LicenseTokenService::class)->verify($response->json('token'))['plan'])->toBe($plan);
    }
});

test('admin can release a device, freeing its slot', function () {
    $license = License::factory()->create(['max_devices' => 1]);
    occaActivate($this, $license, 'device-1')->assertOk();
    $activation = $license->activations()->first();

    Sanctum::actingAs($this->staff);
    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/activations/{$activation->id}")->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $other = License::factory()->create();
    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$other->id}/activations/{$activation->id}")->assertStatus(404);

    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/activations/{$activation->id}")
        ->assertOk()
        ->assertJsonPath('data.devices_used', 0);

    $this->assertDatabaseMissing('license_activations', ['id' => $activation->id]);

    // The released device can no longer refresh; another device can activate.
    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1'])
        ->assertStatus(404)->assertJsonPath('error', 'not_activated');
    occaActivate($this, $license, 'device-2')->assertOk();
});

test('admin can delete a license and its key stops working', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->create();

    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$license->id}")->assertOk();
    $this->assertSoftDeleted('licenses', ['id' => $license->id]);

    occaActivate($this, $license)->assertStatus(404)->assertJsonPath('error', 'invalid_key');
});

// ---------------------------------------------------------------------
// Lifetime plan
// ---------------------------------------------------------------------

test('legacy enterprise rows are renamed to lifetime by the plan migration', function () {
    $legacy = License::factory()->plan('lifetime')->create();
    $other = License::factory()->create();
    DB::table('licenses')->where('id', $legacy->id)->update(['plan' => 'enterprise']);

    // The legacy migration targets the table under its old name.
    Schema::rename('licenses', 'occa_licenses');
    $migration = require base_path('database/migrations/2026_11_09_000006_rename_occa_license_plan_enterprise_to_lifetime.php');
    $migration->up();
    Schema::rename('occa_licenses', 'licenses');

    expect($legacy->fresh()->plan)->toBe('lifetime')
        ->and($legacy->fresh()->planLabel())->toBe('Lifetime')
        ->and($other->fresh()->plan)->toBe('professional');
});

// ---------------------------------------------------------------------
// Category-specific licensee fields
// ---------------------------------------------------------------------

test('individual store requires name, email, phone and the location, and defaults the country', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', ['plan' => 'professional', 'type' => 'term', 'duration' => '1_year'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['licensee_name', 'licensee_email', 'phone', 'province_code', 'regency_code', 'district_code', 'village_code', 'postal_code'])
        ->assertJsonMissingValidationErrors(['country', 'organization_type', 'contact_name', 'address', 'occupation']);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['licensee_email' => 'nope', 'phone' => '12ab', 'country' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['licensee_email', 'phone']);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['phone' => '12345']))
        ->assertStatus(422)->assertJsonValidationErrors(['phone']);
    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['phone' => str_repeat('1', 21)]))
        ->assertStatus(422)->assertJsonValidationErrors(['phone']);

    $response = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual([
        'occupation' => 'Course setter',
        'affiliation' => 'Universitas Indonesia',
        // Organization-only fields sent for an individual are dropped.
        'organization_type' => 'club',
        'contact_name' => 'Someone',
        'website' => 'https://example.com',
    ]))->assertStatus(201)
        ->assertJsonPath('data.phone', '+62 812-1111-2222')
        ->assertJsonPath('data.regency', 'Kota Bandung')
        ->assertJsonPath('data.district', 'Sukasari')
        ->assertJsonPath('data.village', 'Sukarasa')
        ->assertJsonPath('data.province', 'Jawa Barat')
        ->assertJsonPath('data.full_address', 'Sukarasa, Sukasari, Kota Bandung, Jawa Barat 40151, Indonesia')
        ->assertJsonPath('data.country', 'Indonesia')
        ->assertJsonPath('data.occupation', 'Course setter')
        ->assertJsonPath('data.affiliation', 'Universitas Indonesia')
        ->assertJsonPath('data.organization_type', null)
        ->assertJsonPath('data.contact_name', null)
        ->assertJsonPath('data.website', null);

    expect(License::find($response->json('data.id'))->starts_at)->not->toBeNull();
});

test('organization store requires its own fields and validates them', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', ['plan' => 'organization', 'type' => 'term', 'duration' => '1_year'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['licensee_name', 'organization_type', 'contact_name', 'licensee_email', 'phone', 'address', 'village_code', 'postal_code'])
        ->assertJsonMissingValidationErrors(['country', 'contact_position', 'website', 'tax_id', 'member_count']);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaOrganization(['organization_type' => 'company', 'website' => 'example', 'member_count' => 0]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_type', 'website', 'member_count']);

    $this->postJson('/api/v1/license-manager/products/occa/licenses', occaOrganization([
        'contact_position' => 'Secretary',
        'website' => 'https://ojc.example.org',
        'tax_id' => '01.234.567.8-901.000',
        'member_count' => 120,
        // Outside Indonesia: free-text names, no codes.
        'country' => 'Malaysia',
        'province' => 'Selangor',
        'regency' => 'Shah Alam',
        'district' => 'Seksyen 7',
        'postal_code' => '40000',
        'occupation' => 'Mapper',
    ]))->assertStatus(201)
        ->assertJsonPath('data.province', 'Selangor')
        ->assertJsonPath('data.regency', 'Shah Alam')
        ->assertJsonPath('data.village_code', null)
        ->assertJsonPath('data.province_code', null)
        ->assertJsonPath('data.full_address', 'Jl. Sudirman 1, Seksyen 7, Shah Alam, Selangor 40000, Malaysia')
        ->assertJsonPath('data.category', 'organization')
        ->assertJsonPath('data.organization_type', 'club')
        ->assertJsonPath('data.organization_type_label', 'Club')
        ->assertJsonPath('data.contact_name', 'Budi Santoso')
        ->assertJsonPath('data.contact_position', 'Secretary')
        ->assertJsonPath('data.address', 'Jl. Sudirman 1')
        ->assertJsonPath('data.website', 'https://ojc.example.org')
        ->assertJsonPath('data.tax_id', '01.234.567.8-901.000')
        ->assertJsonPath('data.member_count', 120)
        ->assertJsonPath('data.country', 'Malaysia')
        ->assertJsonPath('data.occupation', null);
});

test('update validates sent fields against the stored or new category', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->create();

    // Partial updates keep working; sent fields may not be blanked.
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['is_active' => false])->assertOk();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['phone' => '', 'postal_code' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['phone', 'postal_code']);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['licensee_email' => 'nope'])
        ->assertStatus(422)->assertJsonValidationErrors(['licensee_email']);

    // Moving to organization requires the organization's own fields.
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'organization'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_type', 'contact_name', 'address'])
        ->assertJsonMissingValidationErrors(['licensee_email', 'phone', 'village_code', 'country']);

    // An organization stays an organization when plan is not sent.
    $org = License::factory()->plan('organization')->create();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$org->id}", ['contact_name' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['contact_name']);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$org->id}", ['organization_type' => 'federation'])
        ->assertOk()->assertJsonPath('data.organization_type_label', 'Federation');
});

test('category-only fields are cleared when the category changes', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->create(['occupation' => 'Coach', 'affiliation' => 'School X', 'phone' => '0812 0000 0000', 'regency' => 'Kota Administrasi Jakarta Pusat']);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", [
        'plan' => 'organization',
        'organization_type' => 'community',
        'contact_name' => 'Rina',
        'address' => 'Jl. Slamet Riyadi 5',
        'member_count' => 30,
    ])->assertOk()
        ->assertJsonPath('data.occupation', null)
        ->assertJsonPath('data.affiliation', null)
        // Shared fields carry over.
        ->assertJsonPath('data.phone', '0812 0000 0000')
        ->assertJsonPath('data.regency', 'Kota Administrasi Jakarta Pusat')
        ->assertJsonPath('data.member_count', 30);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'professional', 'occupation' => 'Mapper'])
        ->assertOk()
        ->assertJsonPath('data.category', 'individual')
        ->assertJsonPath('data.occupation', 'Mapper')
        ->assertJsonPath('data.organization_type', null)
        ->assertJsonPath('data.contact_name', null)
        // The street address is part of the shared location.
        ->assertJsonPath('data.address', 'Jl. Slamet Riyadi 5')
        ->assertJsonPath('data.member_count', null)
        ->assertJsonPath('data.regency', 'Kota Administrasi Jakarta Pusat');
});

// ---------------------------------------------------------------------
// Active period
// ---------------------------------------------------------------------

test('the resource reports duration, remaining days, elapsed percent and status', function () {
    Sanctum::actingAs($this->admin);
    $this->freezeTime();

    $term = License::factory()->create(['starts_at' => now()->subDays(30), 'expires_at' => now()->addDays(70)]);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$term->id}")->assertOk()
        ->assertJsonPath('data.duration_days', 100)
        ->assertJsonPath('data.remaining_days', 70)
        ->assertJsonPath('data.elapsed_percent', 30)
        ->assertJsonPath('data.status', 'active');

    // Partial days round up; 14 days or fewer is expiring_soon.
    $soon = License::factory()->create(['starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(13)->addHours(5)]);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$soon->id}")->assertOk()
        ->assertJsonPath('data.remaining_days', 14)
        ->assertJsonPath('data.status', 'expiring_soon');

    $fifteen = License::factory()->create(['expires_at' => now()->addDays(15)]);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$fifteen->id}")->assertJsonPath('data.status', 'active');

    // An inactive license that is about to expire is inactive, not expiring_soon.
    $offSoon = License::factory()->inactive()->create(['expires_at' => now()->addDays(3)]);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$offSoon->id}")->assertJsonPath('data.status', 'inactive');

    $expired = License::factory()->expired()->create();
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$expired->id}")->assertOk()
        ->assertJsonPath('data.remaining_days', 0)
        ->assertJsonPath('data.elapsed_percent', 100)
        ->assertJsonPath('data.status', 'expired');

    $lifetime = License::factory()->plan('lifetime')->create();
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$lifetime->id}")->assertOk()
        ->assertJsonPath('data.duration_days', null)
        ->assertJsonPath('data.remaining_days', null)
        ->assertJsonPath('data.elapsed_percent', null)
        ->assertJsonPath('data.status', 'active');

    // Filter and sort.
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=20&status=expiring_soon')
        ->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.data.0.id', $soon->id);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=20&status=active')
        ->assertOk()->assertJsonPath('data.meta.total', 3);

    $asc = $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=20&sort_by=expires_at&sort_dir=asc')->assertOk()->json('data.data');
    expect(array_column($asc, 'id'))->toBe([$expired->id, $offSoon->id, $soon->id, $fifteen->id, $term->id, $lifetime->id]);
    $desc = $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?per_page=20&sort_by=expires_at&sort_dir=desc')->assertOk()->json('data.data');
    expect(array_column($desc, 'id'))->toBe([$term->id, $fifteen->id, $soon->id, $offSoon->id, $expired->id, $lifetime->id]);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated?sort_by=licensee_name')->assertStatus(422);
});

test('a preset extension sets a missing starts_at to today', function () {
    Sanctum::actingAs($this->admin);
    $license = License::factory()->lifetime()->create(['starts_at' => null]);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['plan' => 'professional', 'duration' => '30_days'])->assertOk()
        ->assertJsonPath('data.duration_days', 30)
        ->assertJsonPath('data.remaining_days', 30);

    expect($license->fresh()->starts_at->isToday())->toBeTrue();
});

test('the summary endpoint counts statuses and plans', function () {
    License::factory()->count(2)->create();
    License::factory()->create(['expires_at' => now()->addDays(5)]);
    License::factory()->expired()->create();
    License::factory()->inactive()->create();
    License::factory()->plan('lifetime')->create();
    License::factory()->plan('organization')->create();
    License::factory()->plan('trial')->create()->delete();

    Sanctum::actingAs($this->staff);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/summary')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/summary')->assertOk()
        ->assertJsonPath('data.total', 7)
        ->assertJsonPath('data.active', 4)
        ->assertJsonPath('data.expiring_soon', 1)
        ->assertJsonPath('data.expired', 1)
        ->assertJsonPath('data.inactive', 1)
        ->assertJsonPath('data.by_plan', ['professional' => 5, 'trial' => 0, 'lifetime' => 1, 'organization' => 1]);
});

// ---------------------------------------------------------------------
// Tracking events
// ---------------------------------------------------------------------

function occaEvents(License $license, ?string $event = null)
{
    return LicenseEvent::where('license_id', $license->id)
        ->when($event, fn ($q) => $q->where('event', $event))
        ->orderBy('id')
        ->get();
}

test('admin create, update, extend, toggle, plan change and delete are tracked', function () {
    Sanctum::actingAs($this->admin);

    $id = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual())->assertStatus(201)->json('data.id');
    $license = License::find($id);
    $created = occaEvents($license, 'created')->sole();
    expect($created->user_id)->toBe($this->admin->id)
        ->and($created->ip)->toBe('127.0.0.1')
        ->and($created->meta['plan'])->toBe('professional')
        ->and($created->meta['source'])->toBe('admin');

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['postal_code' => '40152', 'max_devices' => 4])->assertOk();
    $updated = occaEvents($license, 'updated')->sole();
    expect($updated->meta['changes'])->toEqual([
        'postal_code' => ['old' => '40151', 'new' => '40152'],
        'max_devices' => ['old' => 2, 'new' => 4],
    ])->and($updated->user_id)->toBe($this->admin->id);

    $oldExpiry = $license->fresh()->expires_at;
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['duration' => 'custom', 'expires_at' => now()->addYears(2)->toDateString()])->assertOk();
    $extended = occaEvents($license, 'extended')->sole();
    expect($extended->meta['old_expires_at'])->toBe($oldExpiry->utc()->toIso8601ZuluString())
        ->and($extended->meta['new_expires_at'])->toStartWith(now()->addYears(2)->toDateString());

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['is_active' => false])->assertOk();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['is_active' => true])->assertOk();
    expect(occaEvents($license, 'deactivated_by_admin'))->toHaveCount(1)
        ->and(occaEvents($license, 'activated_by_admin'))->toHaveCount(1);

    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['plan' => 'trial'])->assertOk();
    expect(occaEvents($license, 'plan_changed')->sole()->meta)->toBe(['old_plan' => 'professional', 'new_plan' => 'trial']);

    // Toggling does not also write an "updated" event; saving unchanged data writes nothing.
    $count = occaEvents($license)->count();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$id}", ['postal_code' => '40152'])->assertOk();
    expect(occaEvents($license)->count())->toBe($count)
        ->and(occaEvents($license, 'updated'))->toHaveCount(1);

    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$id}")->assertOk();
    expect(occaEvents($license, 'deleted')->sole()->meta['key'])->toBe($license->key);
});

test('device activation, re-activation and app release are tracked', function () {
    $license = License::factory()->create(['max_devices' => 2]);

    occaActivate($this, $license, 'device-1')->assertOk();
    occaActivate($this, $license, 'device-1', ['app_version' => '1.5.0'])->assertOk();

    $activated = occaEvents($license, 'device_activated')->sole();
    expect($activated->device_id)->toBe('device-1')
        ->and($activated->device_name)->toBe('MacBook Pro')
        ->and($activated->platform)->toBe('macOS')
        ->and($activated->app_version)->toBe('1.4.0')
        ->and($activated->ip)->toBe('127.0.0.1')
        ->and($activated->user_id)->toBeNull();
    expect(occaEvents($license, 'device_reactivated')->sole()->app_version)->toBe('1.5.0');

    $this->postJson('/api/occa/license/deactivate', ['key' => $license->key, 'device_id' => 'device-1'])->assertOk();
    $this->postJson('/api/occa/license/deactivate', ['key' => $license->key, 'device_id' => 'device-1'])->assertOk();
    $released = occaEvents($license, 'device_released')->sole();
    expect($released->device_name)->toBe('MacBook Pro');
});

test('admin device release is tracked with the admin', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-9', ['device_name' => 'MacBook Air', 'platform' => 'macOS'])->assertOk();
    $activation = $license->activations()->first();

    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/activations/{$activation->id}")->assertOk();

    $event = occaEvents($license, 'device_released_by_admin')->sole();
    expect($event->device_id)->toBe('device-9')
        ->and($event->device_name)->toBe('MacBook Air')
        ->and($event->user_id)->toBe($this->admin->id);
});

test('refresh is logged at most once per device per 24 hours', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1')->assertOk();
    occaActivate($this, $license, 'device-2')->assertOk();
    $refresh = fn (string $device) => $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => $device])->assertOk();

    $refresh('device-1');
    $this->travel(2)->hours();
    $refresh('device-1');
    $refresh('device-2');
    expect(occaEvents($license, 'refreshed'))->toHaveCount(2);
    // last_seen_at still moves on every refresh.
    expect($license->activations()->where('device_id', 'device-1')->first()->last_seen_at->timestamp)->toBe(now()->timestamp);

    $this->travel(23)->hours();
    $refresh('device-1');
    expect(occaEvents($license, 'refreshed')->where('device_id', 'device-1'))->toHaveCount(2);
});

test('denied activate and refresh attempts are tracked with device and ip', function () {
    $full = License::factory()->create(['max_devices' => 1]);
    occaActivate($this, $full, 'device-1')->assertOk();
    occaActivate($this, $full, 'device-2', ['device_name' => 'Borrowed Mac'])->assertStatus(409);
    $limit = occaEvents($full, 'denied_device_limit')->sole();
    expect($limit->device_id)->toBe('device-2')
        ->and($limit->device_name)->toBe('Borrowed Mac')
        ->and($limit->ip)->toBe('127.0.0.1');

    $inactive = License::factory()->create();
    occaActivate($this, $inactive, 'device-1')->assertOk();
    $inactive->update(['is_active' => false]);
    occaActivate($this, $inactive, 'device-3')->assertStatus(403);
    $this->postJson('/api/occa/license/refresh', ['key' => $inactive->key, 'device_id' => 'device-1'])->assertStatus(403);
    $denied = occaEvents($inactive, 'denied_inactive');
    expect($denied)->toHaveCount(2)
        ->and($denied[0]->device_id)->toBe('device-3')
        ->and($denied[1]->device_id)->toBe('device-1')
        // Refresh only sends device_id; the name comes from the activation.
        ->and($denied[1]->device_name)->toBe('MacBook Pro');

    $expired = License::factory()->expired()->create();
    occaActivate($this, $expired, 'device-4')->assertStatus(403);
    $this->postJson('/api/occa/license/refresh', ['key' => $expired->key, 'device_id' => 'device-4'])->assertStatus(403);
    expect(occaEvents($expired, 'denied_expired'))->toHaveCount(2);

    // Unknown keys have no license to attach to.
    $this->postJson('/api/occa/license/activate', ['key' => 'OCCA-AAAA-BBBB-CCCC-DDDD', 'device_id' => 'x', 'platform' => 'macOS'])->assertStatus(404);
    expect(LicenseEvent::whereNotIn('license_id', [$full->id, $inactive->id, $expired->id])->count())->toBe(0);
});

test('a logging failure never breaks activation', function () {
    $license = License::factory()->create();
    Schema::drop('license_events');

    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
        $reported[] = $e;

        return false;
    });

    occaActivate($this, $license, 'device-1')->assertOk()->assertJsonPath('license.devices_used', 1);
    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1'])->assertOk();

    expect($license->activations()->count())->toBe(1)
        ->and($reported)->not->toBeEmpty();
});

test('the events endpoint is paginated, newest first, filterable and permission gated', function () {
    $license = License::factory()->create();
    Sanctum::actingAs($this->admin);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['is_active' => false])->assertOk();
    $this->travel(1)->minutes();
    $this->putJson("/api/v1/license-manager/products/occa/licenses/{$license->id}", ['is_active' => true])->assertOk();
    $this->travel(1)->minutes();
    occaActivate($this, $license, 'device-1')->assertOk();
    LicenseEvent::create(['license_id' => License::factory()->create()->id, 'event' => 'created', 'created_at' => now()]);

    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/events?per_page=2")->assertOk()
        ->assertJsonPath('data.meta.total', 3)
        ->assertJsonPath('data.meta.last_page', 2)
        ->assertJsonPath('data.data.0.event', 'device_activated')
        ->assertJsonPath('data.data.0.label', 'Device activated')
        ->assertJsonPath('data.data.0.user_name', null)
        ->assertJsonPath('data.data.0.device_id', 'device-1')
        ->assertJsonPath('data.data.1.event', 'activated_by_admin')
        ->assertJsonPath('data.data.1.user_id', $this->admin->id)
        ->assertJsonPath('data.data.1.user_name', $this->admin->name);

    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/events?event=deactivated_by_admin")->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.event', 'deactivated_by_admin');
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/events?event=bogus")->assertStatus(422);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/999999/events')->assertStatus(404);

    Sanctum::actingAs($this->staff);
    $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}/events")->assertStatus(403);
});

test('show reports the last activity', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1')->assertOk();
    $this->travel(3)->hours();
    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1'])->assertOk();

    Sanctum::actingAs($this->admin);
    $lastActivity = $this->getJson("/api/v1/license-manager/products/occa/licenses/{$license->id}")->assertOk()->json('data.last_activity_at');
    expect(Carbon::parse($lastActivity)->timestamp)->toBe(now()->timestamp);
    $this->getJson('/api/v1/license-manager/products/occa/licenses/all/paginated')->assertOk()->assertJsonPath('data.data.0.last_activity_at', $lastActivity);
});

// ---------------------------------------------------------------------
// Artisan commands
// ---------------------------------------------------------------------

const OCCA_CLI_PERSON = ['--email' => 'person@example.com', '--phone' => '0812 3456 7890', '--village-code' => '32.73.01.1001', '--postal-code' => '40151'];

test('occa:license-create creates a lifetime license', function () {
    $this->artisan('occa:license-create', ['--name' => 'Rizky Ajie Kurniawan', '--plan' => 'lifetime', '--max-devices' => 5] + OCCA_CLI_PERSON)
        ->assertSuccessful();

    $license = License::where('licensee_name', 'Rizky Ajie Kurniawan')->firstOrFail();
    expect($license->plan)->toBe('lifetime')
        ->and($license->type)->toBe('lifetime')
        ->and($license->expires_at)->toBeNull()
        ->and($license->max_devices)->toBe(5)
        ->and($license->country)->toBe('Indonesia')
        ->and($license->starts_at)->not->toBeNull();

    $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'event' => 'created', 'user_id' => null]);
    expect($license->events()->first()->meta['source'])->toBe('cli');
});

test('occa:license-create requires exactly one of --lifetime or --months', function () {
    $this->artisan('occa:license-create', ['--name' => 'X'] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'X', '--plan' => 'organization', '--lifetime' => true, '--months' => 6])->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'X', '--lifetime' => true, '--months' => 6] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'X', '--months' => 6] + OCCA_CLI_PERSON)->assertSuccessful();

    expect(License::first()->expires_at->isSameDay(now()->addMonths(6)))->toBeTrue();
});

test('occa:license-create accepts and validates --plan', function () {
    $this->artisan('occa:license-create', ['--name' => 'Rizky Ajie Kurniawan', '--plan' => 'lifetime', '--lifetime' => true, '--max-devices' => 5] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('Lifetime')
        ->assertSuccessful();

    $owner = License::where('licensee_name', 'Rizky Ajie Kurniawan')->firstOrFail();
    expect($owner->plan)->toBe('lifetime')
        ->and($owner->type)->toBe('lifetime')
        ->and($owner->max_devices)->toBe(5);

    $this->artisan('occa:license-create', [
        '--name' => 'Club', '--plan' => 'organization', '--months' => 12,
        '--org-type' => 'club', '--contact-name' => 'Budi', '--email' => 'club@example.com',
        '--phone' => '021-555-1234', '--address' => 'Jl. Sudirman 1', '--village-code' => '31.71.01.1002', '--postal-code' => '10150',
    ])->assertSuccessful();
    expect(License::where('licensee_name', 'Club')->firstOrFail()->plan)->toBe('organization');

    foreach (['pro', 'starter', 'enterprise'] as $plan) {
        $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => $plan, '--lifetime' => true] + OCCA_CLI_PERSON)->assertFailed();
    }
    // Lifetime plan is never term; --lifetime is only for the Lifetime plan.
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'lifetime', '--months' => 12] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'professional', '--lifetime' => true] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('--lifetime is only for --plan=lifetime')
        ->assertFailed();
    $this->artisan('occa:license-create', [
        '--name' => 'Bad', '--plan' => 'organization', '--lifetime' => true,
        '--org-type' => 'club', '--contact-name' => 'Budi', '--address' => 'Jl. Sudirman 1',
    ] + OCCA_CLI_PERSON)->assertFailed();
    expect(License::where('licensee_name', 'Bad')->exists())->toBeFalse();
});

test('occa:license-create makes trials with --days or --months, never lifetime', function () {
    $this->artisan('occa:license-create', ['--name' => 'Trial Days', '--plan' => 'trial', '--days' => 14] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('Trial')
        ->assertSuccessful();
    $trial = License::where('licensee_name', 'Trial Days')->firstOrFail();
    expect($trial->plan)->toBe('trial')
        ->and($trial->type)->toBe('term')
        ->and($trial->expires_at->isSameDay(now()->addDays(14)))->toBeTrue();

    $this->artisan('occa:license-create', ['--name' => 'Trial Months', '--plan' => 'trial', '--months' => 1] + OCCA_CLI_PERSON)->assertSuccessful();
    expect(License::where('licensee_name', 'Trial Months')->firstOrFail()->plan)->toBe('trial');

    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'trial', '--lifetime' => true] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'trial'] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--months' => 1] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--lifetime' => true] + OCCA_CLI_PERSON)->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 0] + OCCA_CLI_PERSON)->assertFailed();
    expect(License::where('licensee_name', 'Bad')->exists())->toBeFalse();

    // --days works for any term plan.
    $this->artisan('occa:license-create', ['--name' => 'Pro Days', '--days' => 30] + OCCA_CLI_PERSON)->assertSuccessful();
    expect(License::where('licensee_name', 'Pro Days')->firstOrFail()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('occa:license-create validates licensee details with the HTTP rules', function () {
    // The owner's key: name, email, phone, village code and postal code.
    $this->artisan('occa:license-create', ['--name' => 'Rizky Ajie Kurniawan', '--email' => 'rizky@example.com', '--phone' => '+62 812-0000-1111', '--village-code' => '31.71.01.1001', '--postal-code' => '10110', '--plan' => 'lifetime'])
        ->assertSuccessful();
    $owner = License::where('licensee_name', 'Rizky Ajie Kurniawan')->firstOrFail();
    expect($owner->regency)->toBe('Kota Administrasi Jakarta Pusat')->and($owner->district_code)->toBe('31.71.01');

    // An unknown village code, and a non-Indonesian address with free text.
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--email' => 'b@example.com', '--phone' => '0812345678', '--village-code' => '31.71.01.9999', '--postal-code' => '10110'])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Abroad', '--days' => 14, '--email' => 'a@example.com', '--phone' => '0812345678', '--country' => 'Malaysia', '--province' => 'Selangor', '--city' => 'Shah Alam', '--postal-code' => '40000'])
        ->assertSuccessful();
    expect(License::where('licensee_name', 'Abroad')->firstOrFail()->fullAddress())->toBe('Shah Alam, Selangor 40000, Malaysia');

    // Missing / malformed individual fields, errors name the options.
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--email' => 'bad@example.com', '--village-code' => '32.73.01.1001', '--postal-code' => '40151'])
        ->expectsOutputToContain('--phone')
        ->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--email' => 'not-an-email', '--phone' => '0812', '--village-code' => '32.73.01.1001', '--postal-code' => '40151'])
        ->expectsOutputToContain('--email')
        ->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--days' => 14, '--phone' => 'call me', '--village-code' => '32.73.01.1001', '--postal-code' => '40151', '--email' => 'b@example.com'])
        ->assertFailed();

    // Organization needs its own fields.
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'organization', '--months' => 12] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('--org-type')
        ->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'organization', '--months' => 12, '--org-type' => 'company', '--contact-name' => 'A', '--address' => 'B'] + OCCA_CLI_PERSON)
        ->assertFailed();
    $this->artisan('occa:license-create', ['--name' => 'Bad', '--plan' => 'organization', '--months' => 12, '--org-type' => 'club', '--contact-name' => 'A', '--address' => 'B', '--website' => 'not a url'] + OCCA_CLI_PERSON)
        ->assertFailed();

    expect(License::where('licensee_name', 'Bad')->exists())->toBeFalse();
});

test('license:keys prints a usable keypair', function () {
    $this->artisan('license:keys')
        ->expectsOutputToContain('OCCA_LICENSE_SECRET_KEY=')
        ->expectsOutputToContain('OCCA_LICENSE_PUBLIC_KEY=')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------
// License Manager: products
// ---------------------------------------------------------------------

function lmLicenses(string $product = 'occa'): string
{
    return "/api/v1/license-manager/products/{$product}/licenses";
}

test('only OCCA is seeded and existing rows belong to OCCA', function () {
    $this->assertDatabaseHas('license_products', ['code' => 'occa', 'name' => 'OCCA', 'key_prefix' => 'OC', 'is_active' => true]);
    expect(LicenseProduct::count())->toBe(1);

    // A row that existed before the move is backfilled to OCCA.
    Schema::table('licenses', fn ($table) => $table->dropForeign(['product_id']));
    $legacy = License::factory()->create();
    expect($legacy->product->code)->toBe('occa');
});

test('admin can list, create, update and delete products, gated by the license-manager permissions', function () {
    LicenseProduct::factory()->demo()->create();
    Sanctum::actingAs($this->staff);
    $this->getJson('/api/v1/license-manager/products')->assertStatus(403);
    $this->postJson('/api/v1/license-manager/products', ['code' => 'x', 'name' => 'X', 'key_prefix' => 'XX'])->assertStatus(403);

    // Licence viewers may read the products (sidebar), not change them.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('license-manager-list');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/license-manager/products')->assertOk()->assertJsonCount(2, 'data');
    $this->putJson('/api/v1/license-manager/products/occa', ['name' => 'Hacked'])->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/products')->assertOk()
        ->assertJsonPath('data.0.code', 'occa')
        ->assertJsonPath('data.1.code', 'demo-app')
        ->assertJsonPath('data.1.name', 'Demo App');
    $this->getJson('/api/v1/license-manager/products/demo-app')->assertOk()->assertJsonPath('data.key_prefix', 'DEMO');

    // Code: lowercase slug with single hyphens, unique.
    foreach (['Bad Code', 'bad_code', '-bad', 'bad-', 'bad--code', 'occa'] as $code) {
        $this->postJson('/api/v1/license-manager/products', ['code' => $code, 'name' => 'X', 'key_prefix' => 'XX'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
    }
    $this->postJson('/api/v1/license-manager/products', ['code' => 'map-tools-2', 'name' => 'Map Tools 2', 'key_prefix' => 'TOOLONG'])
        ->assertStatus(422)->assertJsonValidationErrors(['key_prefix']);

    $this->postJson('/api/v1/license-manager/products', ['code' => 'map-tools-2', 'name' => 'Map Tools 2', 'key_prefix' => 'mt'])
        ->assertStatus(201)
        ->assertJsonPath('data.code', 'map-tools-2')
        ->assertJsonPath('data.key_prefix', 'MT')
        ->assertJsonPath('data.next_sequences', ['mac' => 1, 'ipad' => 1])
        ->assertJsonPath('data.is_active', true);

    // The code never changes; the rest can.
    $this->putJson('/api/v1/license-manager/products/demo-app', ['code' => 'other', 'name' => 'Demo App Pro', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.code', 'demo-app')
        ->assertJsonPath('data.name', 'Demo App Pro')
        ->assertJsonPath('data.is_active', false);

    // Delete only without licenses (deleted ones count too).
    License::factory()->create()->delete();
    $this->deleteJson('/api/v1/license-manager/products/occa')->assertStatus(422);
    $this->deleteJson('/api/v1/license-manager/products/map-tools-2')->assertOk();
    $this->assertDatabaseMissing('license_products', ['code' => 'map-tools-2']);
    $this->getJson('/api/v1/license-manager/products/nope')->assertStatus(404);
});

test('admin endpoints are scoped to the product in the path', function () {
    LicenseProduct::factory()->demo()->create();
    Sanctum::actingAs($this->admin);
    $occa = License::factory()->create(['licensee_name' => 'Occa User']);
    $land = License::factory()->product('demo-app')->create(['licensee_name' => 'Demo User']);

    expect($occa->key)->toStartWith('OC32-')->and($land->key)->toStartWith('DEMO32-');

    $this->getJson(lmLicenses('occa').'/all/paginated')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.licensee_name', 'Occa User')
        ->assertJsonPath('data.data.0.product.code', 'occa');
    $this->getJson(lmLicenses('demo-app').'/all/paginated')->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.licensee_name', 'Demo User')
        ->assertJsonPath('data.data.0.product.name', 'Demo App');

    // An OCCA license through the Demo App path is a 404, for every endpoint.
    $wrong = lmLicenses('demo-app')."/{$occa->id}";
    $this->getJson($wrong)->assertStatus(404);
    $this->putJson($wrong, ['is_active' => false])->assertStatus(404);
    $this->getJson("{$wrong}/events")->assertStatus(404);
    $this->deleteJson($wrong)->assertStatus(404);
    expect($occa->fresh()->is_active)->toBeTrue()->and($occa->fresh()->trashed())->toBeFalse();
    $this->getJson(lmLicenses('nope').'/all/paginated')->assertStatus(404);

    // Creating under Demo App uses its key prefix.
    $this->postJson(lmLicenses('demo-app'), occaIndividual())->assertStatus(201)
        ->assertJsonPath('data.product.code', 'demo-app')
        ->assertJsonPath('data.key', fn ($key) => str_starts_with($key, 'DEMO32-') && str_ends_with($key, '-0001'));

    // Per-product summary.
    $this->getJson(lmLicenses('demo-app').'/summary')->assertOk()->assertJsonPath('data.total', 2);
    $this->getJson(lmLicenses('occa').'/summary')->assertOk()->assertJsonPath('data.total', 1);
});

test('the overview summary counts per product and in total', function () {
    LicenseProduct::factory()->demo()->create();
    License::factory()->count(2)->create();
    License::factory()->expired()->create();
    License::factory()->product('demo-app')->create(['expires_at' => now()->addDays(3)]);
    License::factory()->product('demo-app')->inactive()->create();

    Sanctum::actingAs($this->staff);
    $this->getJson('/api/v1/license-manager/summary')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/summary')->assertOk()
        ->assertJsonPath('data.all.total', 5)
        ->assertJsonPath('data.all.active', 2)
        ->assertJsonPath('data.products.0.code', 'occa')
        ->assertJsonPath('data.products.0.total', 3)
        ->assertJsonPath('data.products.0.expired', 1)
        ->assertJsonPath('data.products.1.code', 'demo-app')
        ->assertJsonPath('data.products.1.name', 'Demo App')
        ->assertJsonPath('data.products.1.total', 2)
        ->assertJsonPath('data.products.1.expiring_soon', 1)
        ->assertJsonPath('data.products.1.inactive', 1);
});

// ---------------------------------------------------------------------
// License Manager: public API per product
// ---------------------------------------------------------------------

test('another product\'s keys activate on their own route with the product in the token', function () {
    LicenseProduct::factory()->demo()->create();
    $land = License::factory()->product('demo-app')->create();

    $response = $this->postJson('/api/licenses/demo-app/activate', ['key' => strtolower($land->key), 'device_id' => 'ipad-1', 'platform' => 'macOS'])
        ->assertOk()
        ->assertJsonPath('license.product', 'demo-app')
        ->assertJsonPath('license.key', $land->key);
    $payload = app(LicenseTokenService::class)->verify($response->json('token'));
    expect(array_keys($payload)[1])->toBe('product')
        ->and($payload['product'])->toBe('demo-app');

    $this->postJson('/api/licenses/demo-app/refresh', ['key' => $land->key, 'device_id' => 'ipad-1'])->assertOk();
    $this->postJson('/api/licenses/demo-app/deactivate', ['key' => $land->key, 'device_id' => 'ipad-1'])->assertExactJson(['ok' => true]);
    expect($land->activations()->count())->toBe(0);
});

test('a key only works on its own product route; the OCCA alias still works', function () {
    LicenseProduct::factory()->demo()->create();
    $occa = License::factory()->create();
    $land = License::factory()->product('demo-app')->create();

    $this->postJson('/api/licenses/demo-app/activate', ['key' => $occa->key, 'device_id' => 'd', 'platform' => 'macOS'])
        ->assertStatus(404)->assertJsonPath('error', 'invalid_key');
    $this->postJson('/api/occa/license/activate', ['key' => $land->key, 'device_id' => 'd', 'platform' => 'macOS'])
        ->assertStatus(404)->assertJsonPath('error', 'invalid_key');
    $this->postJson('/api/licenses/occa/refresh', ['key' => $land->key, 'device_id' => 'd'])
        ->assertStatus(404)->assertJsonPath('error', 'invalid_key');

    // Generic route and alias are the same product.
    $this->postJson('/api/licenses/occa/activate', ['key' => $occa->key, 'device_id' => 'mac-1', 'platform' => 'macOS'])->assertOk();
    $response = $this->postJson('/api/occa/license/refresh', ['key' => $occa->key, 'device_id' => 'mac-1'])->assertOk();
    expect(app(LicenseTokenService::class)->verify($response->json('token'))['product'])->toBe('occa');
    $this->postJson('/api/occa/license/deactivate', ['key' => $occa->key, 'device_id' => 'mac-1'])->assertOk();
    expect($occa->activations()->count())->toBe(0);

    // A deactivate with another product's key frees nothing.
    $this->postJson('/api/licenses/demo-app/activate', ['key' => $land->key, 'device_id' => 'ipad-1', 'platform' => 'macOS'])->assertOk();
    $this->postJson('/api/occa/license/deactivate', ['key' => $land->key, 'device_id' => 'ipad-1'])->assertOk();
    expect($land->activations()->count())->toBe(1);
});

test('unknown and inactive products are rejected', function () {
    LicenseProduct::factory()->demo()->create();
    $land = License::factory()->product('demo-app')->create();

    $this->postJson('/api/licenses/no-such-app/activate', ['key' => $land->key, 'device_id' => 'd', 'platform' => 'macOS'])
        ->assertStatus(404)->assertJsonPath('error', 'invalid_product');

    LicenseProduct::where('code', 'demo-app')->update(['is_active' => false]);
    $this->postJson('/api/licenses/demo-app/activate', ['key' => $land->key, 'device_id' => 'd', 'platform' => 'macOS'])
        ->assertStatus(403)
        ->assertExactJson(['error' => 'product_inactive', 'message' => 'This product is not available for activation.']);
    $this->postJson('/api/licenses/demo-app/refresh', ['key' => $land->key, 'device_id' => 'd'])
        ->assertStatus(403)->assertJsonPath('error', 'product_inactive');
    // Deactivate always answers ok.
    $this->postJson('/api/licenses/demo-app/deactivate', ['key' => $land->key, 'device_id' => 'd'])->assertOk();
});

test('license:create takes --product and occa:license-create stays an OCCA alias', function () {
    LicenseProduct::factory()->demo()->create();
    $this->artisan('license:create', ['--product' => 'demo-app', '--name' => 'Demo Person', '--plan' => 'lifetime'] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('Demo App')
        ->assertSuccessful();
    $land = License::where('licensee_name', 'Demo Person')->firstOrFail();
    expect($land->product->code)->toBe('demo-app')->and($land->key)->toStartWith('DEMO33-');

    $this->artisan('occa:license-create', ['--name' => 'Occa Person', '--days' => 30] + OCCA_CLI_PERSON)->assertSuccessful();
    $occa = License::where('licensee_name', 'Occa Person')->firstOrFail();
    expect($occa->product->code)->toBe('occa')->and($occa->key)->toMatch(OCCA_KEY_REGEX)->toStartWith('OC32-');

    $this->artisan('license:create', ['--product' => 'nope', '--name' => 'Bad', '--days' => 30] + OCCA_CLI_PERSON)
        ->expectsOutputToContain('Unknown --product')
        ->assertFailed();
});

test('the permission migration renames occa-license-* in place and keeps role grants', function () {
    // Simulate a database seeded before the rename.
    DB::table('permissions')->where('name', 'like', 'license-%')->delete();
    foreach (['menu', 'list', 'create', 'edit', 'delete'] as $action) {
        Permission::create(['name' => 'occa-license-'.$action, 'guard_name' => 'sanctum']);
    }
    $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    $manager->givePermissionTo(['occa-license-list', 'occa-license-create']);
    $listId = Permission::where('name', 'occa-license-list')->value('id');

    $migration = require base_path('database/migrations/2026_11_09_000009_rename_occa_license_permissions_to_license_manager.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::where('name', 'like', 'occa-license-%')->count())->toBe(0)
        ->and(Permission::where('name', 'license-manager-list')->value('id'))->toBe($listId);

    $manager = $manager->fresh();
    expect($manager->hasPermissionTo('license-manager-list'))->toBeTrue()
        ->and($manager->hasPermissionTo('license-manager-create'))->toBeTrue()
        ->and($manager->hasPermissionTo('license-product-manage'))->toBeTrue()
        ->and($manager->hasPermissionTo('license-manager-delete'))->toBeFalse();

    // Then the split: license-manager-view for every role with -list, and
    // license-product-* removed.
    Permission::create(['name' => 'license-product-edit', 'guard_name' => 'sanctum']);
    $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum']);
    $viewer->givePermissionTo('license-manager-list');
    (require base_path('database/migrations/2026_11_10_000005_split_license_manager_permissions.php'))->up();
    (require base_path('database/migrations/2026_11_10_000006_remove_license_product_permissions.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::where('name', 'like', 'license-product-%')->count())->toBe(0)
        ->and(Permission::where('name', 'license-manager-view')->count())->toBe(1)
        ->and($manager->fresh()->hasPermissionTo('license-manager-view'))->toBeTrue()
        ->and($viewer->fresh()->hasPermissionTo('license-manager-view'))->toBeTrue()
        ->and(Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum'])->hasPermissionTo('license-manager-view'))->toBeFalse();
});

// ---------------------------------------------------------------------
// Product icons
// ---------------------------------------------------------------------

test('the OCCA icon is seeded on the public disk and returned as icon_url', function () {
    $occa = LicenseProduct::findByCodeOrFail('occa');
    expect($occa->icon_path)->toBe('license-products/occa.png');
    Storage::disk('public')->assertExists('license-products/occa.png');

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/products/occa')->assertOk()
        ->assertJsonPath('data.icon_url', asset('storage/license-products/occa.png'));
    $this->getJson('/api/v1/license-manager/summary')->assertOk()
        ->assertJsonPath('data.products.0.icon_url', asset('storage/license-products/occa.png'));
    $license = License::factory()->create();
    $this->getJson(lmLicenses('occa')."/{$license->id}")->assertOk()
        ->assertJsonPath('data.product.icon_url', asset('storage/license-products/occa.png'));
});

test('a product icon can be uploaded, replaced and removed', function () {
    Sanctum::actingAs($this->admin);

    $created = $this->post('/api/v1/license-manager/products', [
        'code' => 'demo-app', 'name' => 'Demo App', 'key_prefix' => 'DEMO',
        'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
    ], ['Accept' => 'application/json'])->assertStatus(201);

    $first = LicenseProduct::findByCodeOrFail('demo-app')->icon_path;
    expect($first)->toStartWith('license-products/demo-app-')->toEndWith('.png');
    Storage::disk('public')->assertExists($first);
    expect($created->json('data.icon_url'))->toBe(asset('storage/'.$first));

    // Replace (multipart PUT via _method): the old file is deleted.
    $this->post('/api/v1/license-manager/products/demo-app', [
        '_method' => 'PUT',
        'icon' => UploadedFile::fake()->image('new.webp', 128, 128),
    ], ['Accept' => 'application/json'])->assertOk();
    $second = LicenseProduct::findByCodeOrFail('demo-app')->icon_path;
    expect($second)->not->toBe($first)->toEndWith('.webp');
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);

    // Other edits keep the icon.
    $this->putJson('/api/v1/license-manager/products/demo-app', ['name' => 'Demo App 2'])->assertOk()
        ->assertJsonPath('data.icon_url', asset('storage/'.$second));

    // Remove.
    $this->putJson('/api/v1/license-manager/products/demo-app', ['remove_icon' => true])->assertOk()
        ->assertJsonPath('data.icon_url', null);
    Storage::disk('public')->assertMissing($second);
    expect(LicenseProduct::findByCodeOrFail('demo-app')->icon_path)->toBeNull();
});

test('product icons are validated', function () {
    Sanctum::actingAs($this->admin);
    LicenseProduct::factory()->demo()->create();
    $put = fn ($file) => $this->post('/api/v1/license-manager/products/demo-app', ['_method' => 'PUT', 'icon' => $file], ['Accept' => 'application/json']);

    $put(UploadedFile::fake()->image('big.png')->size(1025))->assertStatus(422)->assertJsonValidationErrors(['icon']);
    $put(UploadedFile::fake()->image('icon.gif'))->assertStatus(422)->assertJsonValidationErrors(['icon']);
    $put(UploadedFile::fake()->create('icon.pdf', 10, 'application/pdf'))->assertStatus(422)->assertJsonValidationErrors(['icon']);
    $put(UploadedFile::fake()->image('ok.jpg')->size(1024))->assertOk();

    // Only license-manager-edit may change icons.
    Sanctum::actingAs($this->staff);
    $put(UploadedFile::fake()->image('icon.png'))->assertStatus(403);
});

// ---------------------------------------------------------------------
// Indonesian regions
// ---------------------------------------------------------------------

test('regions:import is idempotent and the region endpoints return sorted lists', function () {
    expect(DB::table('regions_villages')->count())->toBe(3);
    $this->artisan('regions:import', ['--path' => base_path('tests/Fixtures/regions')])->assertSuccessful();
    expect(DB::table('regions_provinces')->count())->toBe(2)
        ->and(DB::table('regions_regencies')->count())->toBe(2)
        ->and(DB::table('regions_districts')->count())->toBe(2)
        ->and(DB::table('regions_villages')->count())->toBe(3);

    $this->getJson('/api/v1/regions/provinces')->assertStatus(401);

    Sanctum::actingAs($this->staff);
    $this->getJson('/api/v1/regions/provinces')->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, private')
        ->assertExactJson(['success' => true, 'message' => 'Regions Retrieved Successfully', 'data' => [
            ['code' => '31', 'name' => 'Daerah Khusus Ibukota Jakarta'],
            ['code' => '32', 'name' => 'Jawa Barat'],
        ]]);
    $this->getJson('/api/v1/regions/provinces/31/regencies')->assertOk()
        ->assertJsonPath('data', [['code' => '31.71', 'name' => 'Kota Administrasi Jakarta Pusat']]);
    $this->getJson('/api/v1/regions/regencies/31.71/districts')->assertOk()
        ->assertJsonPath('data', [['code' => '31.71.01', 'name' => 'Gambir']]);
    // Sorted by name: Cideng before Gambir.
    $this->getJson('/api/v1/regions/districts/31.71.01/villages')->assertOk()
        ->assertJsonPath('data', [['code' => '31.71.01.1002', 'name' => 'Cideng'], ['code' => '31.71.01.1001', 'name' => 'Gambir']]);
    $this->getJson('/api/v1/regions/districts/99.99.99/villages')->assertOk()->assertJsonPath('data', []);
});

test('Indonesian region codes must exist and belong to their parent', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(lmLicenses(), occaIndividual(['village_code' => '31.71.01.1001']))
        ->assertStatus(422)->assertJsonValidationErrors(['village_code']);
    $this->postJson(lmLicenses(), occaIndividual(['regency_code' => '31.71']))
        ->assertStatus(422)->assertJsonValidationErrors(['regency_code']);
    $this->postJson(lmLicenses(), occaIndividual(['district_code' => '99.99.99']))
        ->assertStatus(422)->assertJsonValidationErrors(['district_code']);
    $this->postJson(lmLicenses(), occaIndividual(['postal_code' => '4015']))
        ->assertStatus(422)->assertJsonValidationErrors(['postal_code']);

    // Names always come from the codes, not from the client.
    $id = $this->postJson(lmLicenses(), occaIndividual(['province' => 'Wrong', 'village' => 'Wrong']))
        ->assertStatus(201)
        ->assertJsonPath('data.province', 'Jawa Barat')
        ->assertJsonPath('data.village', 'Sukarasa')
        ->json('data.id');

    // Moving the address to Jakarta through an update.
    $this->putJson(lmLicenses()."/{$id}", JAKARTA)->assertOk()
        ->assertJsonPath('data.regency', 'Kota Administrasi Jakarta Pusat')
        ->assertJsonPath('data.postal_code', '10110');
    // A single location field is checked against the stored rest.
    $this->putJson(lmLicenses()."/{$id}", ['village_code' => '31.71.01.1002'])->assertOk()
        ->assertJsonPath('data.village', 'Cideng');
    $this->putJson(lmLicenses()."/{$id}", ['village_code' => '32.73.01.1001'])
        ->assertStatus(422)->assertJsonValidationErrors(['village_code']);
});

// ---------------------------------------------------------------------
// Terms, certificate PDF
// ---------------------------------------------------------------------

test('OCCA terms are seeded and new licenses snapshot the terms version', function () {
    $occa = LicenseProduct::findByCodeOrFail('occa');
    expect($occa->terms)->toStartWith('# OCCA')
        ->and($occa->terms_version)->toBe('1.0')
        ->and($occa->terms_effective_at->toDateString())->toBe('2026-11-10');

    Sanctum::actingAs($this->admin);
    $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201)->assertJsonPath('data.terms_version', '1.0');

    $this->putJson('/api/v1/license-manager/products/occa', ['terms' => $occa->terms."\n\nExtra clause."])
        ->assertStatus(422)->assertJsonValidationErrors(['terms_version']);
    $this->putJson('/api/v1/license-manager/products/occa', ['terms' => $occa->terms."\n\nExtra clause.", 'terms_version' => '1.0'])
        ->assertStatus(422)->assertJsonValidationErrors(['terms_version']);
    // Unchanged text keeps the version; other edits do not need one.
    $this->putJson('/api/v1/license-manager/products/occa', ['terms' => $occa->terms, 'name' => 'OCCA'])->assertOk();
    $this->putJson('/api/v1/license-manager/products/occa', ['terms' => $occa->terms."\n\nExtra clause.", 'terms_version' => '1.1', 'terms_effective_at' => '2026-12-01'])
        ->assertOk()
        ->assertJsonPath('data.terms_version', '1.1')
        ->assertJsonPath('data.terms_effective_at', '2026-12-01');

    // Earlier licenses keep 1.0, new ones get 1.1; a product without terms gives null.
    $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201)->assertJsonPath('data.terms_version', '1.1');
    LicenseProduct::factory()->demo()->create();
    $this->postJson(lmLicenses('demo-app'), occaIndividual())->assertStatus(201)->assertJsonPath('data.terms_version', null);
    expect(License::where('terms_version', '1.0')->count())->toBe(1);
});

test('the certificate endpoint streams a PDF, is gated and logs an event', function () {
    $license = License::factory()->create(['terms_version' => '1.0']);
    $url = lmLicenses()."/{$license->id}/certificate";

    Sanctum::actingAs($this->staff);
    $this->get($url, ['Accept' => 'application/json'])->assertStatus(403);

    $lister = User::factory()->create();
    $lister->givePermissionTo('license-manager-list');
    Sanctum::actingAs($lister);
    $this->get($url, ['Accept' => 'application/json'])->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $response = $this->get($url)->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="OCCA-License-'.$license->key.'.pdf"');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    $event = occaEvents($license, 'certificate_downloaded')->sole();
    expect($event->user_id)->toBe($this->admin->id)
        ->and($event->meta['reference'])->toBe('LIC-OCCA-MAC-'.str_pad((string) $license->sequence, 4, '0', STR_PAD_LEFT));

    // Another product's path is a 404.
    LicenseProduct::factory()->demo()->create();
    $this->get(lmLicenses('demo-app')."/{$license->id}/certificate", ['Accept' => 'application/json'])->assertStatus(404);
});

test('lifetime and organization certificates render, with the terms appendix only when there are terms', function () {
    $service = app(LicenseCertificateService::class);

    $lifetime = License::factory()->lifetime()->create(['terms_version' => '1.0']);
    $html = view('pdf.license-certificate', $service->data($lifetime))->render();
    expect($html)->toContain($lifetime->key)
        ->toContain('Lifetime · never expires')
        ->toContain('Appendix A')
        ->toContain('Subject to the <strong>OCCA License Terms v1.0</strong>')
        ->toContain('Terms version: <strong>1.0</strong>')
        ->toContain('Open OCCA');
    expect(substr($service->render($lifetime), 0, 4))->toBe('%PDF');

    $org = License::factory()->plan('organization')->create(['licensee_name' => 'Federation X', 'contact_name' => 'Ana']);
    $html = view('pdf.license-certificate', $service->data($org))->render();
    expect($html)->toContain('Federation X')->toContain('Ana')->toContain('Gambir, Kota Administrasi Jakarta Pusat');
    expect(substr($service->render($org), 0, 4))->toBe('%PDF');

    // A product without terms: no appendix and no "Subject to" line.
    LicenseProduct::factory()->demo()->create();
    $demo = License::factory()->product('demo-app')->create();
    $html = view('pdf.license-certificate', $service->data($demo))->render();
    expect($html)->not->toContain('Appendix A')->not->toContain('Subject to the')->toContain('Open Demo App');
    expect(substr($service->render($demo), 0, 4))->toBe('%PDF');
});

// ---------------------------------------------------------------------
// Overview widgets
// ---------------------------------------------------------------------

test('the expiring endpoint lists active licenses of every product expiring within N days', function () {
    LicenseProduct::factory()->demo()->create();
    $soon = License::factory()->create(['expires_at' => now()->addDays(3)]);
    $later = License::factory()->product('demo-app')->create(['expires_at' => now()->addDays(20)]);
    License::factory()->create(['expires_at' => now()->addDays(60)]);
    License::factory()->inactive()->create(['expires_at' => now()->addDays(2)]);
    License::factory()->expired()->create();
    License::factory()->lifetime()->create();

    $lister = User::factory()->create();
    $lister->givePermissionTo('license-manager-list');
    Sanctum::actingAs($lister);
    $this->getJson('/api/v1/license-manager/expiring')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/expiring?days=30&limit=10')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $soon->id)
        ->assertJsonPath('data.0.status', 'expiring_soon')
        ->assertJsonPath('data.0.remaining_days', 3)
        ->assertJsonPath('data.0.product.code', 'occa')
        ->assertJsonPath('data.0.product.icon_url', asset('storage/license-products/occa.png'))
        ->assertJsonPath('data.1.id', $later->id)
        ->assertJsonPath('data.1.product.code', 'demo-app');
    $this->getJson('/api/v1/license-manager/expiring?days=5')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/license-manager/expiring?days=30&limit=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/license-manager/expiring?days=0')->assertStatus(422);
});

test('the recent events endpoint lists the newest events of every product', function () {
    LicenseProduct::factory()->demo()->create();
    $occa = License::factory()->create(['licensee_name' => 'Occa User']);
    $demo = License::factory()->product('demo-app')->create(['licensee_name' => 'Demo User']);

    Sanctum::actingAs($this->admin);
    $this->putJson(lmLicenses()."/{$occa->id}", ['is_active' => false])->assertOk();
    $this->travel(1)->minutes();
    $this->postJson('/api/licenses/demo-app/activate', ['key' => $demo->key, 'device_id' => 'mac-2', 'device_name' => 'MacBook Air', 'platform' => 'macOS'])->assertOk();

    $lister = User::factory()->create();
    $lister->givePermissionTo('license-manager-list');
    Sanctum::actingAs($lister);
    $this->getJson('/api/v1/license-manager/events/recent')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/events/recent?limit=15')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'device_activated')
        ->assertJsonPath('data.0.device_name', 'MacBook Air')
        ->assertJsonPath('data.0.platform', 'macOS')
        ->assertJsonPath('data.0.admin', null)
        ->assertJsonPath('data.0.license', ['id' => $demo->id, 'key' => $demo->key, 'licensee_name' => 'Demo User'])
        ->assertJsonPath('data.0.product', ['code' => 'demo-app', 'name' => 'Demo App', 'icon_url' => null])
        ->assertJsonPath('data.1.event', 'deactivated_by_admin')
        ->assertJsonPath('data.1.admin', ['name' => $this->admin->name])
        ->assertJsonPath('data.1.product.code', 'occa')
        ->assertJsonStructure(['data' => [['id', 'event', 'created_at', 'device_name', 'platform', 'ip', 'admin', 'license', 'product']]]);
    $this->getJson('/api/v1/license-manager/events/recent?limit=1')->assertOk()->assertJsonCount(1, 'data');
});

test('the overview summary has counts per plan for each product', function () {
    License::factory()->count(2)->create();
    License::factory()->plan('trial')->create();
    License::factory()->lifetime()->create();

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/license-manager/summary')->assertOk()
        ->assertJsonPath('data.products.0.by_plan', ['professional' => 2, 'trial' => 1, 'lifetime' => 1, 'organization' => 0]);
});

// ---------------------------------------------------------------------
// Granular permissions
// ---------------------------------------------------------------------

test('every endpoint needs its own license-manager permission', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1')->assertOk();
    $activation = $license->activations()->first();
    $base = lmLicenses();

    $endpoints = [
        'list' => [['GET', "{$base}"], ['GET', "{$base}/all/paginated"], ['GET', "{$base}/summary"], ['GET', '/api/v1/license-manager/summary']],
        'view' => [['GET', "{$base}/{$license->id}"], ['GET', "{$base}/{$license->id}/events"], ['GET', "{$base}/{$license->id}/certificate"], ['GET', '/api/v1/license-manager/expiring'], ['GET', '/api/v1/license-manager/events/recent']],
        'create' => [['POST', $base, occaIndividual()], ['POST', '/api/v1/license-manager/products', ['code' => 'map-tools', 'name' => 'Map Tools', 'key_prefix' => 'MAP']]],
        'edit' => [['PUT', "{$base}/{$license->id}", ['is_active' => false]], ['DELETE', "{$base}/{$license->id}/activations/{$activation->id}"], ['PUT', '/api/v1/license-manager/products/occa', ['name' => 'OCCA']]],
        'delete' => [['DELETE', "{$base}/{$license->id}"]],
    ];

    foreach ($endpoints as $action => $calls) {
        $without = User::factory()->create();
        $without->givePermissionTo(array_values(array_diff(array_map(fn ($a) => 'license-manager-'.$a, LICENSE_ACTIONS), ['license-manager-'.$action])));
        $with = User::factory()->create();
        $with->givePermissionTo('license-manager-'.$action);

        foreach ($calls as $call) {
            [$method, $url] = $call;
            $body = $call[2] ?? [];
            Sanctum::actingAs($without);
            $status = $this->json($method, $url, $body)->getStatusCode();
            // show also accepts edit (the edit page loads the license).
            if (! ($action === 'view' && $url === "{$base}/{$license->id}")) {
                expect($status)->toBe(403, "{$method} {$url} without {$action}");
            }

            Sanctum::actingAs($with);
            expect($this->json($method, $url, $body)->getStatusCode())->toBeIn([200, 201], "{$method} {$url} with {$action}");
        }
    }

    // Products: readable with menu or list; deleting needs delete.
    $menuOnly = User::factory()->create();
    $menuOnly->givePermissionTo('license-manager-menu');
    Sanctum::actingAs($menuOnly);
    $this->getJson('/api/v1/license-manager/products')->assertOk();
    $this->getJson('/api/v1/license-manager/products/occa')->assertOk();
    $this->deleteJson('/api/v1/license-manager/products/map-tools')->assertStatus(403);
    $deleter = User::factory()->create();
    $deleter->givePermissionTo('license-manager-delete');
    Sanctum::actingAs($deleter);
    $this->deleteJson('/api/v1/license-manager/products/map-tools')->assertOk();

    expect(Permission::where('name', 'like', 'license-product-%')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------
// Key format: {prefix}{planCode}-XXXX-XXXX-XXXX-{sequence}
// ---------------------------------------------------------------------

test('keys carry the plan code and a per-product sequence across plans', function () {
    Sanctum::actingAs($this->admin);

    $keys = [];
    foreach (['trial' => '31', 'professional' => '32', 'lifetime' => '33', 'organization' => '34'] as $plan => $code) {
        $payload = $plan === 'organization' ? occaOrganization() : occaIndividual(['plan' => $plan, 'duration' => $plan === 'lifetime' ? null : '30_days']);
        $response = $this->postJson(lmLicenses(), $payload)->assertStatus(201);
        $keys[] = $key = $response->json('data.key');
        expect($key)->toMatch(OCCA_KEY_REGEX)->toStartWith("OC{$code}-");
    }

    // One counter per product, across plans: 0001 … 0004.
    expect(array_map(fn ($key) => substr($key, -4), $keys))->toBe(['0001', '0002', '0003', '0004']);
    expect(License::orderBy('id')->pluck('sequence')->all())->toBe([1, 2, 3, 4]);
    $this->getJson('/api/v1/license-manager/products/occa')->assertJsonPath('data.next_sequences', ['mac' => 5, 'ipad' => 1]);

    // Another product has its own counter.
    LicenseProduct::factory()->demo()->create();
    expect($this->postJson(lmLicenses('demo-app'), occaIndividual())->json('data.key'))->toStartWith('DEMO32-')->toEndWith('-0001');
});

test('keys are always 20 characters and stop at sequence 9999', function () {
    Sanctum::actingAs($this->admin);

    $key = $this->postJson(lmLicenses(), occaIndividual())->json('data.key');
    expect(strlen($key))->toBe(24)                       // 20 characters + 4 dashes
        ->and(strlen(str_replace('-', '', $key)))->toBe(20);

    LicenseProduct::findByCodeOrFail('occa')->forceFill(['mac_license_sequence' => License::MAX_SEQUENCE - 1])->save();
    expect($this->postJson(lmLicenses(), occaIndividual())->assertStatus(201)->json('data.key'))->toEndWith('-9999');
    $this->postJson(lmLicenses(), occaIndividual())->assertStatus(422)->assertJsonValidationErrors('platform');
    expect(LicenseProduct::findByCodeOrFail('occa')->mac_license_sequence)->toBe(License::MAX_SEQUENCE);

    // iPad counts on its own, so it is not affected by the full Mac range.
    expect($this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->assertStatus(201)->json('data.key'))
        ->toStartWith('OC52-')->toEndWith('-0001');
});

test('sequence numbers are never reused, also after deletes', function () {
    Sanctum::actingAs($this->admin);

    $first = $this->postJson(lmLicenses(), occaIndividual())->json('data.id');
    $second = $this->postJson(lmLicenses(), occaIndividual())->json('data.id');
    $this->deleteJson(lmLicenses()."/{$second}")->assertOk();
    License::withTrashed()->find($second)->forceDelete();
    $this->deleteJson(lmLicenses()."/{$first}")->assertOk();

    $third = $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201);
    expect($third->json('data.sequence'))->toBe(3)
        ->and($third->json('data.key'))->toEndWith('-0003');
});

test('two creates in a row get consecutive numbers from the locked counter', function () {
    $repository = app(LicenseRepositoryInterface::class);
    $occa = LicenseProduct::findByCodeOrFail('occa');

    // Both use a stale product instance: the counter is read under the lock,
    // not from the instance, so they still get 1 and 2.
    $a = $repository->create($occa, occaIndividual());
    $b = $repository->create($occa, occaIndividual(['plan' => 'trial', 'duration' => '14_days']));

    expect([$a->sequence, $b->sequence])->toBe([1, 2])
        ->and($a->key)->toEndWith('-0001')->toStartWith('OC32-')
        ->and($b->key)->toEndWith('-0002')->toStartWith('OC31-')
        ->and($occa->fresh()->mac_license_sequence)->toBe(2);

    // The key number stays 4 digits: after 9999 creating is refused, the counter unchanged.
    $occa->forceFill(['mac_license_sequence' => 9999])->save();
    expect(fn () => $repository->create($occa, occaIndividual()))->toThrow(ValidationException::class);
    expect($occa->fresh()->mac_license_sequence)->toBe(9999);
});

test('the key never changes when the plan changes, and the reference uses the sequence', function () {
    Sanctum::actingAs($this->admin);
    $created = $this->postJson(lmLicenses(), occaIndividual())->json('data');

    $this->putJson(lmLicenses()."/{$created['id']}", ['plan' => 'lifetime'])->assertOk()
        ->assertJsonPath('data.key', $created['key'])
        ->assertJsonPath('data.plan', 'lifetime');

    expect(LicenseCertificateService::reference(License::find($created['id'])))->toBe('LIC-OCCA-MAC-0001');
});

test('key prefixes are 1-6 uppercase letters and OCCA uses OC', function () {
    expect(LicenseProduct::findByCodeOrFail('occa')->key_prefix)->toBe('OC');
    Sanctum::actingAs($this->admin);

    foreach (['', 'TOOLONG', 'OC1', 'O-C'] as $prefix) {
        $this->postJson('/api/v1/license-manager/products', ['code' => 'map-tools', 'name' => 'Map Tools', 'key_prefix' => $prefix])
            ->assertStatus(422)->assertJsonValidationErrors(['key_prefix']);
    }
    $this->postJson('/api/v1/license-manager/products', ['code' => 'map-tools', 'name' => 'Map Tools', 'key_prefix' => 'm'])
        ->assertStatus(201)->assertJsonPath('data.key_prefix', 'M');
    $this->putJson('/api/v1/license-manager/products/occa', ['key_prefix' => 'OCCA2'])
        ->assertStatus(422)->assertJsonValidationErrors(['key_prefix']);
});

test('the platform migration resets existing keys to Mac keys in the new format', function () {
    // Back to the state before both key migrations, with two old-style keys.
    (require base_path('database/migrations/2026_11_10_000008_add_platform_to_licenses.php'))->down();
    (require base_path('database/migrations/2026_11_10_000007_add_license_sequence_and_new_key_format.php'))->down();
    $occaId = DB::table('license_products')->where('code', 'occa')->value('id');
    foreach (['OCCA-AAAA-BBBB-CCCC-DDDD', 'OCCA-EEEE-FFFF-GGGG-HHHH'] as $i => $key) {
        DB::table('licenses')->insert([
            'product_id' => $occaId, 'key' => $key, 'licensee_name' => 'Old '.$i, 'plan' => $i ? 'lifetime' : 'professional',
            'type' => $i ? 'lifetime' : 'term', 'max_devices' => 2, 'is_active' => true,
            'created_at' => now()->addMinutes($i), 'updated_at' => now(),
        ]);
    }

    (require base_path('database/migrations/2026_11_10_000007_add_license_sequence_and_new_key_format.php'))->up();
    (require base_path('database/migrations/2026_11_10_000008_add_platform_to_licenses.php'))->up();

    $keys = DB::table('licenses')->orderBy('id')->pluck('key')->all();
    expect(DB::table('licenses')->orderBy('id')->pluck('sequence')->all())->toBe([1, 2])
        ->and(DB::table('licenses')->pluck('platform')->unique()->values()->all())->toBe(['mac'])
        ->and($keys[0])->toMatch(OCCA_KEY_REGEX)->toStartWith('OC32-')->toEndWith('-0001')
        ->and($keys[1])->toMatch(OCCA_KEY_REGEX)->toStartWith('OC33-')->toEndWith('-0002')
        ->and(LicenseProduct::findByCodeOrFail('occa')->mac_license_sequence)->toBe(2)
        ->and(LicenseProduct::findByCodeOrFail('occa')->ipad_license_sequence)->toBe(0);

    // The old key is gone; the new one activates on a Mac.
    $this->postJson('/api/occa/license/activate', ['key' => 'OCCA-AAAA-BBBB-CCCC-DDDD', 'device_id' => 'mac-1', 'platform' => 'macOS'])->assertStatus(404);
    $this->postJson('/api/occa/license/activate', ['key' => strtolower($keys[0]), 'device_id' => 'mac-1', 'platform' => 'macOS'])->assertOk();
});

// ---------------------------------------------------------------------
// Platforms: Mac keys (OC31-34) and iPad keys (OC52-54, no Trial)
// ---------------------------------------------------------------------

test('iPad keys use platform code 5 and their own sequence, Mac keys code 3', function () {
    Sanctum::actingAs($this->admin);

    $mac1 = $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201);
    $mac2 = $this->postJson(lmLicenses(), occaIndividual(['plan' => 'lifetime', 'duration' => null]))->assertStatus(201);
    $ipad1 = $this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->assertStatus(201);
    $ipad2 = $this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad', 'plan' => 'lifetime', 'duration' => null]))->assertStatus(201);
    $ipad3 = $this->postJson(lmLicenses(), occaOrganization(['platform' => 'ipad']))->assertStatus(201);

    expect($mac1->json('data.key'))->toMatch(OCCA_KEY_REGEX)->toStartWith('OC32-')->toEndWith('-0001')
        ->and($mac2->json('data.key'))->toStartWith('OC33-')->toEndWith('-0002')
        ->and($ipad1->json('data.key'))->toMatch(OCCA_KEY_REGEX)->toStartWith('OC52-')->toEndWith('-0001')
        ->and($ipad2->json('data.key'))->toStartWith('OC53-')->toEndWith('-0002')
        ->and($ipad3->json('data.key'))->toStartWith('OC54-')->toEndWith('-0003')
        ->and($ipad1->json('data.platform'))->toBe('ipad')
        ->and($ipad1->json('data.platform_label'))->toBe('iPad')
        ->and($mac1->json('data.platform_label'))->toBe('Mac');

    $this->getJson('/api/v1/license-manager/products/occa')->assertJsonPath('data.next_sequences', ['mac' => 3, 'ipad' => 4]);
});

test('platform is required on store, iPad has no Trial, and the platform cannot change', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(lmLicenses(), occaIndividual(['platform' => null]))->assertStatus(422)->assertJsonValidationErrors('platform');
    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'android']))->assertStatus(422)->assertJsonValidationErrors('platform');
    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad', 'plan' => 'trial', 'duration' => '14_days']))
        ->assertStatus(422)->assertJsonValidationErrors('plan');
    $this->postJson(lmLicenses(), occaIndividual(['plan' => 'trial', 'duration' => '14_days']))->assertStatus(201);

    $ipad = License::find($this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->json('data.id'));
    $this->putJson(lmLicenses().'/'.$ipad->id, ['platform' => 'mac'])->assertStatus(422)->assertJsonValidationErrors('platform');
    $this->putJson(lmLicenses().'/'.$ipad->id, ['plan' => 'trial', 'duration' => '14_days'])->assertStatus(422)->assertJsonValidationErrors('plan');
    $this->putJson(lmLicenses().'/'.$ipad->id, ['platform' => 'ipad', 'notes' => 'same platform is fine'])->assertOk();

    expect($ipad->fresh()->platform)->toBe('ipad')->and($ipad->fresh()->key)->toBe($ipad->key);
});

test('the list can be filtered by platform', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201);
    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->assertStatus(201);

    expect($this->getJson(lmLicenses().'?platform=ipad')->json('data'))->toHaveCount(1)
        ->and($this->getJson(lmLicenses().'?platform=ipad')->json('data.0.platform'))->toBe('ipad')
        ->and($this->getJson(lmLicenses().'?platform=mac')->json('data.0.platform'))->toBe('mac');
    $this->getJson(lmLicenses().'?platform=android')->assertStatus(422);
});

test('a key only activates on its own platform and the token carries the platform', function () {
    $mac = License::factory()->create();
    $ipad = License::factory()->ipad()->create();

    // Mac key on an iPad, iPad key on a Mac: refused and tracked.
    occaActivate($this, $mac, 'ipad-1', ['device_name' => 'iPad Pro', 'platform' => 'iPadOS'])
        ->assertStatus(403)->assertExactJson(['error' => 'wrong_platform', 'message' => 'This license key is for Mac and cannot be used on this device.']);
    occaActivate($this, $ipad, 'mac-1', ['platform' => 'macOS'])
        ->assertStatus(403)->assertJsonPath('error', 'wrong_platform');
    expect($mac->activations()->count())->toBe(0)
        ->and(occaEvents($mac, LicenseEvent::DENIED_WRONG_PLATFORM)->count())->toBe(1)
        ->and(occaEvents($ipad, LicenseEvent::DENIED_WRONG_PLATFORM)->count())->toBe(1);

    // The platform must be sent and known.
    occaActivate($this, $mac, 'mac-1', ['platform' => null])->assertStatus(422)->assertJsonPath('error', 'invalid_request');
    occaActivate($this, $mac, 'mac-1', ['platform' => 'Windows'])->assertStatus(422)->assertJsonPath('error', 'invalid_request');

    // Matching platforms work; older iPads report "iOS".
    $token = occaActivate($this, $ipad, 'ipad-1', ['device_name' => 'iPad Air', 'platform' => 'iPadOS'])->assertOk()
        ->assertJsonPath('license.platform', 'ipad')->json('token');
    occaActivate($this, $ipad, 'ipad-2', ['platform' => 'iOS'])->assertOk();
    occaActivate($this, $mac, 'mac-1', ['platform' => 'macOS'])->assertOk()->assertJsonPath('license.platform', 'mac');

    expect(app(LicenseTokenService::class)->verify($token)['platform'])->toBe('ipad');
});

test('refresh checks the platform sent or the one the device activated with', function () {
    $ipad = License::factory()->ipad()->create();
    occaActivate($this, $ipad, 'ipad-1', ['platform' => 'iPadOS'])->assertOk();

    $this->postJson('/api/occa/license/refresh', ['key' => $ipad->key, 'device_id' => 'ipad-1'])->assertOk();
    $this->postJson('/api/occa/license/refresh', ['key' => $ipad->key, 'device_id' => 'ipad-1', 'platform' => 'macOS'])
        ->assertStatus(403)->assertJsonPath('error', 'wrong_platform');

    // An activation stored with another platform (e.g. before the split) is refused too.
    $ipad->activations()->where('device_id', 'ipad-1')->update(['platform' => 'macOS']);
    $this->postJson('/api/occa/license/refresh', ['key' => $ipad->key, 'device_id' => 'ipad-1'])
        ->assertStatus(403)->assertJsonPath('error', 'wrong_platform');

    // Deactivating never checks the platform.
    $this->postJson('/api/occa/license/deactivate', ['key' => $ipad->key, 'device_id' => 'ipad-1', 'platform' => 'macOS'])->assertExactJson(['ok' => true]);
});

test('license:create takes --platform and refuses an iPad trial', function () {
    $this->artisan('license:create', [...OCCA_CLI_PERSON, '--name' => 'Ipad User', '--platform' => 'ipad', '--months' => 12])->assertSuccessful();
    expect(License::where('licensee_name', 'Ipad User')->first())
        ->platform->toBe('ipad')
        ->key->toStartWith('OC52-')->toEndWith('-0001');

    $this->artisan('license:create', [...OCCA_CLI_PERSON, '--name' => 'Ipad Trial', '--platform' => 'ipad', '--plan' => 'trial', '--days' => 14])->assertFailed();
    $this->artisan('license:create', [...OCCA_CLI_PERSON, '--name' => 'Mac Trial', '--plan' => 'trial', '--days' => 14])->assertSuccessful();
    expect(License::where('licensee_name', 'Mac Trial')->value('key'))->toStartWith('OC31-');
});

test('certificates of a Mac and an iPad license with the same number have different references and platform steps', function () {
    Sanctum::actingAs($this->admin);

    $mac = License::find($this->postJson(lmLicenses(), occaIndividual())->json('data.id'));
    $ipad = License::find($this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->json('data.id'));

    $service = app(LicenseCertificateService::class);
    expect($mac->sequence)->toBe(1)->and($ipad->sequence)->toBe(1)
        ->and(LicenseCertificateService::reference($mac))->toBe('LIC-OCCA-MAC-0001')
        ->and(LicenseCertificateService::reference($ipad))->toBe('LIC-OCCA-IPAD-0001')
        ->and($service->data($mac)['steps']['steps'][0][1])->toContain('on your Mac')
        ->and($service->data($ipad)['steps']['steps'][0][1])->toContain('on your iPad')
        ->and($service->data($ipad)['steps']['steps'][1][1])->not->toContain('Starter');
});

// ---------------------------------------------------------------------
// Mac + iPad: two keys generated together (one bundle)
// ---------------------------------------------------------------------

test('platform "both" creates a Mac and an iPad license together, linked as a bundle', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'ipad']))->assertStatus(201); // iPad 0001
    $response = $this->postJson(lmLicenses(), occaIndividual(['platform' => 'both', 'max_devices' => 3]))->assertStatus(201);

    $mac = License::find($response->json('data.id'));
    $ipad = License::where('bundle_id', $mac->bundle_id)->where('platform', 'ipad')->first();

    expect($mac->platform)->toBe('mac')
        ->and($mac->key)->toStartWith('OC32-')->toEndWith('-0001')
        ->and($ipad->key)->toMatch(OCCA_KEY_REGEX)->toStartWith('OC52-')->toEndWith('-0002')
        ->and($mac->bundle_id)->not->toBeNull()
        ->and($ipad->only(['licensee_name', 'plan', 'type', 'max_devices', 'expires_at']))
        ->toEqual($mac->only(['licensee_name', 'plan', 'type', 'max_devices', 'expires_at']));

    $response->assertJsonPath('data.bundle.0.key', $ipad->key)->assertJsonPath('data.bundle.0.platform', 'ipad');
    $this->getJson(lmLicenses().'/'.$ipad->id)->assertJsonPath('data.bundle.0.key', $mac->key)->assertJsonCount(1, 'data.bundle');

    // Each key is created and tracked on its own; a single license has no bundle.
    expect(occaEvents($mac, LicenseEvent::CREATED)->first()->meta['bundle'])->toBeTrue()
        ->and(occaEvents($ipad, LicenseEvent::CREATED)->count())->toBe(1);
    $single = $this->postJson(lmLicenses(), occaIndividual())->assertStatus(201);
    expect($single->json('data.bundle_id'))->toBeNull()->and($single->json('data.bundle'))->toBe([]);

    // And each key only works on its own platform.
    occaActivate($this, $mac, 'mac-1', ['platform' => 'macOS'])->assertOk();
    occaActivate($this, $ipad, 'ipad-1', ['platform' => 'iPadOS'])->assertOk();
    occaActivate($this, $ipad, 'mac-2', ['platform' => 'macOS'])->assertStatus(403)->assertJsonPath('error', 'wrong_platform');
});

test('"both" is refused for Trial and creates nothing when one platform is full', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'both', 'plan' => 'trial', 'duration' => '14_days']))
        ->assertStatus(422)->assertJsonValidationErrors('plan');

    // iPad numbers are used up: the Mac half is rolled back too.
    LicenseProduct::findByCodeOrFail('occa')->forceFill(['ipad_license_sequence' => License::MAX_SEQUENCE])->save();
    $this->postJson(lmLicenses(), occaIndividual(['platform' => 'both']))->assertStatus(422)->assertJsonValidationErrors('platform');
    expect(License::count())->toBe(0)
        ->and(LicenseProduct::findByCodeOrFail('occa')->mac_license_sequence)->toBe(0);

    // "both" is only a create choice: an existing license keeps its platform.
    LicenseProduct::findByCodeOrFail('occa')->forceFill(['ipad_license_sequence' => 0])->save();
    $id = $this->postJson(lmLicenses(), occaIndividual())->json('data.id');
    $this->putJson(lmLicenses().'/'.$id, ['platform' => 'both'])->assertStatus(422)->assertJsonValidationErrors('platform');
});

test('license:create --platform=both prints both keys', function () {
    $this->artisan('license:create', [...OCCA_CLI_PERSON, '--name' => 'Both User', '--platform' => 'both', '--plan' => 'lifetime'])
        ->assertSuccessful();

    $keys = License::where('licensee_name', 'Both User')->orderBy('platform')->pluck('key', 'platform');
    expect($keys['ipad'])->toStartWith('OC53-')->and($keys['mac'])->toStartWith('OC33-')
        ->and(License::where('licensee_name', 'Both User')->distinct()->count('bundle_id'))->toBe(1);
});

test('a Mac + iPad bundle has one certificate with both keys, the same from either license', function () {
    Sanctum::actingAs($this->admin);

    $mac = License::find($this->postJson(lmLicenses(), occaIndividual(['platform' => 'both']))->json('data.id'));
    $ipad = License::where('bundle_id', $mac->bundle_id)->where('platform', 'ipad')->first();
    $service = app(LicenseCertificateService::class);

    foreach ([$mac, $ipad] as $license) {
        $data = $service->data($license);
        expect($data['isBundle'])->toBeTrue()
            ->and($data['keys']->pluck('key')->all())->toBe([$mac->key, $ipad->key])
            ->and($data['reference'])->toBe('LIC-OCCA-MAC-0001+IPAD-0001')
            ->and($data['platformsLabel'])->toBe('Mac + iPad')
            ->and($data['steps']['steps'][2][1])->toContain('Mac key on a Mac and the iPad key on an iPad')
            ->and($data['ribbons'])->toHaveKeys(['tl', 'tr', 'bl', 'br'])
            ->and($service->filename($license))->toBe("occa-License-{$mac->key}_{$ipad->key}.pdf");
    }

    $html = view('pdf.license-certificate', $service->data($ipad))->render();
    expect($html)->toContain($mac->key)->toContain($ipad->key)->toContain('License Keys')->toContain('ribbon tl');

    // A single license keeps its own one-key certificate.
    $single = License::find($this->postJson(lmLicenses(), occaIndividual())->json('data.id'));
    expect($service->data($single)['isBundle'])->toBeFalse()
        ->and($service->data($single)['keys'])->toHaveCount(1)
        ->and($service->data($single)['reference'])->toBe('LIC-OCCA-MAC-0002');
});

test('a Trial certificate has no seal, every other plan does', function () {
    Sanctum::actingAs($this->admin);
    $service = app(LicenseCertificateService::class);

    $trial = License::find($this->postJson(lmLicenses(), occaIndividual(['plan' => 'trial', 'duration' => '14_days']))->json('data.id'));
    $pro = License::find($this->postJson(lmLicenses(), occaIndividual())->json('data.id'));

    expect($service->data($trial)['seal'])->toBeNull()
        ->and($service->data($pro)['seal'])->toStartWith('data:image/png;base64,')
        ->and(view('pdf.license-certificate', $service->data($trial))->render())->not->toContain('class="seal"');
});

// ---------------------------------------------------------------------
// Version list (releases) and updates per license
// ---------------------------------------------------------------------

/**
 * A complete release payload.
 */
function occaRelease(array $overrides = []): array
{
    return array_merge([
        'version' => '1.1.0',
        'platforms' => 'both',
        'title' => 'Map tools',
        'status' => 'published',
        'released_at' => '2026-12-01',
        'changes' => [
            ['type' => 'feature', 'area' => 'Map making', 'text' => 'Hatched areas.'],
            ['type' => 'fix', 'area' => '', 'text' => 'Export no longer stops on big maps.'],
        ],
    ], $overrides);
}

test('the first version list of OCCA is imported as a 1.0.0 draft', function () {
    $release = LicenseProduct::findByCode('occa')->releases()->sole();

    expect($release->version)->toBe('1.0.0')
        ->and($release->status)->toBe('draft')
        ->and($release->kind())->toBe('major')
        ->and(count($release->changes))->toBeGreaterThan(10)
        ->and($release->changeCounts()['feature'])->toBe(count($release->changes));
});

test('admin can add, publish, edit and delete versions', function () {
    Sanctum::actingAs($this->admin);
    $base = '/api/v1/license-manager/products/occa/releases';

    // A Mac version needs its installer before it can be published.
    $this->postJson($base, occaRelease())->assertStatus(422)->assertJsonValidationErrors('status');
    $this->postJson($base, occaRelease(['version' => '1.0.5', 'platforms' => 'ipad']))->assertCreated();

    $created = $this->postJson($base, occaRelease(['status' => 'draft']))->assertCreated()
        ->assertJsonPath('data.version', '1.1.0')
        ->assertJsonPath('data.kind', 'minor')
        ->assertJsonPath('data.change_counts.feature', 1)
        ->assertJsonPath('data.change_counts.fix', 1)
        ->assertJsonPath('data.changes.1.area', null);

    $this->postJson($base, occaRelease())->assertStatus(422)->assertJsonValidationErrors('version');
    $this->postJson($base, occaRelease(['version' => '1.2']))->assertStatus(422)->assertJsonValidationErrors('version');
    $this->postJson($base, occaRelease(['version' => '1.2.0', 'released_at' => null]))->assertStatus(422)->assertJsonValidationErrors('released_at');
    $this->postJson($base, occaRelease(['version' => '1.2.0', 'changes' => [['type' => 'oops', 'text' => 'x']]]))->assertStatus(422);

    $id = $created->json('data.id');
    $this->putJson("$base/$id", ['version' => '1.1.1', 'is_critical' => true])->assertOk()
        ->assertJsonPath('data.kind', 'patch')
        ->assertJsonPath('data.patch', 1)
        ->assertJsonPath('data.is_critical', true)
        ->assertJsonPath('data.title', 'Map tools');

    $list = $this->getJson($base)->assertOk();
    expect(collect($list->json('data.releases'))->pluck('version')->all())->toBe(['1.1.1', '1.0.5', '1.0.0'])
        ->and($list->json('data.latest_version'))->toBe('1.0.5');

    $this->deleteJson("$base/$id")->assertOk();
    expect(LicenseProduct::findByCode('occa')->releases()->count())->toBe(2);
});

test('release endpoints need license manager permissions', function () {
    Sanctum::actingAs($this->staff);
    $this->getJson('/api/v1/license-manager/products/occa/releases')->assertStatus(403);
    $this->postJson('/api/v1/license-manager/products/occa/releases', occaRelease())->assertStatus(403);
});

test('a new lifetime license covers the current major version and its updates', function () {
    Sanctum::actingAs($this->admin);
    $product = LicenseProduct::findByCode('occa');
    $product->releases()->create(occaRelease(['version' => '2.0.0']));

    $lifetime = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual(['plan' => 'lifetime', 'duration' => null]))
        ->assertCreated()
        ->assertJsonPath('data.updates_major', 2)
        ->assertJsonPath('data.updates_label', 'OCCA 2.x and its updates');
    $pro = $this->postJson('/api/v1/license-manager/products/occa/licenses', occaIndividual())
        ->assertCreated()
        ->assertJsonPath('data.updates_major', null)
        ->assertJsonPath('data.updates_label', 'Every version while active');

    // A paid upgrade raises the major version; moving to a term plan clears it.
    $id = $lifetime->json('data.id');
    $this->putJson("/api/v1/license-manager/products/occa/licenses/$id", ['updates_major' => 3])->assertOk()->assertJsonPath('data.updates_major', 3);
    $this->putJson("/api/v1/license-manager/products/occa/licenses/$id", ['plan' => 'professional', 'duration' => '1_year'])->assertOk()->assertJsonPath('data.updates_major', null);

    $license = License::find($pro->json('data.id'));
    expect($license->coversVersion('9.0.0'))->toBeTrue();
});

test('the token carries the major version a lifetime license covers', function () {
    $license = License::factory()->create(['plan' => 'lifetime', 'type' => 'lifetime', 'expires_at' => null, 'updates_major' => 1]);
    $payload = json_decode(occaDecodeSegment(explode('.', occaActivate($this, $license)->json('token'))[0]), true);

    expect($payload['updates_major'])->toBe(1)
        ->and($license->coversVersion('1.9.3'))->toBeTrue()
        ->and($license->coversVersion('2.0.0'))->toBeFalse();
});

test('the update check offers the newest version the license covers', function () {
    $product = LicenseProduct::findByCode('occa');
    $product->releases()->where('version', '1.0.0')->update(['status' => 'published', 'released_at' => '2026-11-20']);
    $product->releases()->create(occaRelease(['version' => '1.0.1', 'is_critical' => true]));
    $product->releases()->create(occaRelease(['version' => '1.2.0']));
    $product->releases()->create(occaRelease(['version' => '2.0.0']));
    $product->releases()->create(occaRelease(['version' => '2.1.0', 'status' => 'draft']));
    $product->releases()->create(occaRelease(['version' => '1.3.0', 'platforms' => 'ipad']));
    $lifetime = License::factory()->create(['plan' => 'lifetime', 'type' => 'lifetime', 'expires_at' => null, 'updates_major' => 1]);
    $pro = License::factory()->create();

    $this->getJson('/api/licenses/occa/releases/latest?version=1.0.0&platform=macOS&key='.$lifetime->key)->assertOk()
        ->assertJsonPath('latest.version', '2.0.0')
        ->assertJsonPath('update.version', '1.2.0')
        ->assertJsonPath('update_available', true)
        ->assertJsonPath('upgrade_available', '2.0.0')
        ->assertJsonPath('required', true)
        ->assertJsonPath('updates_major', 1);

    $this->getJson('/api/licenses/occa/releases/latest?version=1.2.0&platform=macOS&key='.$pro->key)->assertOk()
        ->assertJsonPath('update.version', '2.0.0')
        ->assertJsonPath('upgrade_available', null)
        ->assertJsonPath('required', false);

    $this->getJson('/api/licenses/occa/releases/latest?version=2.0.0&platform=macOS')->assertOk()
        ->assertJsonPath('update', null)
        ->assertJsonPath('update_available', false);

    $this->getJson('/api/licenses/occa/releases/latest')->assertStatus(422)->assertJsonPath('error', 'invalid_request');
    $this->getJson('/api/licenses/nope/releases/latest?version=1.0.0')->assertStatus(404)->assertJsonPath('error', 'invalid_product');

    $history = $this->getJson('/api/licenses/occa/releases?platform=macOS')->assertOk();
    expect(collect($history->json('releases'))->pluck('version')->all())->toBe(['2.0.0', '1.2.0', '1.0.1', '1.0.0']);
});

test('a device reporting a new app version is logged as app_updated', function () {
    $license = License::factory()->create();
    occaActivate($this, $license, 'device-1', ['app_version' => '1.0.0'])->assertOk();

    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1', 'app_version' => '1.1.0'])->assertOk();
    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'device-1', 'app_version' => '1.1.0'])->assertOk();

    $updates = LicenseEvent::query()->where('event', LicenseEvent::APP_UPDATED)->get();
    expect($updates)->toHaveCount(1)
        ->and($updates[0]->meta)->toBe(['from' => '1.0.0', 'to' => '1.1.0'])
        ->and($license->activations()->first()->app_version)->toBe('1.1.0');

    Sanctum::actingAs($this->admin);
    LicenseProduct::findByCode('occa')->releases()->create(occaRelease());
    $list = $this->getJson('/api/v1/license-manager/products/occa/releases')->assertOk();
    expect($list->json('data.versions_in_use'))->toBe([['version' => '1.1.0', 'platform' => 'macOS', 'devices' => 1]])
        ->and(collect($list->json('data.releases'))->firstWhere('version', '1.1.0')['devices_on_version'])->toBe(1);
});

test('every version keeps its installer, downloadable in the back office and with a covering key', function () {
    Storage::fake('local');
    Sanctum::actingAs($this->admin);
    $base = '/api/v1/license-manager/products/occa/releases';
    $id = $this->postJson($base, occaRelease(['status' => 'draft']))->assertCreated()->json('data.id');

    $this->post("$base/$id/file", ['file' => UploadedFile::fake()->create('notes.txt', 10)], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('file');
    $upload = $this->post("$base/$id/file", ['file' => UploadedFile::fake()->create('OCCA.dmg', 2048)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.file.name', 'occa-1.1.0.dmg')
        ->assertJsonPath('data.file.size', 2048 * 1024);
    expect($upload->json('data.file.sha256'))->toHaveLength(64);
    Storage::disk('local')->assertExists('license-releases/occa/1.1.0/occa-1.1.0.dmg');

    $this->get("$base/$id/file")->assertOk()->assertDownload('occa-1.1.0.dmg');
    $this->putJson("$base/$id", ['status' => 'published', 'released_at' => '2026-12-01'])->assertOk()->assertJsonPath('data.status', 'published');
    // Published: the installer stays (replace only).
    $this->deleteJson("$base/$id/file")->assertStatus(422)->assertJsonValidationErrors('file');

    $mac = License::factory()->create();
    $ipad = License::factory()->create(['platform' => 'ipad', 'key' => 'OC52-AAAA-BBBB-CCCC-0001']);
    $oldLifetime = License::factory()->create(['plan' => 'lifetime', 'type' => 'lifetime', 'expires_at' => null, 'updates_major' => 0 + 1]);
    LicenseProduct::findByCode('occa')->releases()->create(occaRelease(['version' => '2.0.0', 'file_path' => 'license-releases/occa/1.1.0/occa-1.1.0.dmg', 'file_name' => 'occa-2.0.0.dmg']));

    $history = $this->getJson('/api/licenses/occa/releases?platform=macOS')->assertOk();
    expect(collect($history->json('releases'))->firstWhere('version', '1.1.0')['download_url'])->toEndWith('/api/licenses/occa/releases/1.1.0/download');

    $this->get('/api/licenses/occa/releases/1.1.0/download?key='.$mac->key)->assertOk()->assertDownload('occa-1.1.0.dmg');
    $this->getJson('/api/licenses/occa/releases/1.1.0/download')->assertStatus(422);
    $this->getJson('/api/licenses/occa/releases/1.1.0/download?key=OC32-NOPE-NOPE-NOPE-9999')->assertStatus(404)->assertJsonPath('error', 'invalid_key');
    $this->getJson('/api/licenses/occa/releases/9.9.9/download?key='.$mac->key)->assertStatus(404);
    $this->getJson('/api/licenses/occa/releases/1.1.0/download?key='.$ipad->key)->assertStatus(403)->assertJsonPath('error', 'wrong_platform');
    $this->getJson('/api/licenses/occa/releases/2.0.0/download?key='.$oldLifetime->key)->assertStatus(403)->assertJsonPath('error', 'upgrade_required');
    $this->get('/api/licenses/occa/releases/1.1.0/download?key='.$oldLifetime->key)->assertOk();
});

test('versions have their own permission module, copied from license manager on existing roles', function () {
    // A role that only has license-manager-* gets the matching license-release-* from the migration.
    $role = Role::firstOrCreate(['name' => 'license-viewer', 'guard_name' => 'sanctum']);
    $role->givePermissionTo(['license-manager-menu', 'license-manager-view']);
    Permission::query()->where('name', 'like', 'license-release-%')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require base_path('database/migrations/2026_11_10_000014_add_license_release_permissions.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe([
        'license-manager-menu', 'license-manager-view',
        'license-release-download', 'license-release-menu', 'license-release-view',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole('license-viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/license-manager/products/occa/releases')->assertOk();
    $this->postJson('/api/v1/license-manager/products/occa/releases', occaRelease(['status' => 'draft']))->assertStatus(403);

    // A versions-only user still gets the product list (the Versions page needs it).
    $versionsOnly = User::factory()->create();
    $versionsOnly->givePermissionTo('license-release-menu');
    Sanctum::actingAs($versionsOnly);
    $this->getJson('/api/v1/license-manager/products')->assertOk();

    // license-manager-* alone is not enough for versions.
    $licenseOnly = User::factory()->create();
    $licenseOnly->givePermissionTo('license-manager-create');
    Sanctum::actingAs($licenseOnly);
    $this->postJson('/api/v1/license-manager/products/occa/releases', occaRelease(['status' => 'draft']))->assertStatus(403);
});

// ---------------------------------------------------------------------
// Sharing the certificate: email (PDF attached) and a 7-day WhatsApp link
// ---------------------------------------------------------------------

test('the certificate can be emailed with the PDF attached, for a bundle once with both keys', function () {
    Mail::fake();
    Sanctum::actingAs($this->admin);

    $mac = License::find($this->postJson(lmLicenses(), occaIndividual(['platform' => 'both']))->json('data.id'));
    $ipad = License::where('bundle_id', $mac->bundle_id)->where('platform', 'ipad')->first();

    $this->postJson(lmLicenses()."/{$ipad->id}/certificate/email", [
        'to' => 'jane@example.com', 'cc' => ['boss@example.com'], 'recipient_name' => 'Jane', 'message' => 'Welcome aboard!',
    ])->assertOk()->assertJsonPath('data.to', 'jane@example.com');

    Mail::assertSent(LicenseCertificateMail::class, function ($mail) use ($mac, $ipad) {
        $html = $mail->render();

        return $mail->hasTo('jane@example.com') && $mail->hasCc('boss@example.com')
            && count($mail->attachments()) === 1
            && str_starts_with($mail->pdf, '%PDF')
            && $mail->filename === "occa-License-{$mac->key}_{$ipad->key}.pdf"
            && str_contains($html, $mac->key) && str_contains($html, $ipad->key)
            && str_contains($html, 'Dear Jane,') && str_contains($html, 'Welcome aboard!');
    });
    expect(occaEvents($ipad, LicenseEvent::CERTIFICATE_EMAILED)->first()->meta)
        ->toMatchArray(['to' => 'jane@example.com', 'cc' => ['boss@example.com'], 'reference' => 'LIC-OCCA-MAC-0001+IPAD-0001']);

    $this->postJson(lmLicenses()."/{$ipad->id}/certificate/email", ['to' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('to');
    $this->postJson(lmLicenses().'/999999/certificate/email', ['to' => 'a@example.com'])->assertStatus(404);
});

test('a WhatsApp share link opens the certificate without login for 7 days, and a tampered or expired link is refused', function () {
    Sanctum::actingAs($this->admin);
    $license = License::find($this->postJson(lmLicenses(), occaIndividual())->json('data.id'));

    $response = $this->postJson(lmLicenses()."/{$license->id}/certificate/share-link", ['phone' => '0812 3456 7890'])->assertOk();
    $url = $response->json('data.url');
    expect($url)->toContain('/license-certificate/'.$license->id)->toContain('signature=')
        ->and(Carbon::parse($response->json('data.expires_at'))->diffInDays(now(), true))->toBeGreaterThan(6.9)
        ->and(occaEvents($license, LicenseEvent::CERTIFICATE_SHARED_WHATSAPP)->first()->meta['phone'])->toBe('0812 3456 7890');

    // Public: no auth needed.
    app('auth')->forgetGuards();
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $this->get($path)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(occaEvents($license, LicenseEvent::CERTIFICATE_LINK_OPENED)->count())->toBe(1);

    // Another license id with the same signature, or no signature: refused.
    $this->get(str_replace("/license-certificate/{$license->id}?", '/license-certificate/'.($license->id + 1).'?', $path))->assertStatus(403)->assertSee('not available');
    $this->get("/license-certificate/{$license->id}")->assertStatus(403);

    // After 7 days the link no longer works.
    Carbon::setTestNow(now()->addDays(7)->addMinute());
    $this->get($path)->assertStatus(403)->assertSee('expired');
    Carbon::setTestNow();
});

test('sharing the certificate needs license-manager-view', function () {
    $license = License::factory()->create();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(lmLicenses()."/{$license->id}/certificate/email", ['to' => 'a@example.com'])->assertStatus(403);
    $this->postJson(lmLicenses()."/{$license->id}/certificate/share-link")->assertStatus(403);
});

test('an empty region list is never cached, so rows added later (not via the import) show up at once', function () {
    Sanctum::actingAs($this->admin);
    DB::table('regions_provinces')->delete();

    // Opened while the table is still empty: no list, and not cacheable.
    $this->getJson('/api/v1/regions/provinces')->assertOk()->assertJsonPath('data', [])
        ->assertHeader('Cache-Control', 'no-store, private');

    // Filled by plain SQL (no regions:import, so no cache version bump).
    DB::table('regions_provinces')->insert([['code' => '32', 'name' => 'Jawa Barat'], ['code' => '31', 'name' => 'DKI Jakarta']]);

    $this->getJson('/api/v1/regions/provinces')->assertOk()
        ->assertJsonPath('data', [['code' => '31', 'name' => 'DKI Jakarta'], ['code' => '32', 'name' => 'Jawa Barat']])
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    // A non-empty list is cached as before.
    expect(Cache::get(IndonesianRegions::cacheKey('province', null)))->toHaveCount(2);
});

// ---------------------------------------------------------------------
// One key per device: max_devices unique keys, each for one device;
// a key whose device was released never works again.
// ---------------------------------------------------------------------

function perDeviceLicense($test, int $devices = 3, array $overrides = []): License
{
    Sanctum::actingAs($test->admin);
    $id = $test->postJson(lmLicenses(), occaIndividual(array_merge(['key_mode' => 'per_device', 'max_devices' => $devices], $overrides)))
        ->assertStatus(201)->json('data.id');

    return License::with('keys')->find($id);
}

test('a per-device license gets one unique key per device, all with the license number', function () {
    $license = perDeviceLicense($this, 3);
    $keys = $license->keys;

    expect($license->key_mode)->toBe('per_device')
        ->and($keys)->toHaveCount(3)
        ->and($keys->pluck('position')->all())->toBe([1, 2, 3])
        ->and($keys->first()->key)->toBe($license->key)
        ->and($keys->pluck('key')->unique())->toHaveCount(3)
        ->and($keys->pluck('status')->unique()->all())->toBe(['available']);
    foreach ($keys as $key) {
        expect($key->key)->toMatch(OCCA_KEY_REGEX)->toStartWith('OC32-')->toEndWith('-0001');
    }

    $this->getJson(lmLicenses()."/{$license->id}")->assertOk()
        ->assertJsonPath('data.key_mode', 'per_device')
        ->assertJsonPath('data.key_mode_label', 'One key per device')
        ->assertJsonPath('data.keys_summary', ['total' => 3, 'available' => 3, 'in_use' => 0, 'used' => 0])
        ->assertJsonCount(3, 'data.keys');
    // The list shows the summary too, and finds the license by any of its keys.
    $this->getJson(lmLicenses().'/all/paginated?search='.$keys[2]->key)->assertOk()
        ->assertJsonPath('data.data.0.id', $license->id)
        ->assertJsonPath('data.data.0.keys_summary.total', 3);
});

test('each per-device key activates exactly one device, and a released key never works again', function () {
    $license = perDeviceLicense($this, 3);
    [$k1, $k2, $k3] = $license->keys->pluck('key')->all();
    $activate = fn ($key, $device) => $this->postJson('/api/occa/license/activate', ['key' => $key, 'device_id' => $device, 'device_name' => "Mac {$device}", 'platform' => 'macOS']);

    // Key 1 on device A; the token carries key 1.
    $token = $activate($k1, 'A')->assertOk()->assertJsonPath('license.key', $k1)->json('token');
    expect(app(LicenseTokenService::class)->verify($token)['key'])->toBe($k1);
    // Same key, same device (reinstall): fine. Same key, another device: refused.
    $activate($k1, 'A')->assertOk();
    $activate($k1, 'B')->assertStatus(409)->assertJsonPath('error', 'key_in_use');
    // Key 2 on device B.
    $activate($k2, 'B')->assertOk()->assertJsonPath('license.key', $k2);

    $statuses = fn () => $license->keys()->pluck('status', 'position')->all();
    expect($statuses())->toBe([1 => 'in_use', 2 => 'in_use', 3 => 'available'])
        ->and($license->keys()->where('position', 1)->value('device_name'))->toBe('Mac A');

    // Refresh only works for the device the key is bound to.
    $this->postJson('/api/occa/license/refresh', ['key' => $k1, 'device_id' => 'A'])->assertOk()->assertJsonPath('license.key', $k1);
    $this->postJson('/api/occa/license/refresh', ['key' => $k2, 'device_id' => 'A'])->assertStatus(404)->assertJsonPath('error', 'not_activated');

    // Released from the app: key 1 is used up, also on the same device.
    $this->postJson('/api/occa/license/deactivate', ['key' => $k1, 'device_id' => 'A'])->assertExactJson(['ok' => true]);
    expect($statuses()[1])->toBe('used')->and($license->keys()->where('position', 1)->value('used_at'))->not->toBeNull();
    $activate($k1, 'A')->assertStatus(403)->assertJsonPath('error', 'key_used');
    $activate($k1, 'C')->assertStatus(403)->assertJsonPath('error', 'key_used');
    $this->postJson('/api/occa/license/refresh', ['key' => $k1, 'device_id' => 'A'])->assertStatus(403)->assertJsonPath('error', 'key_used');
    expect(occaEvents($license, LicenseEvent::DENIED_KEY_USED)->count())->toBeGreaterThanOrEqual(2)
        ->and(occaEvents($license, LicenseEvent::DENIED_KEY_IN_USE)->count())->toBe(1);

    // Released by an admin: key 2 is used up as well.
    Sanctum::actingAs($this->admin);
    $activation = $license->activations()->where('device_id', 'B')->first();
    $this->deleteJson(lmLicenses()."/{$license->id}/activations/{$activation->id}")->assertOk();
    expect($statuses())->toBe([1 => 'used', 2 => 'used', 3 => 'available']);
    $activate($k2, 'D')->assertStatus(403)->assertJsonPath('error', 'key_used');

    // Key 3 is still good.
    $activate($k3, 'D')->assertOk();
    $this->getJson(lmLicenses()."/{$license->id}")->assertJsonPath('data.keys_summary', ['total' => 3, 'available' => 0, 'in_use' => 1, 'used' => 2]);
});

test('raising Max devices adds keys; lowering removes unused keys but never below the used ones', function () {
    $license = perDeviceLicense($this, 3);
    [$k1, $k2] = $license->keys->pluck('key')->all();
    $this->postJson('/api/occa/license/activate', ['key' => $k1, 'device_id' => 'A', 'platform' => 'macOS'])->assertOk();
    $this->postJson('/api/occa/license/activate', ['key' => $k2, 'device_id' => 'B', 'platform' => 'macOS'])->assertOk();

    Sanctum::actingAs($this->admin);
    $this->putJson(lmLicenses()."/{$license->id}", ['max_devices' => 5])->assertOk()->assertJsonCount(5, 'data.keys');
    expect($license->keys()->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($license->keys()->pluck('key')->every(fn ($key) => str_ends_with($key, '-0001')))->toBeTrue();

    $this->putJson(lmLicenses()."/{$license->id}", ['max_devices' => 1])->assertStatus(422)->assertJsonValidationErrors('max_devices');
    expect($license->keys()->count())->toBe(5);

    $this->putJson(lmLicenses()."/{$license->id}", ['max_devices' => 2])->assertOk()->assertJsonCount(2, 'data.keys');
    expect($license->keys()->pluck('key')->all())->toBe([$k1, $k2]);
});

test('a shared-key license works as before and has no per-device keys', function () {
    Sanctum::actingAs($this->admin);
    $id = $this->postJson(lmLicenses(), occaIndividual(['max_devices' => 3]))->assertStatus(201)
        ->assertJsonPath('data.key_mode', 'shared')->assertJsonMissingPath('data.keys_summary')->json('data.id');
    $license = License::find($id);

    expect($license->keys()->count())->toBe(0);
    foreach (['A', 'B', 'C'] as $device) {
        $this->postJson('/api/occa/license/activate', ['key' => $license->key, 'device_id' => $device, 'platform' => 'macOS'])->assertOk();
    }
    $this->postJson('/api/occa/license/activate', ['key' => $license->key, 'device_id' => 'D', 'platform' => 'macOS'])->assertStatus(409)->assertJsonPath('error', 'device_limit');
    // The key mode is fixed after creation.
    $this->putJson(lmLicenses()."/{$id}", ['key_mode' => 'per_device'])->assertOk();
    expect($license->fresh()->key_mode)->toBe('shared');
});

test('a Mac + iPad bundle with one key per device gets keys for both licenses', function () {
    $mac = perDeviceLicense($this, 2, ['platform' => 'both']);
    $ipad = License::where('bundle_id', $mac->bundle_id)->where('platform', 'ipad')->first();

    expect($mac->keys)->toHaveCount(2)->and($ipad->keys()->count())->toBe(2)
        ->and($ipad->keys()->pluck('key')->every(fn ($key) => str_starts_with($key, 'OC52-')))->toBeTrue();

    $this->postJson('/api/occa/license/activate', ['key' => $ipad->keys()->value('key'), 'device_id' => 'ipad-1', 'platform' => 'iPadOS'])->assertOk();
    $this->postJson('/api/occa/license/activate', ['key' => $mac->keys->first()->key, 'device_id' => 'ipad-2', 'platform' => 'iPadOS'])->assertStatus(403)->assertJsonPath('error', 'wrong_platform');
});

test('the key migration turns existing licenses into per-device ones, keeping the key and its device', function () {
    $license = License::factory()->create(['max_devices' => 3]);
    $this->postJson('/api/occa/license/activate', ['key' => $license->key, 'device_id' => 'old-mac', 'device_name' => 'Old Mac', 'platform' => 'macOS'])->assertOk();

    (require base_path('database/migrations/2026_11_11_000001_create_license_keys_table.php'))->down();
    (require base_path('database/migrations/2026_11_11_000001_create_license_keys_table.php'))->up();

    $license = License::with('keys')->find($license->id);
    expect($license->key_mode)->toBe('per_device')
        ->and($license->keys)->toHaveCount(3)
        ->and($license->keys[0]->only(['key', 'status', 'device_id', 'device_name']))->toBe(['key' => $license->key, 'status' => 'in_use', 'device_id' => 'old-mac', 'device_name' => 'Old Mac'])
        ->and($license->keys[1]->status)->toBe('available')
        ->and($license->activations()->first()->license_key_id)->toBe($license->keys[0]->id);

    // The old device keeps refreshing with its key; another device needs another key.
    $this->postJson('/api/occa/license/refresh', ['key' => $license->key, 'device_id' => 'old-mac'])->assertOk();
    $this->postJson('/api/occa/license/activate', ['key' => $license->key, 'device_id' => 'new-mac', 'platform' => 'macOS'])->assertStatus(409)->assertJsonPath('error', 'key_in_use');
    $this->postJson('/api/occa/license/activate', ['key' => $license->keys[1]->key, 'device_id' => 'new-mac', 'platform' => 'macOS'])->assertOk();
});

test('license:create --key-mode=per_device makes one key per device', function () {
    $this->artisan('license:create', [...OCCA_CLI_PERSON, '--name' => 'Seat User', '--key-mode' => 'per_device', '--max-devices' => 4, '--months' => 12])->assertSuccessful();
    $license = License::where('licensee_name', 'Seat User')->first();
    expect($license->key_mode)->toBe('per_device')->and($license->keys()->count())->toBe(4);
});

test('the certificate of a per-device license lists every key with its status', function () {
    $license = perDeviceLicense($this, 3);
    [$k1, $k2, $k3] = $license->keys->pluck('key')->all();
    $this->postJson('/api/occa/license/activate', ['key' => $k1, 'device_id' => 'A', 'device_name' => 'Studio Mac', 'platform' => 'macOS'])->assertOk();
    $this->postJson('/api/occa/license/activate', ['key' => $k2, 'device_id' => 'B', 'device_name' => 'Old Mac', 'platform' => 'macOS'])->assertOk();
    $this->postJson('/api/occa/license/deactivate', ['key' => $k2, 'device_id' => 'B'])->assertOk();

    $data = app(LicenseCertificateService::class)->data($license->fresh());
    expect($data['perDevice'])->toBeTrue()->and($data['keysPerLicense'])->toBe(3);

    $html = view('pdf.license-certificate', $data)->render();
    expect($html)->toContain('key 1 of 3')->toContain('Device Keys')->toContain('One key per device')
        ->toContain($k1)->toContain($k2)->toContain($k3)
        ->toContain('In use')->toContain('Studio Mac')->toContain('was Old Mac')->toContain('Available');
});

test('the email of a per-device license lists up to ten keys, more are referred to the PDF', function () {
    Mail::fake();
    $small = perDeviceLicense($this, 3);
    $large = perDeviceLicense($this, 12);

    $this->postJson(lmLicenses()."/{$small->id}/certificate/email", ['to' => 'a@example.com'])->assertOk();
    $this->postJson(lmLicenses()."/{$large->id}/certificate/email", ['to' => 'b@example.com'])->assertOk();

    Mail::assertSent(LicenseCertificateMail::class, function ($mail) use ($small) {
        if (! $mail->hasTo('a@example.com')) {
            return false;
        }
        $html = $mail->render();

        return $small->keys->every(fn ($key) => str_contains($html, $key->key)) && str_contains($html, 'one key each');
    });
    Mail::assertSent(LicenseCertificateMail::class, function ($mail) use ($large) {
        if (! $mail->hasTo('b@example.com')) {
            return false;
        }
        $html = $mail->render();

        return str_contains($html, 'Listed on the Device Keys page') && ! str_contains($html, $large->keys->last()->key);
    });
});

// ---------------------------------------------------------------------
// One-key certificates: download / email / share a single device key
// ---------------------------------------------------------------------

test('a certificate can be made for one key: download, email and share link', function () {
    Mail::fake();
    $license = perDeviceLicense($this, 3);
    [$k1, $k2, $k3] = $license->keys->all();

    // The service: only that key, no Device Keys page, its own reference.
    $data = app(LicenseCertificateService::class)->data($license->fresh(), $k2);
    $html = view('pdf.license-certificate', $data)->render();
    expect($data['reference'])->toBe('LIC-OCCA-MAC-0001-K02')
        ->and($html)->toContain($k2->key)->toContain('key 2 of 3')->toContain('1 device (this key)')
        ->not->toContain($k3->key)->not->toContain('Your license keys');

    // Download.
    $response = $this->get(lmLicenses()."/{$license->id}/certificate?key_id={$k2->id}")->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain("occa-License-{$k2->key}.pdf")
        ->and(occaEvents($license, LicenseEvent::CERTIFICATE_DOWNLOADED)->first()->meta)
        ->toMatchArray(['reference' => 'LIC-OCCA-MAC-0001-K02', 'key' => $k2->key, 'key_position' => 2]);

    // Email: the body and the PDF name carry only that key.
    $this->postJson(lmLicenses()."/{$license->id}/certificate/email", ['to' => 'member@example.com', 'key_id' => $k3->id])->assertOk();
    Mail::assertSent(LicenseCertificateMail::class, function ($mail) use ($k1, $k3) {
        $html = $mail->render();

        return $mail->filename === "occa-License-{$k3->key}.pdf"
            && str_contains($html, $k3->key) && str_contains($html, 'key 3 of 3') && ! str_contains($html, $k1->key);
    });

    // Share link: the key is signed into the URL.
    $url = $this->postJson(lmLicenses()."/{$license->id}/certificate/share-link", ['key_id' => $k2->id])->assertOk()->json('data.url');
    expect($url)->toContain('key='.$k2->id);
    app('auth')->forgetGuards();
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $opened = $this->get($path)->assertOk();
    expect($opened->headers->get('Content-Disposition'))->toContain($k2->key);
    // Swapping the key breaks the signature.
    $this->get(str_replace('key='.$k2->id, 'key='.$k3->id, $path))->assertStatus(403);
});

test('a one-key certificate refuses a used key or a key of another license', function () {
    $license = perDeviceLicense($this, 2);
    $other = perDeviceLicense($this, 2);
    [$k1, $k2] = $license->keys->all();
    $this->postJson('/api/occa/license/activate', ['key' => $k2->key, 'device_id' => 'B', 'platform' => 'macOS'])->assertOk();
    $this->postJson('/api/occa/license/deactivate', ['key' => $k2->key, 'device_id' => 'B'])->assertOk();

    $this->getJson(lmLicenses()."/{$license->id}/certificate?key_id={$k2->id}")->assertStatus(422);
    $this->getJson(lmLicenses()."/{$license->id}/certificate?key_id={$other->keys->first()->id}")->assertStatus(404);
    $this->postJson(lmLicenses()."/{$license->id}/certificate/share-link", ['key_id' => $k2->id])->assertStatus(422);
    $this->get(lmLicenses()."/{$license->id}/certificate?key_id={$k1->id}")->assertOk();
});

test('a bundle key certificate covers only the platform of that key', function () {
    $mac = perDeviceLicense($this, 2, ['platform' => 'both']);
    $ipad = License::with('keys')->where('bundle_id', $mac->bundle_id)->where('platform', 'ipad')->first();
    $ipadKey = $ipad->keys->last();

    // Asked from the Mac license, for an iPad key.
    $response = $this->get(lmLicenses()."/{$mac->id}/certificate?key_id={$ipadKey->id}")->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain($ipadKey->key);

    $data = app(LicenseCertificateService::class)->data($mac, $ipadKey);
    expect($data['isBundle'])->toBeFalse()->and($data['license']->id)->toBe($ipad->id)
        ->and($data['reference'])->toBe('LIC-OCCA-IPAD-0001-K02');
});

test('activate and refresh tell the app the key mode and, per device, which key it is', function () {
    $license = perDeviceLicense($this, 3);
    $k2 = $license->keys->firstWhere('position', 2);

    $this->postJson('/api/occa/license/activate', ['key' => $k2->key, 'device_id' => 'B', 'platform' => 'macOS'])->assertOk()
        ->assertJsonPath('license.key', $k2->key)
        ->assertJsonPath('license.key_mode', 'per_device')
        ->assertJsonPath('license.key_position', 2)
        ->assertJsonPath('license.keys_total', 3);
    $this->postJson('/api/occa/license/refresh', ['key' => $k2->key, 'device_id' => 'B', 'platform' => 'macOS'])->assertOk()
        ->assertJsonPath('license.key_mode', 'per_device')
        ->assertJsonPath('license.key_position', 2);

    $shared = License::find($this->postJson(lmLicenses(), occaIndividual(['key_mode' => 'shared']))->json('data.id'));
    $this->postJson('/api/occa/license/activate', ['key' => $shared->key, 'device_id' => 'S', 'platform' => 'macOS'])->assertOk()
        ->assertJsonPath('license.key_mode', 'shared')
        ->assertJsonPath('license.key_position', null)
        ->assertJsonPath('license.keys_total', null);
});
