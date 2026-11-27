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
        'items' => [
            ['type' => 'finding', 'feature' => 'Order', 'menu' => 'Daftar Order', 'description' => 'Tombol simpan tidak aktif'],
            ['type' => 'finding', 'description' => 'Laporan tidak muncul'],
            ['type' => 'note', 'feature' => 'Dashboard', 'description' => 'Klien minta warna grafik diganti'],
        ],
        'attachment' => UploadedFile::fake()->create('BA UAT 1.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.sessions.0.round', 1)
        ->assertJsonPath('data.sessions.0.findings_count', 2)
        ->assertJsonPath('data.sessions.0.notes_count', 1)
        ->assertJsonPath('data.sessions.0.items.0.feature', 'Order')
        ->assertJsonPath('data.sessions.0.items.0.menu', 'Daftar Order')
        ->assertJsonPath('data.sessions.0.items.1.feature', null)
        ->assertJsonPath('data.sessions.0.items.2.type', 'note')
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

test('findings and notes can be edited, cleared and are validated', function () {
    $session = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision']);
    $session->items()->create(['type' => 'finding', 'description' => 'Lama']);

    // Not sent: left as they are.
    $this->putJson("{$this->base}/sessions/{$session->id}", ['status' => 'passed'])
        ->assertOk()->assertJsonPath('data.sessions.0.findings_count', 1);

    // Replaced, in the order given.
    $this->putJson("{$this->base}/sessions/{$session->id}", ['items' => [
        ['type' => 'note', 'description' => 'Catatan A'],
        ['type' => 'finding', 'feature' => '  Laporan ', 'menu' => '', 'description' => ' Total salah '],
    ]])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.items.0.description', 'Catatan A')
        ->assertJsonPath('data.sessions.0.items.1.feature', 'Laporan')
        ->assertJsonPath('data.sessions.0.items.1.menu', null)
        ->assertJsonPath('data.sessions.0.items.1.description', 'Total salah');

    // A multipart form sends items_sent=1 and no items to clear them all.
    $this->post("{$this->base}/sessions/{$session->id}", ['_method' => 'PUT', 'items_sent' => '1'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(0, 'data.sessions.0.items');

    $this->putJson("{$this->base}/sessions/{$session->id}", ['items' => [['type' => 'bug', 'description' => 'x']]])->assertUnprocessable();
    $this->putJson("{$this->base}/sessions/{$session->id}", ['items' => [['type' => 'finding', 'description' => '']]])->assertUnprocessable();
});

test('the migration turns old one-per-line findings into items', function () {
    $migration = require database_path('migrations/2026_11_27_000001_create_project_uat_items_table.php');
    $migration->down();
    $sessionId = DB::table('project_uat_sessions')->insertGetId(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision', 'findings' => "Satu\n\n Dua \n", 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();

    expect(DB::table('project_uat_items')->where('project_uat_session_id', $sessionId)->orderBy('position')->pluck('description')->all())->toBe(['Satu', 'Dua'])
        ->and(DB::table('project_uat_items')->where('project_uat_session_id', $sessionId)->pluck('type')->unique()->all())->toBe(['finding']);
});

test('an item can be checked off and keeps that state when the round is edited', function () {
    $session = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision']);
    $item = $session->items()->create(['type' => 'finding', 'description' => 'Tombol simpan']);

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => true])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.items.0.is_resolved', true)
        ->assertJsonPath('data.sessions.0.items.0.resolved_by', $this->user->name)
        ->assertJsonPath('data.sessions.0.resolved_count', 1);

    // Editing the round with the item's id keeps the check-off; a new item is added.
    $this->putJson("{$this->base}/sessions/{$session->id}", ['items' => [
        ['id' => $item->id, 'type' => 'finding', 'description' => 'Tombol simpan (diperjelas)'],
        ['type' => 'note', 'description' => 'Baru'],
    ]])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.items.0.is_resolved', true)
        ->assertJsonPath('data.sessions.0.items.0.description', 'Tombol simpan (diperjelas)')
        ->assertJsonPath('data.sessions.0.items.1.is_resolved', false);

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => false])
        ->assertOk()->assertJsonPath('data.sessions.0.items.0.resolved_at', null);
});

