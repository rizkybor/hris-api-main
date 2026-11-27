<?php

namespace App\Services;

use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Models\Project;
use App\Models\User;
use App\Services\Pdf\LetterPdfService;
use App\Services\Pdf\PdfMerger;
use App\Support\SignatureImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cover letters (Surat Pengantar, letter code SPG) put in front of a
 * project document (progress report, UAT result, revision result) as one
 * PDF. A cover letter is a real, numbered Letter linked to the project: a
 * new one is created (needs letter-create) or one written for the project
 * before is reused (needs letter access).
 */
class CoverLetterService
{
    public const CODE = 'SPG';

    public function __construct(
        private DocumentNumberService $numbers,
        private LetterPdfService $letters,
        private PdfMerger $merger,
    ) {}

    /** What the export dialog needs, with the document's own draft text. */
    public function options(Project $project, User $user, array $draft): array
    {
        $canCreate = $user->can('letter-create');
        $canReuse = $canCreate || $user->can('letter-list') || $user->can('letter-menu');
        $previous = $canReuse ? $this->previous($project)->get() : collect();
        $last = $previous->first() ?? Letter::query()->whereNotNull('signatory_name')->latest('id')->first();

        return [
            'can_create' => $canCreate,
            'can_reuse' => $canReuse,
            'letters' => $previous->map(fn (Letter $letter) => [
                'id' => $letter->id,
                'letter_number' => $letter->letter_number,
                'date' => $letter->date?->toDateString(),
                'subject' => $letter->subject,
                'template' => $letter->template ?: 'primary',
            ])->values(),
            // People with a signature (+ company stamp) on file.
            'signatories' => $canCreate ? SignatureImage::signatories() : [],
            'divisions' => $canCreate ? DivisionCode::query()->orderBy('code')->get(['id', 'code', 'name']) : [],
            'draft' => $draft + [
                'date' => Carbon::today('Asia/Jakarta')->toDateString(),
                'template' => $previous->first()?->template ?: 'primary',
                'division_code_id' => $previous->first()?->division_code_id,
                'recipient' => $this->recipient($project),
                'signatory_name' => $last?->signatory_name,
                'signatory_title' => $last?->signatory_title,
                'use_signature' => (bool) ($last?->use_signature ?? false),
            ],
        ];
    }

    /** cover = none | new | existing, and the new letter's fields. */
    public function rules(): array
    {
        return [
            'cover' => ['required', Rule::in(['none', 'new', 'existing'])],
            'letter_id' => ['required_if:cover,existing', 'nullable', 'integer'],
            'template' => ['required_if:cover,new', 'nullable', Rule::in(['primary', 'secondary'])],
            'division_code_id' => ['required_if:cover,new', 'nullable', 'integer', 'exists:division_codes,id'],
            'date' => ['required_if:cover,new', 'nullable', 'date_format:Y-m-d'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'subject' => ['required_if:cover,new', 'nullable', 'string', 'max:255'],
            'body' => ['required_if:cover,new', 'nullable', 'string', 'max:20000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'use_signature' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The cover letter asked for (new or reused), or null for none. Aborts
     * with 403 / 404 when the user may not or the letter isn't there.
     */
    public function resolve(array $data, Project $project, User $user, string $enclosure): ?Letter
    {
        if ($data['cover'] === 'new') {
            abort_unless($user->can('letter-create'), 403, 'You do not have permission to create letters.');
            // A signed letter needs someone whose signature is on file.
            if (! empty($data['use_signature']) && ! SignatureImage::pathFor($data['signatory_name'] ?? null)) {
                throw ValidationException::withMessages(['signatory_name' => 'Pick a signatory whose signature is on file.']);
            }

            return $this->create($project, $data, $user->id, $enclosure);
        }
        if ($data['cover'] === 'existing') {
            abort_unless($user->can('letter-create') || $user->can('letter-list') || $user->can('letter-menu'), 403, 'You do not have permission to view letters.');
            $letter = $this->previous($project)->find($data['letter_id']);
            abort_unless($letter, 404, 'Cover letter not found for this project.');

            return $letter;
        }

        return null;
    }

    /**
     * The document as a PDF response, with the cover letter in front when
     * there is one (its number in the X-Letter-Number header).
     */
    public function respond(string $documentBytes, ?Letter $letter, string $filename, string $title)
    {
        $headers = ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$filename.'"'];
        if ($letter) {
            $documentBytes = $this->merger->merge([$this->letters->make($letter)->output(), $documentBytes], $title);
            $headers['X-Letter-Number'] = $letter->letter_number;
        }

        return response($documentBytes, 200, $headers);
    }

    public function recipient(Project $project): string
    {
        $client = $project->client;
        if (! $client) {
            return '';
        }

        return trim(($client->pic_name ? 'Bapak/Ibu '.$client->pic_name."\n" : '').$client->name);
    }

    /** "Uraian / Keterangan" summary table for a draft body. */
    public static function table(array $rows): string
    {
        $html = '<table><tbody><tr><th style="width: 34%;">Uraian</th><th>Keterangan</th></tr>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td>'.e($label).'</td><td>'.$value.'</td></tr>';
        }

        return $html.'</tbody></table>';
    }

    private function previous(Project $project)
    {
        return Letter::query()
            ->where('project_id', $project->id)
            ->where('status', 'issued')
            ->whereHas('letterCode', fn ($query) => $query->where('code', self::CODE))
            ->with(['letterCode', 'divisionCode'])
            ->latest('date')
            ->latest('id');
    }

    private function create(Project $project, array $data, int $userId, string $enclosure): Letter
    {
        $code = LetterCode::query()->firstOrCreate(['code' => self::CODE], ['name' => 'Surat Pengantar']);
        $division = DivisionCode::findOrFail($data['division_code_id']);
        $date = Carbon::parse($data['date']);

        return DB::transaction(function () use ($project, $data, $userId, $code, $division, $date, $enclosure) {
            $generated = $this->numbers->generateLetterNumber($code->code, 'E', $division->code, $date);

            return Letter::create([
                'letter_number' => $generated['number'],
                'sequence' => $generated['sequence'],
                'year' => $generated['year'],
                'letter_code_id' => $code->id,
                'division_code_id' => $division->id,
                'type' => 'E',
                'date' => $date->toDateString(),
                'subject' => $data['subject'],
                'enclosure' => $enclosure,
                'recipient' => $data['recipient'] ?? null,
                'body' => $data['body'],
                'signatory_name' => $data['signatory_name'] ?? null,
                'signatory_title' => $data['signatory_title'] ?? null,
                'use_signature' => (bool) ($data['use_signature'] ?? false),
                'template' => $data['template'],
                'project_id' => $project->id,
                'created_by' => $userId,
            ])->load(['letterCode', 'divisionCode']);
        });
    }
}
