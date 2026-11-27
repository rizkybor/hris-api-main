<?php

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUatItem;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Pdf\ProjectProgressReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['project-list', 'project-edit', 'project-delete', 'task-list', 'task-edit', 'task-delete'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'pm', 'guard_name' => 'sanctum'])->givePermissionTo($permissions);
    $this->user = User::factory()->create();
    $this->user->assignRole('pm');
    Sanctum::actingAs($this->user);
    $this->mock(CloudinaryManager::class, fn ($mock) => $mock->shouldReceive('delete')->andReturnNull()->byDefault());

    $this->project = Project::create(['name' => 'Aplikasi Order', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $this->base = "/api/v1/projects/{$this->project->id}/uat";
    $uat = $this->postJson("{$this->base}/sessions", ['date' => '2026-11-20', 'locations' => [['location' => 'Jakarta']]])->json('data.sessions.0');
    $this->locationId = $uat['locations'][0]['id'];
    $this->sessionId = $uat['id'];
});

function addUatItem($test, array $data = []): ProjectUatItem
{
    $test->postJson("{$test->base}/locations/{$test->locationId}/items", $data + ['type' => 'finding', 'feature' => 'Order', 'menu' => 'Daftar', 'description' => 'Tombol simpan tidak aktif'])->assertCreated();

    return ProjectUatItem::latest('id')->first();
}

test('a finding or note becomes a To Do task on the board and follows its edits', function () {
    $item = addUatItem($this);
    $task = ProjectTask::where('project_uat_item_id', $item->id)->sole();
    expect($task->project_id)->toBe($this->project->id)
        ->and($task->status)->toBe('todo')
        ->and($task->name)->toBe('[UAT 1] Order › Daftar — Tombol simpan tidak aktif')
        ->and($task->type)->toBe('Temuan UAT')
        ->and($task->priority)->toBe('high')
        ->and($task->description)->toContain('Sesi 1 (Jakarta)');

    $this->putJson("{$this->base}/items/{$item->id}", ['type' => 'note', 'description' => 'Ganti label'])->assertOk();
    expect($task->fresh())->name->toBe('[UAT 1] Ganti label')->type->toBe('Catatan UAT');

    // Board lists it with its link to UAT.
    $this->getJson("/api/v1/project-tasks?project_id={$this->project->id}")->assertOk()
        ->assertJsonPath('data.0.uat_item_id', $item->id);

    $this->deleteJson("{$this->base}/items/{$item->id}")->assertOk();
    expect(ProjectTask::withTrashed()->where('project_uat_item_id', $item->id)->exists())->toBeFalse();
});

test('checking an item off as revised moves its task to Done, and back', function () {
    $item = addUatItem($this);
    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => true])->assertOk();
    expect($item->task->fresh()->status)->toBe('done');

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => false])->assertOk();
    expect($item->task->fresh()->status)->toBe('todo');
});

test('on the board a UAT task only moves between To Do and Done, which checks the item off', function () {
    $item = addUatItem($this);
    $task = $item->task;

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['status' => 'in_progress'])->assertStatus(422);
    $this->putJson("/api/v1/project-tasks/{$task->id}", ['status' => 'done', 'position' => 500])->assertOk()
        ->assertJsonPath('data.status', 'done');
    expect($item->fresh())->is_resolved->toBeTrue()->resolved_by->toBe($this->user->id);

    $this->putJson("/api/v1/project-tasks/{$task->id}", ['status' => 'todo', 'name' => 'Diubah'])->assertOk();
    expect($item->fresh()->is_resolved)->toBeFalse()
        ->and($task->fresh()->name)->toStartWith('[UAT 1]');

    $this->deleteJson("/api/v1/project-tasks/{$task->id}")->assertStatus(422);
    expect($task->fresh())->not->toBeNull();
});

test('UAT tasks are kept out of the development progress', function () {
    ProjectTask::create(['project_id' => $this->project->id, 'name' => 'Dev A', 'status' => 'done', 'priority' => 'medium']);
    ProjectTask::create(['project_id' => $this->project->id, 'name' => 'Dev B', 'status' => 'todo', 'priority' => 'medium']);
    addUatItem($this);
    addUatItem($this, ['type' => 'note', 'description' => 'Catatan']);

    $project = $this->project->fresh()->load('tasks');
    expect($project->tasks)->toHaveCount(2)
        ->and($project->uatTasks)->toHaveCount(2)
        ->and((new ProjectResource($project))->resolve(request())['progress'])->toEqual(50)
        ->and(app(ProjectProgressReportService::class)->summary($project)['totalTasks'])->toBe(2);
});

test('deleting a round removes its tasks and renames the later rounds\' tasks', function () {
    addUatItem($this);
    $second = $this->postJson("{$this->base}/sessions", ['date' => '2026-11-27'])->json('data.sessions.1');
    $this->postJson("{$this->base}/locations/{$second['locations'][0]['id']}/items", ['type' => 'finding', 'description' => 'Laporan kosong'])->assertCreated();

    $this->deleteJson("{$this->base}/sessions/{$this->sessionId}")->assertOk();
    $tasks = ProjectTask::withTrashed()->whereNotNull('project_uat_item_id')->get();
    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->name)->toBe('[UAT 1] Laporan kosong');
});
