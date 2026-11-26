<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\Pdf\AnnualArchiveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use ZipArchive;

/**
 * Annual archive (for the company's yearly report): the chosen document
 * types of one year, each type as one PDF. One type downloads as a PDF,
 * several as a ZIP holding one PDF per type. Each type needs the same
 * permission as viewing that document's list.
 */
class AnnualArchiveController extends Controller
{
    public function __construct(private AnnualArchiveService $archive) {}

    public function options(Request $request)
    {
        $allowed = $this->archive->allowedTypes($request->user());
        if (! $allowed) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to any documents.', null, 403);
        }
        $years = $this->archive->years($allowed);
        $year = (int) ($request->query('year') ?: $years[0]);
        $types = $this->archive->types();

        return ResponseHelper::jsonResponse(true, 'Annual Archive Options Retrieved Successfully', [
            'year' => $year,
            'years' => $years,
            'types' => array_map(fn ($key) => [
                'key' => $key,
                'label' => $types[$key]['label'],
                'count' => $this->archive->count($key, $year),
            ], $allowed),
        ], 200);
    }

    public function export(Request $request)
    {
        $allowed = $this->archive->allowedTypes($request->user());
        if (! $allowed) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to any documents.', null, 403);
        }
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', 'distinct', Rule::in($allowed)],
        ], [
            'types.required' => 'Pilih minimal satu jenis dokumen.',
            'types.*.in' => 'Jenis dokumen tidak tersedia untuk akun Anda.',
        ]);

        // A whole year of documents can take a while to render.
        @set_time_limit(600);
        $year = (int) $data['year'];
        $types = array_values($data['types']);

        if (count($types) === 1) {
            [$bytes] = $this->archive->build($types[0], $year);

            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->archive->filename($types[0], $year).'"',
            ]);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'archive-zip-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($types as $type) {
            [$bytes] = $this->archive->build($type, $year);
            $zip->addFromString($this->archive->filename($type, $year), $bytes);
        }
        $zip->close();

        return response()->download($zipPath, 'Arsip-Dokumen-'.$year.'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
