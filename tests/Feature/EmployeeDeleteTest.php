<?php

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Deleting an employee removes their access too: open sessions end and the
// account can't log in; it stays (inactive) so history keeps their name.
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'employee-delete', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'sanctum'])->givePermissionTo('employee-delete');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('superadmin');

    $this->staffUser = User::factory()->create(['email' => 'leaving@example.test', 'password' => bcrypt('secret-pass-1')]);
    $this->staffUser->assignRole('staff');
    $this->staff = EmployeeProfile::factory()->create(['user_id' => $this->staffUser->id]);
});

test('a deleted employee can no longer log in and their sessions end', function () {
    $this->staffUser->createToken('phone');
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();

    expect($this->staffUser->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/login', ['email' => 'leaving@example.test', 'password' => 'secret-pass-1'])->assertStatus(403);
});

test('the profile is soft-deleted and the account kept (inactive) so history keeps the name', function () {
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();

    $user = User::find($this->staffUser->id);
    expect(EmployeeProfile::withTrashed()->find($this->staff->id)->trashed())->toBeTrue()
        ->and($user)->not->toBeNull()
        ->and($user->is_active)->toBeFalse()
        ->and($user->name)->toBe($this->staffUser->name);
});
