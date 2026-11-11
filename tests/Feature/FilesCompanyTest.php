<?php

use App\Models\FilesCompany;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Http\UploadedFile;
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

    // Uploads go to Cloudinary; the tests check our side of the contract only.
    $this->cloudinary = $this->mock(CloudinaryManager::class, function ($mock) {
        $mock->shouldReceive('uploadAuto')->andReturn('hris/company-files/general/company-file-test')->byDefault();
        $mock->shouldReceive('delete')->andReturnNull()->byDefault();
    });
});

test('unauthenticated user cannot access company files', function () {
    $this->getJson('/api/v1/files-companies')->assertStatus(401);
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
                '*' => ['id', 'document_name', 'document_path', 'type_file', 'size_file', 'description', 'created_at', 'updated_at'],
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
                'data' => ['*' => ['id', 'document_name', 'document_path', 'description']],
                'meta' => ['current_page', 'per_page', 'total'],
            ],
        ]);
    expect($response->json('data.data'))->toHaveCount(10)
        ->and($response->json('data.meta.total'))->toBe(15);
});

test('authenticated user can search company files', function () {
    Sanctum::actingAs($this->user);
    FilesCompany::factory()->create(['document_name' => 'Employee Handbook', 'description' => 'Company policies']);
    FilesCompany::factory()->create(['document_name' => 'Employment Contract', 'description' => 'Standard contract']);

    $response = $this->getJson('/api/v1/files-companies?search=Handbook');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.document_name'))->toBe('Employee Handbook');
});

test('authenticated user can create company file', function () {
    Sanctum::actingAs($this->user);

    $response = $this->post('/api/v1/files-companies', [
        'document_name' => 'Test Document',
        'description' => 'This is a test document',
        'document_path' => UploadedFile::fake()->create('document.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'document_name', 'document_path', 'type_file', 'size_file', 'description']]);
    expect($response->json('data.document_name'))->toBe('Test Document')
        ->and($response->json('data.document_path'))->toEndWith('/upload/hris/company-files/general/company-file-test')
        ->and($response->json('data.type_file'))->toBe('application/pdf');

    $this->assertDatabaseHas('files_companies', [
        'document_name' => 'Test Document',
        'document_path' => 'hris/company-files/general/company-file-test',
    ]);
});

test('authenticated user cannot create company file without required fields', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/files-companies', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document_path', 'document_name']);
});

test('authenticated user can view company file', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create(['document_name' => 'Test File']);

    $response = $this->getJson("/api/v1/files-companies/{$file->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'document_name', 'document_path', 'description']]);
    expect($response->json('data.id'))->toBe($file->id)
        ->and($response->json('data.document_name'))->toBe('Test File');
});

test('authenticated user gets 404 for non-existent company file', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/files-companies/99999')->assertStatus(404);
});

test('authenticated user can update company file with a new upload', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create(['document_name' => 'Old Name', 'document_path' => 'hris/company-files/old', 'type_file' => 'application/pdf']);
    $this->cloudinary->shouldReceive('delete')->once()->with('hris/company-files/old', 'raw');

    $response = $this->post("/api/v1/files-companies/{$file->id}", [
        '_method' => 'PUT',
        'document_name' => 'Updated Name',
        'description' => 'Updated description',
        'document_path' => UploadedFile::fake()->create('new.pdf', 80, 'application/pdf'),
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    expect($response->json('data.document_name'))->toBe('Updated Name');
    $this->assertDatabaseHas('files_companies', [
        'id' => $file->id,
        'document_name' => 'Updated Name',
        'document_path' => 'hris/company-files/general/company-file-test',
    ]);
});

test('authenticated user can partially update company file', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create(['document_name' => 'Original Name', 'document_path' => 'hris/company-files/original']);
    $this->cloudinary->shouldNotReceive('uploadAuto');

    $response = $this->putJson("/api/v1/files-companies/{$file->id}", ['document_name' => 'Partially Updated']);

    $response->assertStatus(200);
    expect($response->json('data.document_name'))->toBe('Partially Updated');
    expect($file->fresh()->document_path)->toBe('hris/company-files/original'); // unchanged
});

test('remove_file without a replacement is rejected before anything is deleted', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create(['document_path' => 'hris/company-files/keep', 'type_file' => 'image/png']);
    $this->cloudinary->shouldNotReceive('delete');

    $this->putJson("/api/v1/files-companies/{$file->id}", ['remove_file' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document_path']);

    expect($file->fresh()->document_path)->toBe('hris/company-files/keep');
});

test('remove_file with a replacement swaps the file and deletes the old one once', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create(['document_path' => 'hris/company-files/old-image', 'type_file' => 'image/png']);
    $this->cloudinary->shouldReceive('delete')->once()->with('hris/company-files/old-image', 'image');

    $this->post("/api/v1/files-companies/{$file->id}", [
        '_method' => 'PUT',
        'remove_file' => '1',
        'document_path' => UploadedFile::fake()->create('replacement.pdf', 50, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(200);

    expect($file->fresh()->document_path)->toBe('hris/company-files/general/company-file-test')
        ->and($file->fresh()->type_file)->toBe('application/pdf');
});

test('authenticated user can delete company file', function () {
    Sanctum::actingAs($this->user);
    $file = FilesCompany::factory()->create();

    $this->deleteJson("/api/v1/files-companies/{$file->id}")
        ->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Company File Deleted Successfully']);

    $this->assertSoftDeleted('files_companies', ['id' => $file->id]);
});

test('authenticated user gets 404 when deleting non-existent company file', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson('/api/v1/files-companies/99999')->assertStatus(404);
});
