<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProposalRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use App\Support\HtmlSanitizer;
use App\Support\ProposalTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProposalController extends Controller implements HasMiddleware
{
    public function __construct(protected CloudinaryManager $cloudinary) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['proposal-menu']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['proposal-create']), only: ['store', 'duplicate']),
            new Middleware(PermissionMiddleware::using(['proposal-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['proposal-create|proposal-edit']), only: ['uploadImage']),
            new Middleware(PermissionMiddleware::using(['proposal-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $proposals = Proposal::with(['creator', 'updater'])
                ->when($request->search, fn ($q) => $q->search($request->search))
                ->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->latest('proposal_date')
                ->latest('id')
                ->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Proposals Retrieved Successfully', PaginateResource::make($proposals, ProposalResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(ProposalRequest $request)
    {
        try {
            $data = $request->validated();

            $sections = isset($data['sections'])
                ? $this->cleanSections($data['sections'])
                : ProposalTemplate::sections(['client' => $data['client_name'], 'recipient' => $data['recipient'] ?? null]);

            $proposal = Proposal::create([
                ...collect($data)->except(['sections', 'recipient'])->all(),
                'status' => $data['status'] ?? 'draft',
                'use_signature' => $data['use_signature'] ?? true,
                'signatory_name' => $data['signatory_name'] ?? 'Aldi Pratama Putra, S.I.Kom.',
                'signatory_title' => $data['signatory_title'] ?? 'Direktur Utama',
                'sections' => $sections,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Proposal Created Successfully', (new ProposalResource($proposal->load(['creator', 'updater'])))->withSections(), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $proposal = Proposal::with(['creator', 'updater'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Proposal Retrieved Successfully', (new ProposalResource($proposal))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(ProposalRequest $request, int $id)
    {
        try {
            $proposal = Proposal::findOrFail($id);
            $data = $request->validated();

            $proposal->update([
                ...collect($data)->except(['sections', 'recipient'])->all(),
                'sections' => $this->cleanSections($data['sections']),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Proposal Updated Successfully', (new ProposalResource($proposal->load(['creator', 'updater'])))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            Proposal::findOrFail($id)->delete();

            return ResponseHelper::jsonResponse(true, 'Proposal Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Starts a new proposal from an existing one -- the usual way to write
     * a proposal for a similar project. The copy is a fresh draft dated
     * today, without the original's letter number.
     */
    public function duplicate(int $id)
    {
        try {
            $source = Proposal::findOrFail($id);

            $copy = $source->replicate(['letter_number', 'status', 'created_by', 'updated_by']);
            $copy->title = Str::limit($source->title.' (Salinan)', 255, '');
            $copy->status = 'draft';
            $copy->proposal_date = now('Asia/Jakarta')->toDateString();
            // Fresh section ids, so the copy's sections never share an id
            // with the original's.
            $copy->sections = array_map(fn ($s) => [...$s, 'id' => (string) Str::uuid()], $source->sections ?? []);
            $copy->created_by = Auth::id();
            $copy->updated_by = Auth::id();
            $copy->save();

            return ResponseHelper::jsonResponse(true, 'Proposal Duplicated Successfully', (new ProposalResource($copy->load(['creator', 'updater'])))->withSections(), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /** Images placed inside a proposal (diagrams, schedules) or as its cover. */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {
            $publicId = $this->cloudinary->uploadImage(
                $request->file('image'),
                CloudinaryFolders::companyFiles('proposals'),
                CloudinaryFolders::filename('proposal-image')
            );

            return ResponseHelper::jsonResponse(true, 'Image Uploaded Successfully', [
                'public_id' => $publicId,
                'url' => CloudinaryUrl::image($publicId),
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Image upload failed: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(int $id)
    {
        $proposal = Proposal::findOrFail($id);
        $tempFiles = [];

        try {
            // dompdf can't fetch remote images (enable_remote is off on
            // purpose), so the cover and every image inside the body are
            // pulled down from our Cloudinary account into storage first.
            // Images from anywhere else are dropped.
            $coverPath = $proposal->cover_image
                ? $this->downloadImage(CloudinaryUrl::image($proposal->cover_image), $tempFiles)
                : null;

            $sections = array_map(function ($section) use (&$tempFiles) {
                $section['content'] = preg_replace_callback(
                    '/<img\b[^>]*?\bsrc="([^"]*)"[^>]*>/i',
                    function ($match) use (&$tempFiles) {
                        $local = $this->isOwnCloudinaryUrl($match[1]) ? $this->downloadImage(html_entity_decode($match[1]), $tempFiles) : null;

                        return $local ? str_replace($match[1], $local, $match[0]) : '';
                    },
                    $section['content'] ?? ''
                );

                return $section;
            }, $proposal->sections ?? []);

            $pdf = Pdf::loadView('pdf.proposal', [
                'proposal' => $proposal,
                'sections' => $sections,
                'coverPath' => $coverPath,
            ])->setPaper('a4');

            $this->addPageChrome($pdf, $proposal);

            $output = $pdf->output();
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }

        $filename = Str::slug($proposal->documentTitle()) ?: 'proposal-penawaran';

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'.pdf"',
        ]);
    }

    /**
     * Running header and footer on every content page (the cover, page 1,
     * stays clean): company name / document label with a thin rule at the
     * top, and the document title / "Halaman x dari y" at the bottom.
     */
    private function addPageChrome($pdf, Proposal $proposal): void
    {
        // Through the wrapper (not Dompdf directly), so output() knows it's
        // already rendered and doesn't render again, dropping the script.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $metrics = $dompdf->getFontMetrics();
        $canvas = $dompdf->getCanvas();

        $regular = $metrics->getFont('Helvetica');
        $bold = $metrics->getFont('Helvetica', 'bold');
        $navy = [0.04, 0.11, 0.32];
        $muted = [0.39, 0.45, 0.55];
        $rule = [0.84, 0.86, 0.90];
        $margin = 62; // ~22mm, matches the page's side margins
        $size = 7.5;

        $headerRight = 'Proposal Penawaran · '.$proposal->client_name;
        $footerLeft = $proposal->documentTitle();
        $maxWidth = $canvas->get_width() - $margin * 2 - 90;
        foreach (['headerRight', 'footerLeft'] as $var) {
            while ($metrics->getTextWidth($$var, $regular, $size) > $maxWidth && mb_strlen($$var) > 10) {
                $$var = mb_substr($$var, 0, -4).'…';
            }
        }

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($regular, $bold, $navy, $muted, $rule, $margin, $size, $headerRight, $footerLeft) {
            if ($pageNumber === 1) {
                return;
            }
            $width = $canvas->get_width();
            $height = $canvas->get_height();

            // Header
            $canvas->text($margin, 34, 'PT JENDELA CAKRA DIGITAL', $bold, $size, $navy);
            $canvas->text($width - $margin - $fontMetrics->getTextWidth($headerRight, $regular, $size), 34, $headerRight, $regular, $size, $muted);
            $canvas->line($margin, 48, $width - $margin, 48, $rule, 0.6);

            // Footer
            $y = $height - 40;
            $canvas->line($margin, $y - 8, $width - $margin, $y - 8, $rule, 0.6);
            $canvas->text($margin, $y, $footerLeft, $regular, $size, $muted);
            $pageLabel = "Halaman {$pageNumber} dari {$pageCount}";
            $canvas->text($width - $margin - $fontMetrics->getTextWidth($pageLabel, $regular, $size), $y, $pageLabel, $regular, $size, $muted);
        });
    }

    private function cleanSections(array $sections): array
    {
        return array_values(array_map(fn ($section) => [
            'id' => (string) $section['id'],
            'title' => trim((string) ($section['title'] ?? '')),
            'content' => HtmlSanitizer::clean($section['content'] ?? ''),
            'page_break_before' => (bool) ($section['page_break_before'] ?? false),
        ], $sections));
    }

    private function isOwnCloudinaryUrl(string $url): bool
    {
        $cloud = config('services.cloudinary.cloud_name');

        return $cloud && str_starts_with(html_entity_decode($url), "https://res.cloudinary.com/{$cloud}/");
    }

    /**
     * Downloads into storage/app/temp -- inside dompdf's chroot (the
     * project directory), which a system temp dir like /tmp is not.
     */
    private function downloadImage(?string $url, array &$tempFiles): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $dir = storage_path('app/temp');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Ask Cloudinary for a print-sized JPEG instead of the original
            // upload (photos straight off a camera can be 6000px / 10MB),
            // which keeps the PDF small and fast to render.
            $url = preg_replace('#/image/upload/(?!w_)#', '/image/upload/w_1600,c_limit,q_80,f_jpg/', $url, 1);

            $path = $dir.'/proposal-'.Str::random(16);
            file_put_contents($path, Http::timeout(20)->get($url)->throw()->body());
            $tempFiles[] = $path;

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Proposal PDF image download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
