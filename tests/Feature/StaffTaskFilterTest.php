<?php

use App\Models\EmployeeProfile;
use App\Models\StaffTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00', 'Asia/Jakarta')); // a Wednesday

    $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'staff-task-menu', 'guard_name' => 'sanctum']);
    $role->givePermissionTo('staff-task-menu');

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->employee = EmployeeProfile::factory()->create(['user_id' => User::factory()->create()->id]);

    $make = function (string $title, string $due, array $statuses) {
        $task = StaffTask::create([
            'title' => $title,
            'due_date' => $due,
            'assignment_mode' => 'selected',
            'created_by' => $this->manager->id,
        ]);
        foreach ($statuses as $status) {
            $task->assignees()->create([
                'employee_id' => EmployeeProfile::factory()->create(['user_id' => User::factory()->create()->id])->id,
                'status' => $status,
            ]);
        }

        return $task;
    };

    $make('Overdue open', '2026-11-09', ['todo', 'done']);
    $make('Overdue but done', '2026-11-09', ['done', 'done']);
    $make('Due friday', '2026-11-13', ['in_progress']);
    $make('Due next month', '2026-12-10', ['todo']);
    $make('No assignees', '2026-12-20', []);
});

afterEach(fn () => Carbon::setTestNow());

$titles = fn ($response) => collect($response->json('data.data'))->pluck('title')->sort()->values()->all();

test('filter=overdue lists open tasks past their due date only', function () use ($titles) {
    Sanctum::actingAs($this->manager);
    $response = $this->getJson('/api/v1/staff-tasks?filter=overdue')->assertOk();
    expect($titles($response))->toBe(['Overdue open']);
});

test('filter=due_this_week lists open tasks due from today to sunday', function () use ($titles) {
    Sanctum::actingAs($this->manager);
    $response = $this->getJson('/api/v1/staff-tasks?filter=due_this_week')->assertOk();
    expect($titles($response))->toBe(['Due friday']);
});

test('filter=done needs every assignee done; open is the rest', function () use ($titles) {
    Sanctum::actingAs($this->manager);
    expect($titles($this->getJson('/api/v1/staff-tasks?filter=done')->assertOk()))->toBe(['Overdue but done']);
    expect($titles($this->getJson('/api/v1/staff-tasks?filter=open')->assertOk()))
        ->toBe(['Due friday', 'Due next month', 'No assignees', 'Overdue open']);
});

test('the filtered counts match the summary tiles', function () {
    Sanctum::actingAs($this->manager);
    $summary = $this->getJson('/api/v1/staff-tasks/summary')->assertOk()->json('data');
    foreach (['overdue', 'due_this_week', 'open', 'done'] as $filter) {
        expect($this->getJson("/api/v1/staff-tasks?filter={$filter}")->json('data.meta.total'))->toBe($summary[$filter]);
    }
});

test('an unknown filter is ignored', function () {
    Sanctum::actingAs($this->manager);
    expect($this->getJson('/api/v1/staff-tasks?filter=bogus')->assertOk()->json('data.meta.total'))->toBe(5);
});
