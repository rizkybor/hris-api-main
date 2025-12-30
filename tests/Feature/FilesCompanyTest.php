<?php

use App\Models\FilesCompany;
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
    Permission::firstOrCreate(['name' => 'files-company-list', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'files-company-create', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'files-company-edit', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'files-company-delete', 'guard_name' => 'sanctum']);

    // Assign permissions to roles
    $managerRole->givePermissionTo([
        'files-company-list',
        'files-company-create',
        'files-company-edit',
        'files-company-delete',
    ]);

    $hrRole->givePermissionTo([
        'files-company-list',
        'files-company-create',
        'files-company-edit',
        'files-company-delete',
    ]);

    // Create test user
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
});

test('unauthenticated user cannot access company files', function () {
    $response = $this->getJson('/api/v1/files-companies');

    $response->assertStatus(401);
});

test('authenticated user can list company files', function () {
    Sanctum::actingAs($this->user);

    FilesCompany::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/files-companies');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'path',
                    'name',
                    'description',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('authenticated user can get paginated company files', function () {
    Sanctum::actingAs($this->user);

    FilesCompany::factory()->count(15)->create();

    $response = $this->getJson('/api/v1/files-companies/all/paginated?row_per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'path',
                        'name',
                        'description',
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

test('authenticated user can search company files', function () {
    Sanctum::actingAs($this->user);

    FilesCompany::create([
        'path' => 'company-files/policies/handbook.pdf',
        'name' => 'Employee Handbook',
        'description' => 'Company policies and procedures',
    ]);

    FilesCompany::create([
        'path' => 'company-files/legal/contract.pdf',
        'name' => 'Employment Contract',
        'description' => 'Standard employment contract',
    ]);

    $response = $this->getJson('/api/v1/files-companies?search=Handbook');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Employee Handbook');
});

test('authenticated user can create company file', function () {
    Sanctum::actingAs($this->user);

    $data = [
        'path' => 'company-files/test/document.pdf',
        'name' => 'Test Document',
        'description' => 'This is a test document',
    ];

    $response = $this->postJson('/api/v1/files-companies', $data);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'path',
                'name',
                'description',
            ],
        ]);

    expect($response->json('data.name'))->toBe('Test Document');
    expect($response->json('data.path'))->toBe('company-files/test/document.pdf');

    $this->assertDatabaseHas('files_companies', [
        'name' => 'Test Document',
        'path' => 'company-files/test/document.pdf',
    ]);
});

test('authenticated user cannot create company file without required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/files-companies', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['path', 'name']);
});

test('authenticated user can view company file', function () {
    Sanctum::actingAs($this->user);

    $file = FilesCompany::create([
        'path' => 'company-files/test/file.pdf',
        'name' => 'Test File',
        'description' => 'Test description',
    ]);

    $response = $this->getJson("/api/v1/files-companies/{$file->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'path',
                'name',
                'description',
            ],
        ]);

    expect($response->json('data.id'))->toBe($file->id);
    expect($response->json('data.name'))->toBe('Test File');
});

test('authenticated user gets 404 for non-existent company file', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/files-companies/99999');

    $response->assertStatus(404);
});

test('authenticated user can update company file', function () {
    Sanctum::actingAs($this->user);

    $file = FilesCompany::create([
        'path' => 'company-files/old/path.pdf',
        'name' => 'Old Name',
        'description' => 'Old description',
    ]);

    $updateData = [
        'path' => 'company-files/new/path.pdf',
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ];

    $response = $this->putJson("/api/v1/files-companies/{$file->id}", $updateData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'path',
                'name',
                'description',
            ],
        ]);

    expect($response->json('data.name'))->toBe('Updated Name');
    expect($response->json('data.path'))->toBe('company-files/new/path.pdf');

    $this->assertDatabaseHas('files_companies', [
        'id' => $file->id,
        'name' => 'Updated Name',
        'path' => 'company-files/new/path.pdf',
    ]);
});

test('authenticated user can partially update company file', function () {
    Sanctum::actingAs($this->user);

    $file = FilesCompany::create([
        'path' => 'company-files/original/path.pdf',
        'name' => 'Original Name',
        'description' => 'Original description',
    ]);

    $updateData = [
        'name' => 'Partially Updated',
    ];

    $response = $this->putJson("/api/v1/files-companies/{$file->id}", $updateData);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Partially Updated');
    expect($response->json('data.path'))->toBe('company-files/original/path.pdf'); // Should remain unchanged
});

test('authenticated user can delete company file', function () {
    Sanctum::actingAs($this->user);

    $file = FilesCompany::create([
        'path' => 'company-files/to-delete/file.pdf',
        'name' => 'To Delete',
        'description' => 'Will be deleted',
    ]);

    $response = $this->deleteJson("/api/v1/files-companies/{$file->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Company File Deleted Successfully',
        ]);

    $this->assertSoftDeleted('files_companies', [
        'id' => $file->id,
    ]);
});

test('authenticated user gets 404 when deleting non-existent company file', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson('/api/v1/files-companies/99999');

    $response->assertStatus(404);
});

