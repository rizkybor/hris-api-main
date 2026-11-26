<?php

use App\Models\DivisionCode;
use App\Models\LetterCode;
use App\Models\User;
use App\Services\AssetCodeGenerator;
use App\Services\DocumentNumberService;
use App\Services\EmployeeCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Last minute of 2026 and the first minutes of 2027 in WIB. 00:05 WIB on
// 1 Jan is still 31 Dec 17:05 UTC (the app's clock), the case that used
// to keep last year's numbering.
const LAST_MINUTE_2026 = '2026-12-31 23:59:00';
const FIRST_MINUTES_2027 = '2027-01-01 00:05:00';

function atWib(string $time): void
{
    Carbon::setTestNow(Carbon::parse($time, 'Asia/Jakarta'));
}

beforeEach(function () {
    $this->numbers = app(DocumentNumberService::class);
});

afterEach(fn () => Carbon::setTestNow());

test('every document number restarts at 001 the moment the year changes (WIB)', function () {
    $make = fn () => [
        'letter' => $this->numbers->generateLetterNumber('SPG', 'E', 'BD', $this->numbers->today())['number'],
        'nota_dinas' => $this->numbers->generateNotaDinasNumber($this->numbers->today())['number'],
        'meeting_note' => $this->numbers->generateMeetingNoteNumber(now())['number'], // a UTC datetime
        'po' => $this->numbers->generatePoNumber('E', $this->numbers->today()),
        'invoice' => $this->numbers->generateInvoiceNumber('ABC', $this->numbers->today()),
        'receipt' => $this->numbers->generateReceiptNumber('ABC', $this->numbers->today()),
        'certificate' => $this->numbers->generateCertificateNumber('JCD', 'TRN', 'WEB', $this->numbers->today())['number'],
    ];

    atWib('2026-12-30 10:00:00');
    $make();
    atWib(LAST_MINUTE_2026);
    $late2026 = $make();

    expect($late2026['letter'])->toBe('002/E/SPG-JCD/BD/XII/2026')
        ->and($late2026['nota_dinas'])->toBe('ND/002/JCD/XII/2026')
        ->and($late2026['meeting_note'])->toBe('MN/002/JCD/XII/2026')
        ->and($late2026['po'])->toBe('PO/002/E/OPS/JCD/XII/2026')
        ->and($late2026['invoice'])->toBe('INV/JCD-ABC/3112/26.002')
        ->and($late2026['receipt'])->toBe('RCP/JCD-ABC/3112/26.002');

    atWib(FIRST_MINUTES_2027);
    $first2027 = $make();

    expect($first2027['letter'])->toBe('001/E/SPG-JCD/BD/I/2027')
        ->and($first2027['nota_dinas'])->toBe('ND/001/JCD/I/2027')
        ->and($first2027['meeting_note'])->toBe('MN/001/JCD/I/2027')
        ->and($first2027['po'])->toBe('PO/001/E/OPS/JCD/I/2027')
        ->and($first2027['invoice'])->toBe('INV/JCD-ABC/0101/27.001')
        ->and($first2027['receipt'])->toBe('RCP/JCD-ABC/0101/27.001')
        ->and($first2027['certificate'])->toContain('2027')
        ->and($first2027['certificate'])->toContain('0001');

    // The next one in 2027 continues from 001.
    expect($this->numbers->generatePoNumber('E', $this->numbers->today()))->toBe('PO/002/E/OPS/JCD/I/2027');
});

test('a document dated in the new year starts at 001 even when created before midnight', function () {
    atWib('2026-12-31 20:00:00');
    $this->numbers->generateInvoiceNumber('ABC', Carbon::parse('2026-12-31'));

    expect($this->numbers->generateInvoiceNumber('ABC', Carbon::parse('2027-01-01')))->toBe('INV/JCD-ABC/0101/27.001')
        ->and($this->numbers->generateInvoiceNumber('ABC', Carbon::parse('2026-12-31')))->toBe('INV/JCD-ABC/3112/26.002');
});

test('letters created through the API restart per year', function () {
    Permission::firstOrCreate(['name' => 'letter-create', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum'])->givePermissionTo('letter-create');
    $user = User::factory()->create();
    $user->assignRole('manager');
    Sanctum::actingAs($user);
    $code = LetterCode::firstOrCreate(['code' => 'SKT'], ['name' => 'Surat Keterangan']);
    $division = DivisionCode::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resource']);
    $letter = fn (string $date) => $this->postJson('/api/v1/letters', [
        'letter_code_id' => $code->id, 'division_code_id' => $division->id, 'type' => 'I',
        'date' => $date, 'subject' => 'Keterangan', 'body' => 'Isi',
    ])->assertCreated()->json('data.letter_number');

    atWib(LAST_MINUTE_2026);
    expect($letter('2026-12-31'))->toBe('001/I/SKT-JCD/HR/XII/2026')
        ->and($letter('2026-12-31'))->toBe('002/I/SKT-JCD/HR/XII/2026');

    atWib(FIRST_MINUTES_2027);
    expect($letter('2027-01-01'))->toBe('001/I/SKT-JCD/HR/I/2027');
});

test('employee and asset codes created without a date use the WIB year', function () {
    atWib(FIRST_MINUTES_2027);

    expect(app(EmployeeCodeGenerator::class)->generate('full_time'))->toContain('JCD27-')
        ->and(app(AssetCodeGenerator::class)->generate('IT-HW'))->toBe('JCD-IT-HW-27-0001');
});
