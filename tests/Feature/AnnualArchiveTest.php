<?php

use App\Models\DivisionCode;
use App\Models\Invoice;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function archivePages(string $bytes): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $bytes);
}

beforeEach(function () {
    foreach (['letter-list', 'invoice-list'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo(['letter-list', 'invoice-list']);
    Role::firstOrCreate(['name' => 'letters-only', 'guard_name' => 'sanctum'])->givePermissionTo(['letter-list']);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $code = LetterCode::firstOrCreate(['code' => 'SKT'], ['name' => 'Surat Keterangan']);
    $division = DivisionCode::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resource']);
    $letter = fn (string $number, string $date, string $status = 'issued') => Letter::create([
        'letter_number' => $number, 'letter_code_id' => $code->id, 'division_code_id' => $division->id, 'type' => 'I',
        'year' => (int) substr($date, 0, 4), 'sequence' => 1, 'date' => $date, 'subject' => 'Surat '.$number, 'body' => 'Isi surat', 'status' => $status,
    ]);
    $letter('001/I/SKT-JCD/HR/I/2026', '2026-01-05');
    $letter('002/I/SKT-JCD/HR/XII/2026', '2026-12-31');
    $letter('003/I/SKT-JCD/HR/XII/2026', '2026-12-20', 'cancelled');
    $letter('001/I/SKT-JCD/HR/I/2027', '2027-01-01');
    $letter('009/I/SKT-JCD/HR/XII/2025', '2025-12-31');

    foreach ([['INV/JCD-ABC/0302/26.001', '2026-02-03', 'paid'], ['INV/JCD-ABC/1005/26.002', '2026-05-10', 'cancelled']] as [$number, $date, $status]) {
        Invoice::create(['invoice_number' => $number, 'client_code' => 'ABC', 'client_name' => 'PT Contoh', 'date' => $date, 'items' => [['description' => 'Jasa', 'qty' => '1', 'price' => 1000000]], 'subtotal' => 1000000, 'total' => 1000000, 'status' => $status]);
    }
});

test('the options list the year\'s counts for the types this user can read', function () {
    $this->getJson('/api/v1/annual-archive/options?year=2026')
        ->assertOk()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.types.0.key', 'letters')
        ->assertJsonPath('data.types.0.count', 2) // the cancelled one and other years are out
        ->assertJsonPath('data.types.1.key', 'invoices')
        ->assertJsonPath('data.types.1.count', 1)
        ->assertJsonCount(2, 'data.types');
    expect($this->getJson('/api/v1/annual-archive/options')->json('data.years'))->toContain(2027, 2026, 2025);
});

test('one type downloads as one PDF: cover, then each document of that year', function () {
    $response = $this->postJson('/api/v1/annual-archive/export', ['year' => 2026, 'types' => ['letters']])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('Arsip-2026-Surat.pdf');

    $single = archivePages($this->get('/api/v1/letters/'.Letter::where('letter_number', '001/I/SKT-JCD/HR/I/2026')->value('id').'/export-pdf')->getContent());
    // 1 cover page + 2 letters (the 2025, 2027 and cancelled letters are left out).
    expect(archivePages($response->getContent()))->toBe(1 + 2 * $single);
});

test('several types download as a ZIP with one PDF per type', function () {
    $response = $this->post('/api/v1/annual-archive/export', ['year' => 2026, 'types' => ['letters', 'invoices']], ['Accept' => 'application/json'])->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'zip-test-');
    file_put_contents($path, $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname()));

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
    expect($names)->toBe(['Arsip-2026-Invoice.pdf', 'Arsip-2026-Surat.pdf'])
        ->and(str_starts_with($zip->getFromName('Arsip-2026-Invoice.pdf'), '%PDF'))->toBeTrue();
    $zip->close();
    @unlink($path);
});

test('types the user cannot read are refused', function () {
    $user = User::factory()->create();
    $user->assignRole('letters-only');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/annual-archive/options?year=2026')->assertOk()->assertJsonCount(1, 'data.types');
    $this->postJson('/api/v1/annual-archive/export', ['year' => 2026, 'types' => ['invoices']])->assertUnprocessable();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/annual-archive/options')->assertForbidden();
});

test('meeting notes follow their role limit too', function () {
    Permission::firstOrCreate(['name' => 'meeting-note-list', 'guard_name' => 'sanctum']);
    $user = User::factory()->create();
    $user->assignRole('letters-only');
    $user->givePermissionTo('meeting-note-list');
    Sanctum::actingAs($user);
    expect(collect($this->getJson('/api/v1/annual-archive/options?year=2026')->json('data.types'))->pluck('key'))->not->toContain('meeting_notes');

    Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
    $user->assignRole('hr');
    expect(collect($this->getJson('/api/v1/annual-archive/options?year=2026')->json('data.types'))->pluck('key'))->toContain('meeting_notes');
});
