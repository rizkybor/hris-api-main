<?php

use App\Models\CredentialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    $hrRole = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);

    // Create permissions
    Permission::firstOrCreate(['name' => 'credential-account-list', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'credential-account-create', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'credential-account-edit', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'credential-account-delete', 'guard_name' => 'sanctum']);

    // Assign permissions to roles
    $managerRole->givePermissionTo([
        'credential-account-list',
        'credential-account-create',
        'credential-account-edit',
        'credential-account-delete',
    ]);

    $hrRole->givePermissionTo([
        'credential-account-list',
        'credential-account-create',
        'credential-account-edit',
        'credential-account-delete',
    ]);

    // Create test user
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
});

test('unauthenticated user cannot access credential accounts', function () {
    $response = $this->getJson('/api/v1/credential-accounts');

    $response->assertStatus(401);
});

test('authenticated user can list credential accounts', function () {
    Sanctum::actingAs($this->user);

    CredentialAccount::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/credential-accounts');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'label_password',
                    'username_email',
                    'password',
                    'website',
                    'notes',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('authenticated user can get paginated credential accounts', function () {
    Sanctum::actingAs($this->user);

    CredentialAccount::factory()->count(15)->create();

    $response = $this->getJson('/api/v1/credential-accounts/all/paginated?row_per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'label_password',
                        'username_email',
                        'password',
                        'website',
                        'notes',
                    ],
                ],
                'current_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('data.data'))->toHaveCount(10);
    expect($response->json('data.total'))->toBe(15);
});

test('authenticated user can search credential accounts', function () {
    Sanctum::actingAs($this->user);

    CredentialAccount::create([
        'label_password' => 'GitHub Account',
        'username_email' => 'dev@test.com',
        'password' => 'password123',
        'website' => 'https://github.com',
        'notes' => 'Development account',
    ]);

    CredentialAccount::create([
        'label_password' => 'AWS Console',
        'username_email' => 'aws@test.com',
        'password' => 'password456',
        'website' => 'https://aws.com',
        'notes' => 'Cloud account',
    ]);

    $response = $this->getJson('/api/v1/credential-accounts?search=GitHub');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.label_password'))->toBe('GitHub Account');
});

test('authenticated user can create credential account', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'label_password' => 'New Account',
        'username_email' => 'new@test.com',
        'password' => 'SecurePassword123!',
        'website' => 'https://example.com',
        'notes' => 'Test account',
    ];

    $response = $this->postJson('/api/v1/credential-accounts', $data);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'label_password',
                'username_email',
                'password',
                'website',
                'notes',
            ],
        ]);

    expect($response->json('data.label_password'))->toBe('New Account');
    expect($response->json('data.username_email'))->toBe('new@test.com');

    $this->assertDatabaseHas('credential_accounts', [
        'label_password' => 'New Account',
        'username_email' => 'new@test.com',
    ]);
});

test('authenticated user cannot create credential account without required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/credential-accounts', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['label_password', 'username_email', 'password']);
});

test('authenticated user can view credential account', function () {
    Sanctum::actingAs($this->user);

    $account = CredentialAccount::create([
        'label_password' => 'Test Account',
        'username_email' => 'test@test.com',
        'password' => 'password123',
        'website' => 'https://test.com',
        'notes' => 'Test notes',
    ]);

    $response = $this->getJson("/api/v1/credential-accounts/{$account->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'label_password',
                'username_email',
                'password',
                'website',
                'notes',
            ],
        ]);

    expect($response->json('data.id'))->toBe($account->id);
    expect($response->json('data.label_password'))->toBe('Test Account');
});

test('authenticated user gets 404 for non-existent credential account', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/credential-accounts/99999');

    $response->assertStatus(404);
});

test('authenticated user can update credential account', function () {
    Sanctum::actingAs($this->user);

    $account = CredentialAccount::create([
        'label_password' => 'Old Label',
        'username_email' => 'old@test.com',
        'password' => 'oldpassword',
        'website' => 'https://old.com',
        'notes' => 'Old notes',
    ]);

    $updateData = [
        'label_password' => 'Updated Label',
        'username_email' => 'updated@test.com',
        'password' => 'newpassword',
        'website' => 'https://updated.com',
        'notes' => 'Updated notes',
    ];

    $response = $this->putJson("/api/v1/credential-accounts/{$account->id}", $updateData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'label_password',
                'username_email',
                'password',
                'website',
                'notes',
            ],
        ]);

    expect($response->json('data.label_password'))->toBe('Updated Label');
    expect($response->json('data.username_email'))->toBe('updated@test.com');

    $this->assertDatabaseHas('credential_accounts', [
        'id' => $account->id,
        'label_password' => 'Updated Label',
        'username_email' => 'updated@test.com',
    ]);
});

test('authenticated user can partially update credential account', function () {
    Sanctum::actingAs($this->user);

    $account = CredentialAccount::create([
        'label_password' => 'Original Label',
        'username_email' => 'original@test.com',
        'password' => 'originalpassword',
        'website' => 'https://original.com',
        'notes' => 'Original notes',
    ]);

    $updateData = [
        'label_password' => 'Partially Updated',
    ];

    $response = $this->putJson("/api/v1/credential-accounts/{$account->id}", $updateData);

    $response->assertStatus(200);
    expect($response->json('data.label_password'))->toBe('Partially Updated');
    expect($response->json('data.username_email'))->toBe('original@test.com'); // Should remain unchanged
});

test('authenticated user can delete credential account', function () {
    Sanctum::actingAs($this->user);

    $account = CredentialAccount::create([
        'label_password' => 'To Delete',
        'username_email' => 'delete@test.com',
        'password' => 'password123',
        'website' => 'https://delete.com',
        'notes' => 'Will be deleted',
    ]);

    $response = $this->deleteJson("/api/v1/credential-accounts/{$account->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Credential Account Deleted Successfully',
        ]);

    $this->assertSoftDeleted('credential_accounts', [
        'id' => $account->id,
    ]);
});

test('authenticated user gets 404 when deleting non-existent credential account', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson('/api/v1/credential-accounts/99999');

    $response->assertStatus(404);
});

