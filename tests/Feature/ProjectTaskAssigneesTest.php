<?php

use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    foreach (['task-list', 'task-create', 'task-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $role->givePermissionTo(['task-list', 'task-create', 'task-edit']);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    Sanctum::actingAs($this->manager);

    $this->project = Project::create(['name' => 'Multi Assignee', 'start_date' => '2026-01-01']);
    [$this->a, $this->b, $this->c] = [EmployeeProfile::factory()->create(), EmployeeProfile::factory()->create(), EmployeeProfile::factory()->create()];
    $this->base = ['project_id' => $this->project->id, 'name' => 'Shared task', 'priority' => 'medium', 'status' => 'todo'];
});

test('a task can be created with several assignees', function () {
    $this->postJson('/api/v1/project-tasks', $this->base + ['assignee_ids' => [$this->a->id, $this->b->id]])
        ->assertCreated()
        ->assertJsonPath('data.assignee_ids', [$this->a->id, $this->b->id])
        ->assertJsonPath('data.assignee_id', $this->a->id)
        ->assertJsonCount(2, 'data.assignees');
});

test('a lone assignee_id from older clients still works', function () {
    $this->postJson('/api/v1/project-tasks', $this->base + ['assignee_id' => $this->b->id])
        ->assertCreated()
        ->assertJsonPath('data.assignee_ids', [$this->b->id]);
});

test('assignees can be replaced and cleared', function () {
    $task = ProjectTask::create($this->base);

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['assignee_ids' => [$this->c->id, $this->a->id]])
        ->assertOk()
        ->assertJsonPath('data.assignee_ids', [$this->c->id, $this->a->id])
        ->assertJsonPath('data.assignee_id', $this->c->id);

    // A form sends "" for an empty list.
    $this->putJson("/api/v1/project-tasks/{$task->id}", ['assignee_ids' => ''])
        ->assertOk()
        ->assertJsonPath('data.assignee_ids', [])
        ->assertJsonPath('data.assignee_id', null);
    expect($task->fresh()->assignee_id)->toBeNull();
});

test('clearing a lone assignee_id really removes it', function () {
    $task = ProjectTask::create($this->base);
    $this->putJson("/api/v1/project-tasks/{$task->id}", ['assignee_id' => $this->a->id])->assertOk();

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['assignee_id' => ''])
        ->assertOk()
        ->assertJsonPath('data.assignee_id', null)
        ->assertJsonPath('data.assignee_ids', []);
});

test('updating other fields keeps the assignees', function () {
    $task = ProjectTask::create($this->base);
    $task->assignees()->sync([$this->a->id, $this->b->id]);

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.assignee_ids', [$this->a->id, $this->b->id]);
});

test('every assignee is found by assignedTo', function () {
    $task = ProjectTask::create($this->base);
    $task->assignees()->sync([$this->a->id, $this->b->id]);

    expect(ProjectTask::assignedTo($this->a->id)->pluck('id')->all())->toBe([$task->id])
        ->and(ProjectTask::assignedTo($this->b->id)->count())->toBe(1)
        ->and(ProjectTask::assignedTo($this->c->id)->count())->toBe(0)
        ->and($task->fresh()->isAssignedTo($this->b->id))->toBeTrue();
});

test('unknown or duplicate assignees are rejected', function () {
    $this->postJson('/api/v1/project-tasks', $this->base + ['assignee_ids' => [999999]])
        ->assertStatus(422)->assertJsonValidationErrors('assignee_ids.0');
    $this->postJson('/api/v1/project-tasks', $this->base + ['assignee_ids' => [$this->a->id, $this->a->id]])
        ->assertStatus(422);
});
