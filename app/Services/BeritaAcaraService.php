<?php

namespace App\Services;

use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Models\ProjectUatItem;
use App\Models\ProjectUatSession;
use App\Models\User;
use App\Support\SignatureImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Berita Acara UAT: a numbered letter (code BA) per UAT, signed by both
 * parties (PIHAK PERTAMA: JCD, PIHAK KEDUA: the client), listing how the
 * UAT went and its findings/notes. Generating again updates the same
 * letter (same number) with the UAT's current data.
 */
class BeritaAcaraService
{
    public const CODE = 'BA';

    private const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function __construct(private DocumentNumberService $numbers) {}

    public function options(ProjectUatSession $uat, User $user): array
    {
        $uat->loadMissing(['project.client', 'beritaAcara']);
        $existing = $uat->beritaAcara;
        $last = $existing ?? Letter::query()->whereNotNull('signatory_name')->latest('id')->first();
        $client = $uat->project->client;

        return [
            'can_create' => $user->can('letter-create'),
            'existing' => $existing ? ['id' => $existing->id, 'letter_number' => $existing->letter_number, 'date' => $existing->date?->toDateString()] : null,
            'signatories' => SignatureImage::signatories(),
            'divisions' => DivisionCode::query()->orderBy('code')->get(['id', 'code', 'name']),
            'draft' => [
                'date' => $existing?->date?->toDateString() ?? ($uat->date?->format('Y-m-d') ?: Carbon::today('Asia/Jakarta')->toDateString()),
                'template' => $existing?->template ?: 'secondary',
                'division_code_id' => $existing?->division_code_id ?? Letter::query()->whereHas('letterCode', fn ($q) => $q->where('code', self::CODE))->latest('id')->value('division_code_id'),
                'signatory_name' => $last?->signatory_name,
                'signatory_title' => $last?->signatory_title,
                'use_signature' => (bool) ($last?->use_signature ?? false),
                'second_party_name' => $existing?->second_party_name ?? $client?->name,
                'second_party_signatory_name' => $existing?->second_party_signatory_name ?? $client?->pic_name,
                'second_party_signatory_title' => $existing?->second_party_signatory_title,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'template' => ['required', Rule::in(['primary', 'secondary'])],
            'division_code_id' => ['required', 'integer', 'exists:division_codes,id'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'use_signature' => ['sometimes', 'boolean'],
            'second_party_name' => ['required', 'string', 'max:255'],
            'second_party_signatory_name' => ['nullable', 'string', 'max:255'],
            'second_party_signatory_title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Creates the UAT's Berita Acara, or refreshes the one it has (same number). */
    public function generate(ProjectUatSession $uat, array $data, int $userId): Letter
    {
        if (! empty($data['use_signature']) && ! SignatureImage::pathFor($data['signatory_name'] ?? null)) {
            throw ValidationException::withMessages(['signatory_name' => 'Pilih penandatangan yang tanda tangannya tersedia.']);
        }
        $uat->loadMissing(['project.client', 'locations.items', 'beritaAcara']);
        $values = [
            'date' => $data['date'],
            'subject' => 'User Acceptance Test (UAT) ke-'.$uat->round.' '.$uat->project->name,
            'recipient' => null,
            'body' => $this->body($uat, Carbon::parse($data['date'])),
            'template' => $data['template'],
            'division_code_id' => (int) $data['division_code_id'],
            'signatory_name' => $data['signatory_name'] ?? null,
            'signatory_title' => $data['signatory_title'] ?? null,
            'use_signature' => (bool) ($data['use_signature'] ?? false),
            'second_party_name' => $data['second_party_name'],
            'second_party_signatory_name' => $data['second_party_signatory_name'] ?? null,
            'second_party_signatory_title' => $data['second_party_signatory_title'] ?? null,
            'project_id' => $uat->project_id,
        ];

        return DB::transaction(function () use ($uat, $values, $userId) {
            $letter = $uat->beritaAcara;
            if ($letter && $letter->status === 'issued') {
                $letter->update($values);
            } else {
                $code = LetterCode::query()->firstOrCreate(['code' => self::CODE], ['name' => 'Berita Acara']);
                $division = DivisionCode::findOrFail($values['division_code_id']);
                $generated = $this->numbers->generateLetterNumber($code->code, 'E', $division->code, Carbon::parse($values['date']));
                $letter = Letter::create($values + [
                    'letter_number' => $generated['number'],
                    'sequence' => $generated['sequence'],
                    'year' => $generated['year'],
                    'letter_code_id' => $code->id,
                    'type' => 'E',
                    'created_by' => $userId,
                ]);
                $uat->update(['ba_letter_id' => $letter->id]);
            }

            return $letter->fresh(['letterCode', 'divisionCode']);
        });
    }

    /** The Berita Acara text, from the UAT's current data. */
    public function body(ProjectUatSession $uat, Carbon $date): string
    {
        $fmt = fn ($d) => $d ? Carbon::parse($d)->locale('id')->translatedFormat('d F Y') : '-';
        $project = $uat->project;
        $client = $project->client?->name ?? 'Klien';
        $items = $uat->locations->flatMap(fn ($location) => $location->items);
        $findings = $items->where('type', ProjectUatItem::TYPE_FINDING);
        $notes = $items->where('type', ProjectUatItem::TYPE_NOTE);
        $place = fn ($location, $i) => $location->location ?: 'Sesi '.($i + 1);
        $sessions = $uat->locations->values()->map(function ($location, $i) use ($place, $fmt, $uat) {
            $when = $fmt($location->held_on ?? $uat->date).($location->start_time ? ', pukul '.$location->start_time : '');

            return e($place($location, $i)).' ('.$when.')';
        })->implode('<br>');
        $participants = $uat->locations->pluck('participants')->filter()->map(fn ($p) => e($p))->implode('; ');
        $result = [
            ProjectUatSession::STATUS_PASSED => 'Lulus',
            ProjectUatSession::STATUS_REVISION => 'Perlu revisi',
            ProjectUatSession::STATUS_IN_PROGRESS => 'Sedang berlangsung',
            ProjectUatSession::STATUS_SCHEDULED => 'Terjadwal',
        ][$uat->status] ?? $uat->status;

        $html = '<p>Pada hari ini, '.self::DAYS[$date->dayOfWeek].', tanggal '.$fmt($date).', telah dilaksanakan User Acceptance Test (UAT) ke-'.$uat->round
            .' atas proyek <strong>'.e($project->name).'</strong> oleh:</p>'
            .'<table><tbody>'
            .'<tr><td style="width: 30%;">PIHAK PERTAMA</td><td>PT. Jendela Cakra Digital, selaku pengembang</td></tr>'
            .'<tr><td>PIHAK KEDUA</td><td>'.e($client).', selaku pengguna</td></tr>'
            .'</tbody></table>'
            .'<p>dengan rincian pelaksanaan sebagai berikut:</p>'
            .CoverLetterService::table(array_filter([
                'Tanggal UAT' => $fmt($uat->date),
                'Sesi & lokasi' => $sessions ?: '-',
                'Peserta' => $participants ?: null,
                'Jumlah temuan' => (string) $findings->count(),
                'Jumlah catatan' => (string) $notes->count(),
                'Hasil UAT' => '<strong>'.$result.'</strong>',
            ]));

        if ($items->count()) {
            $html .= '<p>Adapun temuan dan catatan hasil UAT adalah sebagai berikut:</p>'
                .'<table><tbody><tr><th style="width: 6%;">No</th><th style="width: 14%;">Jenis</th><th style="width: 24%;">Fitur / Menu</th><th>Uraian</th></tr>';
            $no = 0;
            foreach ($uat->locations->values() as $i => $location) {
                foreach ($location->items as $item) {
                    $where = collect([$item->feature, $item->menu])->filter()->map(fn ($v) => e($v))->implode(' › ');
                    $html .= '<tr><td>'.(++$no).'</td><td>'.($item->type === ProjectUatItem::TYPE_FINDING ? 'Temuan' : 'Catatan').'</td><td>'.($where ?: '-')
                        .($uat->locations->count() > 1 ? '<br><span style="color: #6b7280;">'.e($place($location, $i)).'</span>' : '')
                        .'</td><td>'.e($item->description).'</td></tr>';
                }
            }
            $html .= '</tbody></table>';
        }

        $html .= match ($uat->status) {
            ProjectUatSession::STATUS_PASSED => '<p>Berdasarkan hasil UAT tersebut, PIHAK KEDUA menyatakan bahwa proyek telah diuji dan <strong>diterima (lulus UAT)</strong> sesuai dengan kebutuhan yang telah disepakati.</p>',
            ProjectUatSession::STATUS_REVISION => '<p>Berdasarkan hasil UAT tersebut, terdapat '.$findings->count().' temuan yang akan ditindaklanjuti oleh PIHAK PERTAMA. Hasil perbaikannya akan disampaikan kepada PIHAK KEDUA untuk diperiksa kembali.</p>',
            default => '<p>Hasil akhir UAT akan dituangkan setelah seluruh sesi pengujian selesai dilaksanakan.</p>',
        };

        return $html.'<p>Demikian Berita Acara ini dibuat dengan sebenarnya dan ditandatangani oleh kedua belah pihak untuk dipergunakan sebagaimana mestinya.</p>';
    }
}
