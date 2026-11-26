<?php

use App\Models\Project;
use App\Models\ProjectUatSession;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['project-list', 'project-edit', 'project-delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'pm', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-edit', 'project-delete']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list']);

    $this->user = User::factory()->create();
    $this->user->assignRole('pm');
    Sanctum::actingAs($this->user);

    $n = 0;
    $this->cloudinary = $this->mock(CloudinaryManager::class, function ($mock) use (&$n) {
        $mock->shouldReceive('uploadRaw')->andReturnUsing(function () use (&$n) {
            return 'hris/project-files/uat-'.(++$n);
        })->byDefault();
        $mock->shouldReceive('delete')->andReturnNull()->byDefault();
    });

    $this->project = Project::create(['name' => 'Aplikasi Order', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $this->base = "/api/v1/projects/{$this->project->id}/uat";
});

test('the agreed number of UAT rounds can be set and cleared', function () {
    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => 3])
        ->assertOk()
        ->assertJsonPath('data.planned', 3)
        ->assertJsonPath('data.remaining', 3);
    expect($this->project->fresh()->uat_rounds_planned)->toBe(3);

    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => null])->assertOk()->assertJsonPath('data.planned', null);
    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => 0])->assertUnprocessable();
    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => 21])->assertUnprocessable();
});

test('rounds are numbered in order and count towards the agreement', function () {
    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => 2]);

    $this->post("{$this->base}/sessions", [
        'date' => '2026-11-20',
        'status' => 'revision',
        'notes' => 'Klien minta revisi',
        'findings' => "Tombol simpan tidak aktif\n\nLaporan tidak muncul\n",
        'attachment' => UploadedFile::fake()->create('BA UAT 1.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.sessions.0.round', 1)
        ->assertJsonPath('data.sessions.0.findings_count', 2)
        ->assertJsonPath('data.sessions.0.attachment.name', 'BA UAT 1.pdf')
        ->assertJsonPath('data.sessions.0.attachment.type', 'pdf')
        ->assertJsonPath('data.done', 1)
        ->assertJsonPath('data.remaining', 1);

    $this->postJson("{$this->base}/sessions", ['date' => '2026-11-27'])
        ->assertCreated()
        ->assertJsonPath('data.sessions.1.round', 2)
        ->assertJsonPath('data.sessions.1.status', 'scheduled')
        ->assertJsonPath('data.done', 1);

    // A third round is past the agreement but can still be recorded.
    $this->postJson("{$this->base}/sessions", ['status' => 'passed'])
        ->assertCreated()
        ->assertJsonPath('data.over_plan', true)
        ->assertJsonPath('data.sessions.2.over_plan', true)
        ->assertJsonPath('data.sessions.1.over_plan', false)
        ->assertJsonPath('data.passed_round', 3)
        ->assertJsonPath('data.remaining', 0);

    // The project detail carries the summary for the tab badge.
    $this->getJson("/api/v1/projects/{$this->project->id}")
        ->assertJsonPath('data.uat_rounds_planned', 2)
        ->assertJsonPath('data.uat_rounds_done', 2);
});

test('a round can be updated, its attachment replaced and removed', function () {
    $session = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'scheduled', 'attachment_path' => 'hris/old', 'attachment_name' => 'old.pdf']);

    $this->cloudinary->shouldReceive('delete')->once()->with('hris/old', 'raw');
    $this->post("{$this->base}/sessions/{$session->id}", [
        '_method' => 'PUT',
        'status' => 'passed',
        'attachment' => UploadedFile::fake()->create('BA UAT final.pdf', 50, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.status', 'passed')
        ->assertJsonPath('data.sessions.0.attachment.name', 'BA UAT final.pdf');

    $this->putJson("{$this->base}/sessions/{$session->id}", ['remove_attachment' => true])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.attachment', null)
        ->assertJsonPath('data.sessions.0.status', 'passed');

    $this->putJson("{$this->base}/sessions/{$session->id}", ['status' => 'done'])->assertUnprocessable();
});

test('deleting a round renumbers the later ones', function () {
    foreach ([1, 2, 3] as $round) {
        ProjectUatSession::create(['project_id' => $this->project->id, 'round' => $round, 'status' => 'revision', 'notes' => "R{$round}"]);
    }
    $second = ProjectUatSession::where('round', 2)->first();

    $this->deleteJson("{$this->base}/sessions/{$second->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.sessions')
        ->assertJsonPath('data.sessions.0.notes', 'R1')
        ->assertJsonPath('data.sessions.1.notes', 'R3')
        ->assertJsonPath('data.sessions.1.round', 2);
});

test('a round of another project is not found', function () {
    $other = Project::create(['name' => 'Lain', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $session = ProjectUatSession::create(['project_id' => $other->id, 'round' => 1, 'status' => 'scheduled']);

    $this->putJson("{$this->base}/sessions/{$session->id}", ['status' => 'passed'])->assertNotFound();
    $this->deleteJson("{$this->base}/sessions/{$session->id}")->assertNotFound();
    $this->getJson('/api/v1/projects/999999/uat')->assertNotFound();
});

test('a viewer can read the UAT but not change it', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    $this->getJson($this->base)->assertOk()->assertJsonPath('data.planned', null);
    $this->putJson("{$this->base}/plan", ['uat_rounds_planned' => 2])->assertForbidden();
    $this->postJson("{$this->base}/sessions", [])->assertForbidden();
});
