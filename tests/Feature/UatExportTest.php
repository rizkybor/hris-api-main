<?php

use App\Models\Client;
use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\Project;
use App\Models\ProjectUatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function uatPdfText(string $bytes): string
{
    // dompdf writes text uncompressed enough for a smoke check of key words
    $path = tempnam(sys_get_temp_dir(), 'uat-pdf-');
    file_put_contents($path, $bytes);
    $text = (string) shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null');
    @unlink($path);

    return mb_strtolower($text);
}

/** How many JPEG photos the PDF really embeds (a broken image embeds none; the logo is a PNG). */
function uatPdfImages(string $bytes): int
{
    $path = tempnam(sys_get_temp_dir(), 'uat-pdf-');
    file_put_contents($path, $bytes);
    $list = (string) shell_exec('pdfimages -list '.escapeshellarg($path).' 2>/dev/null');
    @unlink($path);

    return preg_match_all('/\s+image\s+.*\sjpeg\s/', $list);
}

beforeEach(function () {
    foreach (['project-list', 'project-export', 'letter-create', 'letter-list'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-export', 'letter-create', 'letter-list']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list']);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $client = Client::create(['name' => 'PT Contoh', 'pic_name' => 'Andi', 'pic_phone' => '0812', 'email' => 'a@example.com']);
    $this->project = Project::create(['name' => 'Aplikasi Order', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-12-31', 'client_id' => $client->id, 'uat_rounds_planned' => 2]);
    $this->uat = ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision', 'date' => '2026-11-10', 'notes' => 'Perlu revisi kecil']);
    $jakarta = $this->uat->locations()->create(['position' => 0, 'location' => 'Kantor Jakarta', 'start_time' => '09:00']);
    $surabaya = $this->uat->locations()->create(['position' => 1, 'location' => 'Cabang Surabaya']);
    $this->uat->items()->create(['project_uat_location_id' => $jakarta->id, 'type' => 'finding', 'feature' => 'Order', 'description' => 'Tombol simpan', 'is_resolved' => true, 'resolved_at' => now(), 'photo_path' => 'hris/bukti-1', 'evidence_path' => 'hris/temuan-1']);
    $this->uat->items()->create(['project_uat_location_id' => $surabaya->id, 'type' => 'note', 'description' => 'Warna grafik']);
    $this->division = DivisionCode::firstOrCreate(['code' => 'PRJ'], ['name' => 'Project']);
    $this->url = "/api/v1/projects/{$this->project->id}/uat/sessions/{$this->uat->id}";

    // Proof photos are fetched small from Cloudinary.
    Http::fake(['*' => Http::response(base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='), 200, ['Content-Type' => 'image/jpeg'])]);
});

test('the export dialog gets a drafted cover letter for each kind', function () {
    $result = $this->getJson("{$this->url}/export-options?kind=result")->assertOk();
    expect($result->json('data.draft.subject'))->toBe('Laporan Hasil UAT ke-1 – Aplikasi Order')
        ->and($result->json('data.draft.body'))->toContain('Kantor Jakarta, Cabang Surabaya')
        ->and($result->json('data.draft.recipient'))->toContain('PT Contoh');

    $revision = $this->getJson("{$this->url}/export-options?kind=revision")->assertOk();
    expect($revision->json('data.draft.subject'))->toBe('Laporan Hasil Revisi UAT ke-1 – Aplikasi Order')
        ->and($revision->json('data.draft.body'))->toContain('1 dari 2');

    $this->getJson("{$this->url}/export-options?kind=other")->assertUnprocessable();
});

test('the UAT result and revision documents export as PDFs', function () {
    $result = $this->postJson("{$this->url}/export", ['kind' => 'result', 'cover' => 'none'])->assertOk();
    expect($result->headers->get('Content-Disposition'))->toContain('Laporan-UAT-1-Aplikasi-Order.pdf');
    $text = uatPdfText($result->getContent());
    expect($text)->toContain('kantor jakarta')->toContain('tombol simpan')->toContain('warna grafik')->not->toContain('status revisi');

    $revision = $this->postJson("{$this->url}/export", ['kind' => 'revision', 'cover' => 'none'])->assertOk();
    expect($revision->headers->get('Content-Disposition'))->toContain('Laporan-Revisi-UAT-1-Aplikasi-Order.pdf');
    expect(uatPdfText($revision->getContent()))->toContain('status revisi')->toContain('sudah direvisi')->toContain('dalam proses');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'w_500'));
})->skip(fn () => ! trim((string) shell_exec('command -v pdftotext')), 'pdftotext not installed');

test('photos are really drawn: the issue photo in the result, before and after in the revision report', function () {
    $result = $this->postJson("{$this->url}/export", ['kind' => 'result', 'cover' => 'none'])->assertOk();
    expect(uatPdfImages($result->getContent()))->toBe(1);

    $revision = $this->postJson("{$this->url}/export", ['kind' => 'revision', 'cover' => 'none'])->assertOk();
    expect(uatPdfImages($revision->getContent()))->toBe(2)
        ->and(uatPdfText($revision->getContent()))->toContain('sebelum')->toContain('sesudah')
        // An item with no photo on either side says so.
        ->toContain('tanpa foto sebelum & sesudah');
})->skip(fn () => ! trim((string) shell_exec('command -v pdfimages')), 'pdfimages not installed');

test('a new cover letter goes in front, with the document as its enclosure', function () {
    $response = $this->postJson("{$this->url}/export", [
        'kind' => 'revision', 'cover' => 'new', 'template' => 'primary', 'division_code_id' => $this->division->id,
        'date' => '2026-11-28', 'subject' => 'Laporan Hasil Revisi UAT ke-1 – Aplikasi Order', 'body' => '<p>Isi</p>',
    ])->assertOk();

    $letter = Letter::sole();
    expect($letter->enclosure)->toBe('1 (satu) berkas Laporan Hasil Revisi UAT ke-1')
        ->and($letter->project_id)->toBe($this->project->id)
        ->and($response->headers->get('X-Letter-Number'))->toBe($letter->letter_number);
});

test('exporting needs the project export permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    $this->getJson("{$this->url}/export-options?kind=result")->assertForbidden();
    $this->postJson("{$this->url}/export", ['kind' => 'result', 'cover' => 'none'])->assertForbidden();
});
