<?php

use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// The task list must not run one query per task (N+1) now that every task
// carries a list of assignees and a per-viewer can_comment flag.
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    foreach (['task-list', 'task-menu'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $role->givePermissionTo(['task-list', 'task-menu']);

    $this->user = User::factory()->create();
    $this->user->assignRole('staff');
    $this->me = EmployeeProfile::factory()->create(['user_id' => $this->user->id]);
    $this->other = EmployeeProfile::factory()->create();
    Sanctum::actingAs($this->user);
});

$seed = function (Project $project, int $count) {
    for ($i = 0; $i < $count; $i++) {
        $task = ProjectTask::create(['project_id' => $project->id, 'name' => "Task {$i}", 'priority' => 'medium', 'status' => 'todo']);
        $task->assignees()->sync([$this->me->id, $this->other->id]);
    }
};

$queries = function (string $url) {
    $this->getJson($url)->assertOk(); // warm-up: permissions and the viewer's profile load once
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson($url)->assertOk();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
};

test('the project task list runs the same number of queries for 5 or 25 tasks', function () use ($seed, $queries) {
    $small = Project::create(['name' => 'Small', 'start_date' => '2026-01-01']);
    $big = Project::create(['name' => 'Big', 'start_date' => '2026-01-01']);
    $seed->call($this, $small, 5);
    $seed->call($this, $big, 25);

    $q5 = $queries->call($this, "/api/v1/project-tasks?project_id={$small->id}");
    $q25 = $queries->call($this, "/api/v1/project-tasks?project_id={$big->id}");

    expect($q25)->toBe($q5);
});

test('my tasks runs the same number of queries for 5 or 25 tasks', function () use ($seed, $queries) {
    $project = Project::create(['name' => 'Mine', 'start_date' => '2026-01-01']);
    $seed->call($this, $project, 5);
    $q5 = $queries->call($this, '/api/v1/my-tasks');
    $seed->call($this, $project, 20);
    $q25 = $queries->call($this, '/api/v1/my-tasks');

    expect($q25)->toBe($q5);
});
