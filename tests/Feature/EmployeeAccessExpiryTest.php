<?php

use App\Models\EmployeeProfile;
use App\Models\JobInformation;
use App\Models\StaffRaportReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-20 08:00', 'Asia/Jakarta'));
    $this->employee = function (array $job = [], array $user = []) {
        $u = User::factory()->create(['password' => bcrypt('secret-pass-1')] + $user);
        $profile = EmployeeProfile::factory()->create(['user_id' => $u->id]);
        JobInformation::factory()->create(array_merge(['employee_id' => $profile->id, 'status' => 'active', 'start_date' => '2026-01-05', 'contract_end_date' => null, 'probation_end_date' => null], $job));

        return [$u, $profile];
    };
});

afterEach(fn () => Carbon::setTestNow());

test('a contract that ended yesterday switches the login off', function () {
    [$u] = ($this->employee)(['contract_end_date' => '2026-11-19']);
    $u->createToken('phone');

    $this->artisan('employees:expire-access')->assertSuccessful();

    $u->refresh();
    expect($u->is_active)->toBeFalse()
        ->and($u->access_ended_on->toDateString())->toBe('2026-11-19')
        ->and($u->access_end_reason)->toBe('contract')
        ->and($u->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/login', ['email' => $u->email, 'password' => 'secret-pass-1'])->assertStatus(403);
});

test('a probation that ended a few days ago does too', function () {
    [$u] = ($this->employee)(['probation_end_date' => '2026-11-17']);

    $this->artisan('employees:expire-access')->assertSuccessful();

    expect($u->fresh()->is_active)->toBeFalse()->and($u->fresh()->access_end_reason)->toBe('probation');
});

test('old end dates, today\'s end date, and former employees are left alone', function () {
    [$old] = ($this->employee)(['probation_end_date' => '2025-11-01']); // permanent since long ago
    [$today] = ($this->employee)(['contract_end_date' => '2026-11-20']); // last day is today
    [$gone] = ($this->employee)(['contract_end_date' => '2026-11-19', 'status' => 'resigned']);

    $this->artisan('employees:expire-access')->assertSuccessful();

    expect($old->fresh()->is_active)->toBeTrue()
        ->and($today->fresh()->is_active)->toBeTrue()
        ->and($gone->fresh()->is_active)->toBeTrue();
});

test('someone on leave also loses access when their contract ends', function () {
    [$u] = ($this->employee)(['contract_end_date' => '2026-11-18', 'status' => 'on_leave']);

    $this->artisan('employees:expire-access')->assertSuccessful();

    expect($u->fresh()->is_active)->toBeFalse();
});

test('a manual reactivation sticks: the same date is not processed again', function () {
    [$u] = ($this->employee)(['contract_end_date' => '2026-11-19']);
    $this->artisan('employees:expire-access');
    $u->forceFill(['is_active' => true])->save(); // HR switched it back on

    $this->artisan('employees:expire-access');

    expect($u->fresh()->is_active)->toBeTrue();
});

test('dry run changes nothing', function () {
    [$u] = ($this->employee)(['contract_end_date' => '2026-11-19']);

    $this->artisan('employees:expire-access --dry-run')->expectsOutputToContain('Would deactivate: 1')->assertSuccessful();

    expect($u->fresh()->is_active)->toBeTrue()->and($u->fresh()->access_ended_on)->toBeNull();
});

describe('staff report closing notes', function () {
    beforeEach(function () {
        foreach (['staff-raport-review', 'staff-raport-list', 'staff-raport-menu', 'staff-raport-export', 'employee-list'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'sanctum']);
        }
        foreach (['manager', 'operational_director', 'finance'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'sanctum'])->givePermissionTo(['staff-raport-review', 'staff-raport-list', 'staff-raport-export', 'employee-list']);
        }
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum'])->givePermissionTo(['staff-raport-list', 'staff-raport-export', 'employee-list']);
        [$this->leaver, $this->profile] = ($this->employee)(['contract_end_date' => '2026-11-19']);
        $this->artisan('employees:expire-access');
        $this->as = function (string $role) {
            $u = User::factory()->create(['name' => ucfirst($role).' Reviewer']);
            $u->assignRole($role);
            Sanctum::actingAs($u);

            return $u;
        };
    });

    test('Manager, Operational Director and Finance can write, change and remove their note', function () {
        foreach (['manager', 'operational_director', 'finance'] as $role) {
            ($this->as)($role);
            $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => "Catatan dari {$role}"])->assertOk();
        }
        expect(StaffRaportReview::where('employee_id', $this->profile->id)->count())->toBe(3);

        $finance = User::role('finance')->first();
        Sanctum::actingAs($finance);
        $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => 'Diubah'])->assertOk();
        $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => ''])->assertOk()->assertJsonCount(2, 'data');
    });

    test('roles without the permission cannot write a note', function () {
        ($this->as)('hr');
        $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => 'x'])->assertForbidden();
    });

    test('the tenure period ends on the day access ended and the notes are included', function () {
        ($this->as)('manager');
        $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => 'Kerja bagus selama kontrak.'])->assertOk();

        $this->getJson("/api/v1/reports/staff-raport/{$this->profile->id}?period=tenure")
            ->assertOk()
            ->assertJsonPath('data.period.start_date', '2026-01-05')
            ->assertJsonPath('data.period.end_date', '2026-11-19')
            ->assertJsonPath('data.employee.access_end_reason', 'contract')
            ->assertJsonPath('data.reviews.0.review', 'Kerja bagus selama kontrak.')
            ->assertJsonPath('data.reviews.0.reviewer_role', 'manager');

        $this->getJson("/api/v1/employees/{$this->profile->id}/raport-reviews")->assertOk()->assertJsonCount(1, 'data');
    });

    test('the PDF template prints the notes and the access end', function () {
        ($this->as)('manager');
        $this->putJson("/api/v1/reports/staff-raport/{$this->profile->id}/review", ['review' => 'Kerja bagus selama kontrak.'])->assertOk();
        $data = app(\App\Interfaces\ReportRepositoryInterface::class)->getStaffRaportDetail($this->profile->id, '2026-01-05', '2026-11-19');

        $html = view('pdf.staff-raport', $data + ['period_label' => 'Tenure', 'generated_at' => now(), 'use_signature' => false])->render();

        expect($html)->toContain('Catatan Penilai')->toContain('Kerja bagus selama kontrak.')->toContain('Manager Reviewer')->toContain('kontrak selesai');
    });
});
