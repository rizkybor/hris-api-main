<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00', 'Asia/Jakarta'));

    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    foreach (['task-list', 'task-create', 'task-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $role->givePermissionTo(['task-list', 'task-create', 'task-edit']);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');

    $this->project = Project::create([
        'name' => 'Due Date Project',
        'start_date' => '2026-01-01',
    ]);

    $this->payload = fn (array $overrides = []) => array_merge([
        'project_id' => $this->project->id,
        'name' => 'Backdated task',
        'priority' => 'medium',
        'status' => 'todo',
    ], $overrides);
});

afterEach(fn () => Carbon::setTestNow());

test('a task can be created with a due date in the past', function () {
    Sanctum::actingAs($this->manager);

    $this->postJson('/api/v1/project-tasks', ($this->payload)(['due_date' => '2026-11-11']))
        ->assertCreated()
        ->assertJsonPath('data.due_date', '2026-11-11');

    expect(ProjectTask::first()->due_date->format('Y-m-d'))->toBe('2026-11-11');
});

test('creating a task still rejects an invalid due date', function () {
    Sanctum::actingAs($this->manager);

    $this->postJson('/api/v1/project-tasks', ($this->payload)(['due_date' => 'not-a-date']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    expect(ProjectTask::count())->toBe(0);
});

test('an overdue task can be updated and moved to another past due date', function () {
    Sanctum::actingAs($this->manager);

    $task = ProjectTask::create(($this->payload)(['due_date' => '2026-10-01']));

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['name' => 'Renamed', 'due_date' => '2026-11-01'])
        ->assertOk()
        ->assertJsonPath('data.due_date', '2026-11-01');
});

test('updating a task still rejects an invalid due date', function () {
    Sanctum::actingAs($this->manager);

    $task = ProjectTask::create(($this->payload)(['due_date' => '2026-10-01']));

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['due_date' => 'not-a-date'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');
});
