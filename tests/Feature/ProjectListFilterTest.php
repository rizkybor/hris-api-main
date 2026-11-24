<?php

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 09:00'));
    Permission::firstOrCreate(['name' => 'project-list', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo('project-list');
    $user = User::factory()->create();
    $user->assignRole('manager');
    Sanctum::actingAs($user);

    foreach ([
        ['Active late', 'active', '2026-11-01'],
        ['Active on time', 'active', '2026-12-31'],
        ['Planning late', 'planning', '2026-11-01'],
        ['On hold late', 'on_hold', '2026-11-01'],
        ['Draft late', 'draft', '2026-11-01'],
        ['Done late', 'completed', '2026-11-01'],
    ] as [$name, $status, $end]) {
        Project::create(['name' => $name, 'status' => $status, 'start_date' => '2026-01-01', 'end_date' => $end]);
    }
});

afterEach(fn () => Carbon::setTestNow());

$names = fn ($response) => collect($response->json('data.data'))->pluck('name')->sort()->values()->all();

test('overdue lists only active projects past their end date', function () use ($names) {
    $response = $this->getJson('/api/v1/projects/all/paginated?row_per_page=50&status=overdue')->assertOk();
    expect($names($response))->toBe(['Active late']);
});

test('each project status can be filtered, including planning, on hold and draft', function () use ($names) {
    foreach (['planning' => ['Planning late'], 'on_hold' => ['On hold late'], 'draft' => ['Draft late'], 'completed' => ['Done late']] as $status => $expected) {
        expect($names($this->getJson("/api/v1/projects/all/paginated?row_per_page=50&status={$status}")->assertOk()))->toBe($expected);
    }
    $this->getJson('/api/v1/projects/all/paginated?row_per_page=50&status=on-hold')->assertStatus(422);
});
