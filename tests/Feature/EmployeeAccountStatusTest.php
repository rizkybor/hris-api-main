<?php

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Turning an employee's login off/on: gated by the employee-account-status
// permission (default: Superadmin, Manager, HR, Operational Director).
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'employee-account-status', 'guard_name' => 'sanctum']);
    foreach (['superadmin', 'manager', 'hr', 'operational_director', 'finance', 'staff'] as $name) {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        if (in_array($name, ['superadmin', 'manager', 'hr', 'operational_director'])) {
            $role->givePermissionTo('employee-account-status');
        }
    }
    $this->staffUser = User::factory()->create();
    $this->staffUser->assignRole('staff');
    $this->staff = EmployeeProfile::factory()->create(['user_id' => $this->staffUser->id]);
    $this->as = function (string $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        EmployeeProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $user;
    };
    $this->toggle = fn ($employee) => $this->postJson("/api/v1/employees/{$employee->id}/toggle-account-status");
});

test('HR, Manager and Operational Director can deactivate and reactivate a staff login', function (string $role) {
    ($this->as)($role);

    ($this->toggle)($this->staff)->assertOk();
    expect($this->staffUser->fresh()->is_active)->toBeFalse();

    ($this->toggle)($this->staff)->assertOk();
    expect($this->staffUser->fresh()->is_active)->toBeTrue();
})->with(['hr', 'manager', 'operational_director']);

test('roles without the permission cannot', function (string $role) {
    ($this->as)($role);

    ($this->toggle)($this->staff)->assertForbidden();
    expect($this->staffUser->fresh()->is_active)->toBeTrue();
})->with(['finance', 'staff']);

test('the permission can be granted to any role', function () {
    Role::findByName('finance', 'sanctum')->givePermissionTo('employee-account-status');
    ($this->as)('finance');

    ($this->toggle)($this->staff)->assertOk();
});

test('a deactivated account cannot log in and its sessions end', function () {
    ($this->as)('hr');
    $this->staffUser->update(['password' => bcrypt('secret-pass-1')]);
    $this->staffUser->createToken('phone');

    ($this->toggle)($this->staff)->assertOk();

    expect($this->staffUser->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/login', ['email' => $this->staffUser->email, 'password' => 'secret-pass-1'])->assertStatus(403);
});

test('nobody can deactivate their own account', function () {
    $hr = ($this->as)('hr');

    ($this->toggle)($hr->employeeProfile)->assertForbidden();
    expect($hr->fresh()->is_active)->toBeTrue();
});

test('HR cannot deactivate a Manager; Superadmin can', function () {
    $managerUser = User::factory()->create();
    $managerUser->assignRole('manager');
    $manager = EmployeeProfile::factory()->create(['user_id' => $managerUser->id]);

    ($this->as)('hr');
    ($this->toggle)($manager)->assertForbidden();

    ($this->as)('superadmin');
    ($this->toggle)($manager)->assertOk();
    expect($managerUser->fresh()->is_active)->toBeFalse();
});
