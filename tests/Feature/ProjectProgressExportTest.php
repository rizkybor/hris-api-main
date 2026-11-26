<?php

use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\Project;
use App\Models\ProjectUatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Pages in a PDF (dompdf and FPDF both write one /Type /Page per page).
function pdfPages(string $bytes): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $bytes);
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-26 10:00:00');
    foreach (['project-list', 'project-export', 'letter-list', 'letter-create'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-export', 'letter-list', 'letter-create']);
    Role::firstOrCreate(['name' => 'reporter', 'guard_name' => 'sanctum'])->givePermissionTo(['project-list', 'project-export']);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $this->division = DivisionCode::create(['code' => 'PRJ', 'name' => 'Project']);
    $this->project = Project::create([
        'name' => 'Aplikasi Order', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active',
        'start_date' => '2026-07-01', 'end_date' => '2026-10-31',
        'retention_start_date' => '2026-11-01', 'retention_end_date' => '2027-01-31', 'uat_rounds_planned' => 2,
    ]);
    ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 1, 'status' => 'revision', 'date' => '2026-10-20', 'findings' => "Tombol simpan\nLaporan"]);
    ProjectUatSession::create(['project_id' => $this->project->id, 'round' => 2, 'status' => 'passed', 'date' => '2026-10-28']);
    $this->url = "/api/v1/projects/{$this->project->id}";
});

afterEach(fn () => Carbon::setTestNow());

test('the options carry a draft cover letter from the project', function () {
    $this->getJson("{$this->url}/cover-letter-options")
        ->assertOk()
        ->assertJsonPath('data.can_create', true)
        ->assertJsonPath('data.draft.subject', 'Laporan Progres Proyek Aplikasi Order')
        ->assertJsonPath('data.draft.template', 'primary')
        ->assertJsonPath('data.divisions.0.code', 'PRJ')
        ->assertJsonCount(0, 'data.letters');
    expect($this->getJson("{$this->url}/cover-letter-options")->json('data.draft.body'))->toContain('Masa retensi, hingga 31 Januari 2027');
});

test('the report alone shows the timeline and the UAT', function () {
    $response = $this->postJson("{$this->url}/export-progress", ['cover' => 'none'])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->has('X-Letter-Number'))->toBeFalse()
        ->and(Letter::count())->toBe(0);
});

test('a new cover letter is numbered, linked to the project and put in front of the report', function () {
    $report = $this->postJson("{$this->url}/export-progress", ['cover' => 'none'])->getContent();

    $response = $this->postJson("{$this->url}/export-progress", [
        'cover' => 'new',
        'template' => 'secondary',
        'division_code_id' => $this->division->id,
        'date' => '2026-11-26',
        'recipient' => "Bapak Andi\nPT Contoh",
        'subject' => 'Laporan Progres Proyek Aplikasi Order',
        'body' => '<p>Dengan hormat,</p><p>Terlampir laporan progres.</p>',
        'signatory_name' => 'Rizky',
        'signatory_title' => 'Direktur',
    ])->assertOk();

    $letter = Letter::with('letterCode')->sole();
    expect($letter->letterCode->code)->toBe('SPG')
        ->and($letter->project_id)->toBe($this->project->id)
        ->and($letter->template)->toBe('secondary')
        ->and($letter->type)->toBe('E')
        ->and($letter->letter_number)->toBe('001/E/SPG-JCD/PRJ/XI/2026')
        ->and($response->headers->get('X-Letter-Number'))->toBe('001/E/SPG-JCD/PRJ/XI/2026')
        ->and(pdfPages($response->getContent()))->toBeGreaterThan(pdfPages($report));

    // Offered again next time.
    $this->getJson("{$this->url}/cover-letter-options")
        ->assertJsonPath('data.letters.0.letter_number', '001/E/SPG-JCD/PRJ/XI/2026')
        ->assertJsonPath('data.draft.template', 'secondary')
        ->assertJsonPath('data.draft.signatory_name', 'Rizky');
});

test('an earlier cover letter can be reused without a new number', function () {
    $this->postJson("{$this->url}/export-progress", ['cover' => 'new', 'template' => 'primary', 'division_code_id' => $this->division->id, 'date' => '2026-11-26', 'subject' => 'S', 'body' => 'Isi'])->assertOk();
    $letter = Letter::sole();

    $this->postJson("{$this->url}/export-progress", ['cover' => 'existing', 'letter_id' => $letter->id])
        ->assertOk()
        ->assertHeader('X-Letter-Number', $letter->letter_number);
    expect(Letter::count())->toBe(1);

    // A letter of another project is not offered.
    $other = Project::create(['name' => 'Lain', 'type' => 'web_development', 'priority' => 'medium', 'status' => 'active', 'start_date' => '2026-01-01']);
    $this->postJson("/api/v1/projects/{$other->id}/export-progress", ['cover' => 'existing', 'letter_id' => $letter->id])->assertNotFound();
});

test('a new cover letter needs its fields and the letter-create permission', function () {
    $this->postJson("{$this->url}/export-progress", ['cover' => 'new'])->assertUnprocessable()
        ->assertJsonValidationErrors(['template', 'division_code_id', 'date', 'subject', 'body']);

    $reporter = User::factory()->create();
    $reporter->assignRole('reporter');
    Sanctum::actingAs($reporter);
    $this->getJson("{$this->url}/cover-letter-options")->assertOk()->assertJsonPath('data.can_create', false)->assertJsonPath('data.can_reuse', false);
    $this->postJson("{$this->url}/export-progress", ['cover' => 'new', 'template' => 'primary', 'division_code_id' => $this->division->id, 'date' => '2026-11-26', 'subject' => 'S', 'body' => 'Isi'])->assertForbidden();
    $this->postJson("{$this->url}/export-progress", ['cover' => 'none'])->assertOk();
});

test('the plain progress export still works', function () {
    $this->get("{$this->url}/export-progress")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a signed cover letter needs a signatory whose signature is on file', function () {
    $signatories = $this->getJson("{$this->url}/cover-letter-options")->json('data.signatories');
    expect(collect($signatories)->pluck('key')->all())->toBe(['aldi', 'andy', 'rizky'])
        ->and($signatories[0]['preview'])->toStartWith('data:image/png;base64,');

    $base = ['cover' => 'new', 'template' => 'primary', 'division_code_id' => $this->division->id, 'date' => '2026-11-26', 'subject' => 'S', 'body' => 'Isi'];

    $this->postJson("{$this->url}/export-progress", $base + ['use_signature' => true, 'signatory_name' => 'Budi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['signatory_name']);
    expect(Letter::count())->toBe(0);

    $this->postJson("{$this->url}/export-progress", $base + ['use_signature' => true, 'signatory_name' => 'Rizky Ajie Kurniawan', 'signatory_title' => 'Direktur'])->assertOk();
    expect(Letter::sole()->use_signature)->toBeTrue();

    // Unsigned: any name may be typed.
    $this->postJson("{$this->url}/export-progress", $base + ['use_signature' => false, 'signatory_name' => 'Budi'])->assertOk();
});
