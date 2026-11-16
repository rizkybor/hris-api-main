<?php

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
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
    // Refused either way: the address no longer leads to this account, and the account is inactive.
    $this->postJson('/api/v1/login', ['email' => 'leaving@example.test', 'password' => 'secret-pass-1'])->assertStatus(401);
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

test('the email is freed for a new account once the employee is deleted', function () {
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();

    $old = User::find($this->staffUser->id);
    expect($old->email)->toBe("leaving+deleted-{$old->id}@example.test")
        ->and($old->deleted_email)->toBe('leaving@example.test')
        ->and(Validator::make(['email' => 'leaving@example.test'], ['email' => 'unique:users,email'])->passes())->toBeTrue();

    // A new account can take the address (the DB unique index agrees too).
    expect(User::factory()->create(['email' => 'leaving@example.test'])->exists)->toBeTrue();
});

test('a deleted employee can be restored with their email, login and history', function () {
    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();

    $this->getJson('/api/v1/employees/deleted')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->staff->id)
        ->assertJsonPath('data.0.email', 'leaving@example.test')
        ->assertJsonPath('data.0.email_taken', false);

    $this->postJson("/api/v1/employees/{$this->staff->id}/restore")->assertOk();

    $user = $this->staffUser->fresh();
    expect($this->staff->fresh()->trashed())->toBeFalse()
        ->and($user->email)->toBe('leaving@example.test')
        ->and($user->deleted_email)->toBeNull()
        ->and($user->is_active)->toBeTrue();
    $this->postJson('/api/v1/login', ['email' => 'leaving@example.test', 'password' => 'secret-pass-1'])->assertOk();
});

test('restoring is refused when the email now belongs to another account', function () {
    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();
    User::factory()->create(['email' => 'leaving@example.test']); // re-hired as a new account

    $this->getJson('/api/v1/employees/deleted')->assertJsonPath('data.0.email_taken', true);
    $this->postJson("/api/v1/employees/{$this->staff->id}/restore")->assertStatus(422);

    expect(EmployeeProfile::find($this->staff->id))->toBeNull(); // still deleted
});

test('Manager can delete, list and restore a staff member', function () {
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo('employee-delete');
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    Sanctum::actingAs($manager);

    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();
    $this->getJson('/api/v1/employees/deleted')->assertOk()->assertJsonPath('data.0.id', $this->staff->id);
    $this->postJson("/api/v1/employees/{$this->staff->id}/restore")->assertOk();
});

test('Manager cannot delete another Manager, or their own profile', function () {
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo('employee-delete');
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $own = EmployeeProfile::factory()->create(['user_id' => $manager->id]);
    $otherUser = User::factory()->create();
    $otherUser->assignRole('manager');
    $other = EmployeeProfile::factory()->create(['user_id' => $otherUser->id]);
    Sanctum::actingAs($manager);

    $this->deleteJson("/api/v1/employees/{$other->id}")->assertForbidden();
    $this->deleteJson("/api/v1/employees/{$own->id}")->assertForbidden();

    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/employees/{$other->id}")->assertOk();
});

test('only Superadmin and Manager can list or restore deleted employees', function () {
    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/employees/{$this->staff->id}")->assertOk();

    $hr = User::factory()->create();
    $hr->assignRole(Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum'])->givePermissionTo('employee-delete'));
    Sanctum::actingAs($hr);

    $this->getJson('/api/v1/employees/deleted')->assertForbidden();
    $this->postJson("/api/v1/employees/{$this->staff->id}/restore")->assertForbidden();
});
