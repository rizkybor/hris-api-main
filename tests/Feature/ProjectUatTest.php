<?php

use App\Models\Project;
use App\Models\ProjectUatItem;
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
        'attachment' => UploadedFile::fake()->create('BA UAT 1.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.sessions.0.round', 1)
        // Every UAT starts with one (unnamed) session.
        ->assertJsonCount(1, 'data.sessions.0.locations')
        ->assertJsonPath('data.sessions.0.locations.0.location', null)
        ->assertJsonPath('data.sessions.0.locations.0.date', '2026-11-20')
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

// The first session of a new UAT (created through the API).
function newUat($test, array $data = []): array
{
    $uat = $test->postJson("{$test->base}/sessions", $data + ['date' => '2026-11-10', 'status' => 'revision'])->assertCreated()->json('data.sessions');

    return end($uat);
}

test('findings and notes are added straight to a session, edited, moved and deleted', function () {
    $uat = newUat($this, ['locations' => [['location' => 'Kantor Klien – Jakarta', 'time' => '09:00'], ['location' => 'Cabang Surabaya']]]);
    [$jakarta, $surabaya] = array_column($uat['locations'], 'id');

    $this->postJson("{$this->base}/locations/{$jakarta}/items", ['type' => 'finding', 'feature' => ' Order ', 'menu' => 'Daftar Order', 'description' => ' Tombol simpan '])
        ->assertCreated()
        ->assertJsonPath('data.sessions.0.locations.0.items.0.feature', 'Order')
        ->assertJsonPath('data.sessions.0.locations.0.items.0.description', 'Tombol simpan')
        ->assertJsonPath('data.sessions.0.locations.0.findings_count', 1)
        ->assertJsonPath('data.sessions.0.findings_count', 1);
    $this->postJson("{$this->base}/locations/{$surabaya}/items", ['type' => 'note', 'description' => 'Catatan cabang'])
        ->assertCreated()
        ->assertJsonPath('data.sessions.0.locations.1.items.0.type', 'note')
        ->assertJsonPath('data.sessions.0.notes_count', 1)
        ->assertJsonPath('data.sessions.0.items_count', 2);

    $item = ProjectUatItem::where('description', 'Tombol simpan')->sole();
    $this->putJson("{$this->base}/items/{$item->id}", ['type' => 'finding', 'menu' => '', 'description' => 'Tombol simpan (diperjelas)', 'location_id' => $surabaya])
        ->assertOk()
        ->assertJsonCount(0, 'data.sessions.0.locations.0.items')
        ->assertJsonPath('data.sessions.0.locations.1.items.1.description', 'Tombol simpan (diperjelas)')
        ->assertJsonPath('data.sessions.0.locations.1.items.1.menu', null);

    $this->deleteJson("{$this->base}/items/{$item->id}")->assertOk()->assertJsonPath('data.sessions.0.items_count', 1);

    $this->postJson("{$this->base}/locations/{$jakarta}/items", ['type' => 'bug', 'description' => 'x'])->assertUnprocessable();
    $this->postJson("{$this->base}/locations/{$jakarta}/items", ['type' => 'finding', 'description' => ''])->assertUnprocessable();
});

test('editing the UAT keeps its sessions\' findings; removing a session removes its findings', function () {
    $uat = newUat($this, ['locations' => [['location' => 'A'], ['location' => 'B']]]);
    [$a, $b] = array_column($uat['locations'], 'id');
    $this->postJson("{$this->base}/locations/{$a}/items", ['type' => 'finding', 'description' => 'Di A']);
    $this->postJson("{$this->base}/locations/{$b}/items", ['type' => 'finding', 'description' => 'Di B']);

    // Only the UAT's own fields: sessions and findings untouched.
    $this->putJson("{$this->base}/sessions/{$uat['id']}", ['status' => 'passed'])
        ->assertOk()->assertJsonPath('data.sessions.0.items_count', 2);

    // Renaming a session (sent with its id) keeps its findings; dropping B drops B's.
    $this->putJson("{$this->base}/sessions/{$uat['id']}", ['locations' => [['id' => $a, 'location' => 'A (pusat)']]])
        ->assertOk()
        ->assertJsonCount(1, 'data.sessions.0.locations')
        ->assertJsonPath('data.sessions.0.locations.0.location', 'A (pusat)')
        ->assertJsonPath('data.sessions.0.locations.0.items.0.description', 'Di A')
        ->assertJsonPath('data.sessions.0.items_count', 1);

    // An emptied list still leaves one session.
    $this->post("{$this->base}/sessions/{$uat['id']}", ['_method' => 'PUT', 'locations_sent' => '1'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(1, 'data.sessions.0.locations');

    $this->putJson("{$this->base}/sessions/{$uat['id']}", ['locations' => [['location' => 'X', 'time' => '25:00']]])->assertUnprocessable();
});

test('an item can be checked off and keeps that state when edited', function () {
    $uat = newUat($this);
    $location = $uat['locations'][0]['id'];
    $this->postJson("{$this->base}/locations/{$location}/items", ['type' => 'finding', 'description' => 'Tombol simpan']);
    $item = ProjectUatItem::sole();

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => true])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.locations.0.items.0.is_resolved', true)
        ->assertJsonPath('data.sessions.0.locations.0.items.0.resolved_by', $this->user->name)
        ->assertJsonPath('data.sessions.0.resolved_count', 1);

    $this->putJson("{$this->base}/items/{$item->id}", ['type' => 'finding', 'description' => 'Tombol simpan (diperjelas)'])
        ->assertJsonPath('data.sessions.0.locations.0.items.0.is_resolved', true);

    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => false])
        ->assertOk()->assertJsonPath('data.sessions.0.locations.0.items.0.resolved_at', null);
});

