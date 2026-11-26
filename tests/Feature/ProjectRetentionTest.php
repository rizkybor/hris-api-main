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
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-edit']);
    $user = User::factory()->create();
    $user->assignRole('manager');
    Sanctum::actingAs($user);
    Carbon::setTestNow('2026-11-26 10:00:00');

    $this->project = Project::create(['name' => 'Aplikasi Order', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);
});

afterEach(fn () => Carbon::setTestNow());

test('retention dates are saved and the month count follows them', function () {
    $this->putJson("/api/v1/projects/{$this->project->id}", ['retention_start_date' => '2026-07-01', 'retention_end_date' => '2026-12-31'])
        ->assertOk()
        ->assertJsonPath('data.retention_start_date', '2026-07-01')
        ->assertJsonPath('data.retention_end_date', '2026-12-31')
        ->assertJsonPath('data.retention_period_months', 6);

    $this->putJson("/api/v1/projects/{$this->project->id}", ['retention_start_date' => null, 'retention_end_date' => null])
        ->assertOk()
        ->assertJsonPath('data.retention_start_date', null)
        ->assertJsonPath('data.retention_period_months', null);
});

test('retention needs both dates in order', function () {
    $this->putJson("/api/v1/projects/{$this->project->id}", ['retention_start_date' => '2026-07-01'])->assertUnprocessable();
    $this->putJson("/api/v1/projects/{$this->project->id}", ['retention_start_date' => '2026-07-01', 'retention_end_date' => '2026-06-01'])->assertUnprocessable();
});

test('a project in its retention phase is not overdue', function () {
    $overdue = fn () => collect($this->getJson('/api/v1/projects/all/paginated?row_per_page=50&status=overdue')->json('data.data'))->pluck('name')->all();

    // Development ended 30 Jun, no retention: overdue.
    expect($overdue())->toBe(['Aplikasi Order']);

    // Retention running (Jul - Dec): not overdue.
    $this->project->update(['retention_start_date' => '2026-07-01', 'retention_end_date' => '2026-12-31']);
    expect($overdue())->toBe([]);

    // Retention planned but not started yet: still overdue.
    $this->project->update(['retention_start_date' => '2026-12-01', 'retention_end_date' => '2027-05-31']);
    expect($overdue())->toBe(['Aplikasi Order']);
});

test('the migration backfills retention dates from the month count', function () {
    $project = Project::create(['name' => 'Lama', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
    DB::table('projects')->where('id', $project->id)->update(['retention_period_months' => 3, 'retention_start_date' => null, 'retention_end_date' => null]);

    $migration = require database_path('migrations/2026_11_26_000003_add_retention_dates_to_projects_table.php');
    $migration->down();
    $migration->up();

    $fresh = $project->fresh();
    expect($fresh->retention_start_date->toDateString())->toBe('2026-04-01')
        ->and($fresh->retention_end_date->toDateString())->toBe('2026-06-30');
});