test('a proof photo can be attached, replaced and removed, and is kept small', function () {
    $session = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision']);
    $item = $session->items()->create(['type' => 'note', 'description' => 'Perlu bukti']);
    $this->cloudinary->shouldReceive('uploadImage')->andReturn('hris/project-files/bukti-1', 'hris/project-files/bukti-2');

    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('bukti.jpg', 800, 600)->size(300)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.items.0.photo.name', 'bukti.jpg');

    $this->cloudinary->shouldReceive('delete')->once()->with('hris/project-files/bukti-1');
    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('bukti2.png')], ['Accept' => 'application/json'])->assertOk();

    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('besar.jpg')->size(3000)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.photo.0', 'Ukuran foto maksimal 2 MB.');
    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->deleteJson("{$this->base}/items/{$item->id}/photo")->assertOk()->assertJsonPath('data.sessions.0.items.0.photo', null);
});

test('items of another project are not found', function () {
    $other = Project::create(['name' => 'Lain', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $session = ProjectUatSession::create(['project_id' => $other->id, 'round' => 1, 'status' => 'revision']);
    $item = $session->items()->create(['type' => 'finding', 'description' => 'x']);

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => true])->assertNotFound();
    $this->deleteJson("{$this->base}/items/{$item->id}/photo")->assertNotFound();
});

test('one UAT can run in several places at the same time, and items say where they came from', function () {
    $response = $this->postJson("{$this->base}/sessions", [
        'date' => '2026-11-10',
        'status' => 'revision',
        'locations' => [
            ['location' => 'Kantor Klien – Jakarta', 'date' => '2026-11-10', 'time' => '09:00', 'participants' => 'Andi, Budi'],
            ['location' => 'Cabang Surabaya', 'date' => '2026-11-10', 'time' => '09:00'],
        ],
        'items' => [
            ['type' => 'finding', 'description' => 'Tombol simpan', 'location_index' => 0],
            ['type' => 'finding', 'description' => 'Laporan cabang', 'location_index' => 1],
            ['type' => 'note', 'description' => 'Umum'],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.sessions.0.round', 1)
        ->assertJsonCount(2, 'data.sessions.0.locations')
        ->assertJsonPath('data.sessions.0.locations.0.location', 'Kantor Klien – Jakarta')
        ->assertJsonPath('data.sessions.0.locations.0.time', '09:00')
        ->assertJsonPath('data.sessions.0.locations.1.items_count', 1)
        ->assertJsonPath('data.sessions.0.items.0.location', 'Kantor Klien – Jakarta')
        ->assertJsonPath('data.sessions.0.items.1.location', 'Cabang Surabaya')
        ->assertJsonPath('data.sessions.0.items.2.location', null);

    $session = ProjectUatSession::sole();
    [$jakarta, $surabaya] = $session->locations()->pluck('id')->all();

    // Editing only the round's fields keeps places and the items' places.
    $this->putJson("{$this->base}/sessions/{$session->id}", ['status' => 'passed'])
        ->assertJsonPath('data.sessions.0.items.1.location', 'Cabang Surabaya');

    // Removing a place keeps its items, without a place.
    $this->putJson("{$this->base}/sessions/{$session->id}", ['locations' => [['id' => $jakarta, 'location' => 'Kantor Klien – Jakarta']]])
        ->assertOk()
        ->assertJsonCount(1, 'data.sessions.0.locations')
        ->assertJsonCount(3, 'data.sessions.0.items')
        ->assertJsonPath('data.sessions.0.items.1.location', null)
        ->assertJsonPath('data.sessions.0.items.0.location', 'Kantor Klien – Jakarta');

    // Still one UAT round: the places don't count as extra rounds.
    expect(ProjectUatSession::count())->toBe(1);

    $this->putJson("{$this->base}/sessions/{$session->id}", ['locations' => [['location' => '']]])->assertUnprocessable();
    $this->putJson("{$this->base}/sessions/{$session->id}", ['locations' => [['location' => 'X', 'time' => '25:00']]])->assertUnprocessable();
});