test('a proof photo can be attached, replaced and removed, and is kept small', function () {
    $uat = newUat($this);
    $this->postJson("{$this->base}/locations/{$uat['locations'][0]['id']}/items", ['type' => 'note', 'description' => 'Perlu bukti']);
    $item = ProjectUatItem::sole();
    $this->cloudinary->shouldReceive('uploadImage')->andReturn('hris/project-files/bukti-1', 'hris/project-files/bukti-2');

    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('bukti.jpg', 800, 600)->size(300)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.locations.0.items.0.photo.name', 'bukti.jpg');

    $this->cloudinary->shouldReceive('delete')->once()->with('hris/project-files/bukti-1');
    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('bukti2.png')], ['Accept' => 'application/json'])->assertOk();

    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('besar.jpg')->size(3000)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.photo.0', 'Ukuran foto maksimal 2 MB.');
    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->deleteJson("{$this->base}/items/{$item->id}/photo")->assertOk()->assertJsonPath('data.sessions.0.locations.0.items.0.photo', null);

    // Deleting the item removes its photo too.
    $this->post("{$this->base}/items/{$item->id}/photo", ['photo' => UploadedFile::fake()->image('lagi.jpg')], ['Accept' => 'application/json']);
    $this->cloudinary->shouldReceive('delete')->once()->with('hris/project-files/bukti-2');
    $this->deleteJson("{$this->base}/items/{$item->id}")->assertOk();
});

test('sessions and items of another project are not found; viewers cannot add', function () {
    $other = Project::create(['name' => 'Lain', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $otherUat = ProjectUatSession::create(['project_id' => $other->id, 'round' => 1, 'status' => 'revision']);
    $place = $otherUat->locations()->create(['position' => 0]);
    $item = $otherUat->items()->create(['type' => 'finding', 'description' => 'x', 'project_uat_location_id' => $place->id]);

    $this->postJson("{$this->base}/locations/{$place->id}/items", ['type' => 'finding', 'description' => 'y'])->assertNotFound();
    $this->putJson("{$this->base}/items/{$item->id}", ['type' => 'finding', 'description' => 'y'])->assertNotFound();
    $this->deleteJson("{$this->base}/items/{$item->id}")->assertNotFound();
    $this->patchJson("{$this->base}/items/{$item->id}/resolve", ['resolved' => true])->assertNotFound();

    $mine = newUat($this);
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->postJson("{$this->base}/locations/{$mine['locations'][0]['id']}/items", ['type' => 'finding', 'description' => 'y'])->assertForbidden();
});

test('the migration gives every UAT a session and moves loose items into it', function () {
    $uat = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision', 'date' => '2026-11-10']);
    $uat->items()->create(['type' => 'finding', 'description' => 'Lepas']);
    $withPlaces = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 2, 'status' => 'revision']);
    $first = $withPlaces->locations()->create(['position' => 0, 'location' => 'A']);
    $withPlaces->locations()->create(['position' => 1, 'location' => 'B']);
    $withPlaces->items()->create(['type' => 'note', 'description' => 'Tanpa tempat']);

    (require database_path('migrations/2026_11_28_000001_uat_items_belong_to_a_session.php'))->up();

    expect($uat->locations()->count())->toBe(1)
        ->and($uat->locations()->first()->held_on->toDateString())->toBe('2026-11-10')
        ->and($uat->items()->first()->project_uat_location_id)->toBe($uat->locations()->first()->id)
        ->and($withPlaces->locations()->count())->toBe(2)
        ->and($withPlaces->items()->first()->project_uat_location_id)->toBe($first->id);
});
