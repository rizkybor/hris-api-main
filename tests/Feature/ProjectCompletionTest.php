<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['project-list', 'project-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'pm', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-edit']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list']);
    $this->user = User::factory()->create();
    $this->user->assignRole('pm');
    Sanctum::actingAs($this->user);

    $this->project = Project::create(['name' => 'Aplikasi Order', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'budget' => 50000000]);
});

afterEach(fn () => Carbon::setTestNow());

test('a project can be marked completed and reactivated, other fields untouched', function () {
    Carbon::setTestNow('2026-11-26 10:00:00');

    $this->putJson("/api/v1/projects/{$this->project->id}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.name', 'Aplikasi Order');
    $project = $this->project->fresh();
    expect($project->completed_at->toDateTimeString())->toBe('2026-11-26 10:00:00')
        ->and((float) $project->budget)->toBe(50000000.0)
        ->and($project->end_date)->toStartWith('2026-12-31');

    $this->putJson("/api/v1/projects/{$this->project->id}", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.completed_at', null);
});

test('saving a completed project again keeps its completion date', function () {
    Carbon::setTestNow('2026-11-20 09:00:00');
    $this->project->update(['status' => 'completed']);
    Carbon::setTestNow('2026-11-26 09:00:00');

    $this->putJson("/api/v1/projects/{$this->project->id}", ['name' => 'Aplikasi Order v2'])->assertOk();

    expect($this->project->fresh()->completed_at->toDateString())->toBe('2026-11-20');
});

test('a viewer cannot change the status', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    $this->putJson("/api/v1/projects/{$this->project->id}", ['status' => 'completed'])->assertForbidden();
});
