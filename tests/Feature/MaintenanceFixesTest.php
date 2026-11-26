<?php

use App\Models\Client;
use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00', 'Asia/Jakarta'));
    $permissions = ['maintenance-menu', 'maintenance-list', 'maintenance-create', 'maintenance-edit', 'maintenance-delete', 'maintenance-share', 'maintenance-adjust', 'maintenance-log'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo($permissions);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $client = Client::create(['name' => 'PT Contoh', 'pic_name' => 'Andi', 'pic_phone' => '0812', 'email' => 'andi@example.com']);
    $this->contract = MaintenanceContract::findOrFail($this->postJson('/api/v1/maintenance/contracts', [
        'client_id' => $client->id,
        'name' => 'Maintenance Aplikasi',
        'monthly_quota_hours' => 10,
        'start_date' => '2026-11-01',
        'status' => 'active',
        'leftover_policy' => 'bank',
    ])->assertCreated()->json('data.id'));
    $this->periodId = $this->getJson("/api/v1/maintenance/contracts/{$this->contract->id}")->json('data.current_period.id');
});

afterEach(fn () => Carbon::setTestNow());

test('a quota deduction cannot take the month below zero', function () {
    $this->postJson("/api/v1/maintenance/periods/{$this->periodId}/adjustments", ['hours' => -15, 'reason' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours']);

    $this->postJson("/api/v1/maintenance/periods/{$this->periodId}/adjustments", ['hours' => -10, 'reason' => 'Libur'])->assertCreated();
    $this->getJson("/api/v1/maintenance/periods/{$this->periodId}")
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.overage', 0);
});

test('removing a top-up cannot leave the month below zero', function () {
    $this->postJson("/api/v1/maintenance/periods/{$this->periodId}/adjustments", ['hours' => 5, 'reason' => 'Bonus'])->assertCreated();
    $this->postJson("/api/v1/maintenance/periods/{$this->periodId}/adjustments", ['hours' => -15, 'reason' => 'Potong'])->assertCreated();
    $topUp = collect($this->getJson("/api/v1/maintenance/periods/{$this->periodId}")->json('data.adjustments'))->firstWhere('hours', 5);

    $this->deleteJson("/api/v1/maintenance/adjustments/{$topUp['id']}")->assertUnprocessable();
});

test('the client page does not reveal hidden features or internal reasons', function () {
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/balance-adjustments", ['hours' => 20, 'reason' => 'Bonus internal dari direksi'])->assertSuccessful();
    $hidden = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/features", ['title' => 'Dashboard Rahasia', 'estimated_hours' => 5, 'visible_to_client' => false])->json('data.id');
    $this->postJson("/api/v1/maintenance/features/{$hidden}/approve")->assertSuccessful();
    $shown = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/features", ['title' => 'Ekspor Excel', 'estimated_hours' => 3, 'visible_to_client' => true])->json('data.id');
    $this->postJson("/api/v1/maintenance/features/{$shown}/approve")->assertSuccessful();

    $url = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/share")->json('data.share_url');
    $html = $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->getContent();

    expect($html)->not->toContain('Dashboard Rahasia')
        ->and($html)->not->toContain('Bonus internal dari direksi')
        ->and($html)->toContain('Pengembangan fitur')
        ->and($html)->toContain('Ekspor Excel');
});

test('records of a deleted contract are not found', function () {
    $log = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-10', 'title' => 'Perbaikan', 'hours' => 1])->assertCreated()->json('data.id');
    $feature = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/features", ['title' => 'F', 'estimated_hours' => 2])->json('data.id');
    $this->deleteJson("/api/v1/maintenance/contracts/{$this->contract->id}")->assertOk();

    $this->putJson("/api/v1/maintenance/logs/{$log}", ['work_date' => '2026-11-10', 'title' => 'X', 'hours' => 2])->assertNotFound();
    $this->deleteJson("/api/v1/maintenance/logs/{$log}")->assertNotFound();
    $this->getJson("/api/v1/maintenance/periods/{$this->periodId}")->assertNotFound();
    $this->postJson("/api/v1/maintenance/periods/{$this->periodId}/adjustments", ['hours' => 1, 'reason' => 'x'])->assertNotFound();
    $this->postJson("/api/v1/maintenance/features/{$feature}/approve")->assertNotFound();
});

test('the hours-by-category recap counts quota work only', function () {
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/balance-adjustments", ['hours' => 20, 'reason' => 'x'])->assertSuccessful();
    $feature = $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/features", ['title' => 'F', 'estimated_hours' => 10])->json('data.id');
    $this->postJson("/api/v1/maintenance/features/{$feature}/approve")->assertSuccessful();
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-10', 'title' => 'Bug', 'hours' => 2, 'category' => 'bug_fix'])->assertCreated();
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-11', 'title' => 'Fitur', 'hours' => 6, 'maintenance_feature_id' => $feature])->assertCreated();

    $summary = $this->getJson('/api/v1/maintenance/summary')->assertOk()->json('data');
    $byCategory = collect($summary['categories'] ?? $summary['by_category'] ?? [])->sum('hours');

    expect((float) $summary['used'])->toBe(2.0)
        ->and((float) $byCategory)->toBe(2.0);
});

test('no hours can be logged after the contract has ended', function () {
    $this->putJson("/api/v1/maintenance/contracts/{$this->contract->id}", [
        'client_id' => $this->contract->client_id, 'name' => 'Maintenance Aplikasi', 'monthly_quota_hours' => 10,
        'start_date' => '2026-11-01', 'end_date' => '2026-11-10', 'status' => 'active', 'leftover_policy' => 'bank',
    ])->assertOk();

    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-10', 'title' => 'Hari terakhir', 'hours' => 1])->assertCreated();
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-12', 'title' => 'Setelah berakhir', 'hours' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_date']);

    $this->contract->update(['status' => 'ended', 'end_date' => null]);
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['work_date' => '2026-11-05', 'title' => 'X', 'hours' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('errors.work_date.0', 'Kontrak ini sudah berakhir, jam tidak bisa dicatat lagi.');
});

test('built-in validation messages are Indonesian too', function () {
    $this->postJson("/api/v1/maintenance/contracts/{$this->contract->id}/logs", ['hours' => 30])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'Judul wajib diisi.')
        ->assertJsonPath('errors.work_date.0', 'Tanggal pekerjaan wajib diisi.')
        ->assertJsonPath('errors.hours.0', 'Jumlah jam maksimal 24.');
});

test('the maintenance report needs maintenance access, not only report access', function () {
    foreach (['report-view', 'report-export'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum'])->syncPermissions(['report-view', 'report-export']);
    $hr = User::factory()->create();
    $hr->assignRole('hr');
    Sanctum::actingAs($hr);

    $this->getJson('/api/v1/reports/maintenance')->assertForbidden();
    $this->getJson('/api/v1/reports/export?type=maintenance')->assertForbidden();

    $this->user->givePermissionTo(['report-view', 'report-export']);
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/reports/maintenance')->assertOk();
});
