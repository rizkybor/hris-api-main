<?php

use App\Models\CompanyCashTransaction;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    foreach (['company-cash-book-list', 'company-cash-book-create', 'company-cash-book-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $role->givePermissionTo(['company-cash-book-list', 'company-cash-book-create', 'company-cash-book-edit']);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $n = 0;
    $this->cloudinary = $this->mock(CloudinaryManager::class, function ($mock) use (&$n) {
        $mock->shouldReceive('uploadAuto')->andReturnUsing(function () use (&$n) {
            return 'hris/company-files/cash-book/proof-'.(++$n);
        })->byDefault();
        $mock->shouldReceive('delete')->andReturnNull()->byDefault();
    });
    $this->base = ['type' => 'debit', 'description' => 'Beli ATK', 'amount' => 150000, 'transaction_date' => '2026-11-16'];
});

test('a transaction can be recorded with several proofs', function () {
    $response = $this->post('/api/v1/company-cash-transactions', $this->base + [
        'proofs' => [UploadedFile::fake()->image('nota-1.jpg'), UploadedFile::fake()->create('nota-2.pdf', 200, 'application/pdf')],
    ], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonCount(2, 'data.proofs');
    expect($response->json('data.proofs.0.name'))->toBe('nota-1.jpg')
        ->and($response->json('data.proofs.0.is_image'))->toBeTrue()
        ->and($response->json('data.proofs.1.is_image'))->toBeFalse()
        ->and($response->json('data.proof.name'))->toBe('nota-1.jpg');
});

test('a single proof from older clients still works', function () {
    $this->post('/api/v1/company-cash-transactions', $this->base + ['proof' => UploadedFile::fake()->image('nota.png')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(1, 'data.proofs');
});

test('proofs can be added and removed one by one when editing', function () {
    $tx = CompanyCashTransaction::create($this->base + ['created_by' => $this->user->id]);
    $a = $tx->proofs()->create(['file' => 'hris/old-a', 'name' => 'a.jpg', 'mime' => 'image/jpeg']);
    $b = $tx->proofs()->create(['file' => 'hris/old-b', 'name' => 'b.pdf', 'mime' => 'application/pdf']);

    $this->cloudinary->shouldReceive('delete')->once()->with('hris/old-b', 'raw');

    $this->post("/api/v1/company-cash-transactions/{$tx->id}", [
        '_method' => 'PUT',
        'remove_proof_ids' => [$b->id],
        'proofs' => [UploadedFile::fake()->image('c.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();

    expect($tx->fresh()->proofs->pluck('name')->all())->toBe(['a.jpg', 'c.jpg']);
});

test('another transaction\'s proof cannot be removed through this one', function () {
    $mine = CompanyCashTransaction::create($this->base + ['created_by' => $this->user->id]);
    $other = CompanyCashTransaction::create($this->base + ['created_by' => $this->user->id]);
    $foreign = $other->proofs()->create(['file' => 'hris/foreign', 'name' => 'x.jpg', 'mime' => 'image/jpeg']);

    $this->putJson("/api/v1/company-cash-transactions/{$mine->id}", ['remove_proof_ids' => [$foreign->id]])->assertOk();

    expect($other->fresh()->proofs)->toHaveCount(1);
});

test('more than 3 proofs in total are refused', function () {
    $tx = CompanyCashTransaction::create($this->base + ['created_by' => $this->user->id]);
    foreach (range(1, 2) as $i) {
        $tx->proofs()->create(['file' => "hris/p{$i}", 'name' => "{$i}.jpg", 'mime' => 'image/jpeg']);
    }

    $this->post("/api/v1/company-cash-transactions/{$tx->id}", [
        '_method' => 'PUT',
        'proofs' => [UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg')],
    ], ['Accept' => 'application/json'])->assertStatus(422);
    expect($tx->fresh()->proofs)->toHaveCount(2);

    // Removing one makes room for one more.
    $this->post("/api/v1/company-cash-transactions/{$tx->id}", [
        '_method' => 'PUT',
        'remove_proof_ids' => [$tx->proofs()->first()->id],
        'proofs' => [UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();
    expect($tx->fresh()->proofs)->toHaveCount(3);
});

test('a new transaction takes at most 3 proofs', function () {
    $this->post('/api/v1/company-cash-transactions', $this->base + [
        'proofs' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 4)),
    ], ['Accept' => 'application/json'])->assertStatus(422);
    expect(CompanyCashTransaction::count())->toBe(0);
});

test('the list loads proofs without a query per row', function () {
    foreach (range(1, 6) as $i) {
        $tx = CompanyCashTransaction::create($this->base + ['created_by' => $this->user->id]);
        $tx->proofs()->create(['file' => "hris/p{$i}", 'name' => "{$i}.jpg", 'mime' => 'image/jpeg']);
    }
    $this->getJson('/api/v1/company-cash-transactions')->assertOk();

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->getJson('/api/v1/company-cash-transactions')->assertOk();
    $proofQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'company_cash_transaction_proofs'));

    expect($proofQueries)->toHaveCount(1);
});
