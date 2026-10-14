<?php

namespace App\Services;

use App\Models\DocumentLetter;
use App\Models\DocumentLetterAttachment;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns an Official Memo's attachments into "Lampiran" pages for its PDF:
 * images go in as-is, each page of a PDF attachment as an image, and files
 * that can't be drawn (DOC/DOCX) are only listed.
 */
class OfficialMemoAttachmentService
{
    // Keeps one huge attachment from turning the memo into a 200-page PDF.
    private const MAX_PAGES_PER_FILE = 20;

    private const MAX_PAGES_TOTAL = 40;

    // Box each attachment is fitted into, in mm -- deliberately smaller than
    // the printable area: a compact, still-legible reference copy rather
    // than a full-page reproduction.
    private const BOX_WIDTH_MM = 120;

    private const BOX_HEIGHT_MM = 160;

    // Source resolution fetched from Cloudinary: ~210 DPI at 120mm wide,
    // enough to read small print while keeping the PDF light.
    private const SOURCE_TRANSFORM = 'w_1000,c_limit,q_70';

    public function __construct(protected CloudinaryManager $cloudinary) {}

    /**
     * @param  array  $tempFiles  downloaded files, for the caller to delete
     * @return array{list: array, pages: array, sheets: array}
     *   list:   every attachment with its kind and whether it's attached
     *   pages:  [number, name, page, pages, path, width_mm, height_mm]
     *   sheets: the pages packed onto PDF pages (see packSheets)
     */
    public function prepare(DocumentLetter $memo, array &$tempFiles): array
    {
        $list = [];
        $pages = [];

        foreach ($memo->attachments as $index => $attachment) {
            $number = $index + 1;
            $kind = $this->kindOf($attachment);
            $entry = ['number' => $number, 'name' => $attachment->original_name, 'kind' => $kind, 'pages' => 0, 'attached' => false, 'truncated' => false];

            try {
                $urls = match ($kind) {
                    'image' => [$this->imageUrl(CloudinaryUrl::image($attachment->file_path))],
                    'pdf' => $this->pdfPageUrls($attachment),
                    default => [],
                };
            } catch (\Throwable $e) {
                Log::warning('Official memo attachment could not be rendered', ['attachment_id' => $attachment->id, 'error' => $e->getMessage()]);
                $urls = [];
            }

            $room = self::MAX_PAGES_TOTAL - count($pages);
            if (count($urls) > min(self::MAX_PAGES_PER_FILE, max(0, $room))) {
                $entry['truncated'] = true;
                $urls = array_slice($urls, 0, min(self::MAX_PAGES_PER_FILE, max(0, $room)));
            }

            foreach ($urls as $i => $url) {
                $path = $this->download($url, $tempFiles);
                if (! $path || ! ($size = @getimagesize($path))) {
                    continue;
                }
                [$w, $h] = $this->fit($size[0], $size[1]);
                $pages[] = [
                    'number' => $number,
                    'name' => $attachment->original_name,
                    'page' => $i + 1,
                    'pages' => count($urls),
                    'path' => $path,
                    'width_mm' => $w,
                    'height_mm' => $h,
                ];
            }

            $entry['pages'] = count(array_filter($pages, fn ($p) => $p['number'] === $number));
            $entry['attached'] = $entry['pages'] > 0;
            $list[] = $entry;
        }

        return ['list' => $list, 'pages' => $pages, 'sheets' => $this->packSheets($pages)];
    }

    // Usable height for attachments on a memo page (A4 minus the letterhead
    // margins, the continuation note at the bottom, and a little air), the
    // caption above each item, and the gap between items, in mm.
    private const SHEET_HEIGHT_MM = 222;

    private const CAPTION_MM = 7;

    private const GAP_MM = 6;

    // An item may be shrunk to fit the space left on a page, but never below
    // this share of its normal size -- smaller and small print stops being
    // legible, so it starts a new page instead.
    private const MIN_SQUEEZE = 0.7;

    /**
     * Fills each PDF page with as many attachment items as fit, top to
     * bottom, instead of one per page. When the next item doesn't fit, the
     * whole page (items already on it plus the new one) is shrunk evenly to
     * make room -- but never below MIN_SQUEEZE of the normal size; past that
     * the item starts a new page.
     *
     * @return array<int, array<int, array>> sheets of items (same shape as pages)
     */
    private function packSheets(array $items): array
    {
        $sheets = [];
        $current = [];

        // Uniform scale that fits $group on one page (1.0 = normal size).
        $scaleFor = function (array $group): float {
            $fixed = count($group) * self::CAPTION_MM + (count($group) - 1) * self::GAP_MM;
            $heights = array_sum(array_column($group, 'height_mm'));

            return min(1.0, (self::SHEET_HEIGHT_MM - $fixed) / $heights);
        };

        $finish = function (array $group) use ($scaleFor): array {
            $scale = $scaleFor($group);

            return array_map(fn ($item) => [
                ...$item,
                'width_mm' => round($item['width_mm'] * $scale, 1),
                'height_mm' => round($item['height_mm'] * $scale, 1),
            ], $group);
        };

        foreach ($items as $item) {
            if ($current && $scaleFor([...$current, $item]) < self::MIN_SQUEEZE) {
                $sheets[] = $finish($current);
                $current = [];
            }
            $current[] = $item;
        }

        if ($current) {
            $sheets[] = $finish($current);
        }

        return $sheets;
    }

    private function kindOf(DocumentLetterAttachment $attachment): string
    {
        $extension = strtolower(pathinfo($attachment->original_name ?? '', PATHINFO_EXTENSION));

        if (str_starts_with((string) $attachment->mime_type, 'image/') || in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return 'image';
        }
        if ($attachment->mime_type === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        return 'document';
    }

    /**
     * One JPEG URL per page of a PDF attachment. The raw PDF is copied to an
     * image resource on first use (cached on the attachment).
     */
    private function pdfPageUrls(DocumentLetterAttachment $attachment): array
    {
        if (! $attachment->render_public_id) {
            $rendered = $this->cloudinary->uploadImageFromUrl(
                CloudinaryUrl::raw($attachment->file_path),
                CloudinaryFolders::companyFiles('document-letters'),
                CloudinaryFolders::filename('document-letter-'.$attachment->document_letter_id.'-attachment-pages')
            );
            $attachment->forceFill([
                'render_public_id' => $rendered['public_id'],
                'page_count' => $rendered['pages'],
            ])->save();
        }

        $base = CloudinaryUrl::image($attachment->render_public_id);
        $count = max(1, (int) $attachment->page_count);

        return array_map(
            fn ($page) => preg_replace('#/image/upload/#', "/image/upload/pg_{$page},".self::SOURCE_TRANSFORM.'/', $base, 1).'.jpg',
            range(1, $count)
        );
    }

    /** A print-sized JPEG of an image attachment, not the original upload. */
    private function imageUrl(string $url): string
    {
        return preg_replace('#/image/upload/#', '/image/upload/'.self::SOURCE_TRANSFORM.',f_jpg/', $url, 1);
    }

    /** Largest size (mm) that keeps the aspect ratio inside the page box. */
    private function fit(int $pxWidth, int $pxHeight): array
    {
        $scale = min(self::BOX_WIDTH_MM / $pxWidth, self::BOX_HEIGHT_MM / $pxHeight);

        return [round($pxWidth * $scale, 1), round($pxHeight * $scale, 1)];
    }

    /** Into storage/app/temp -- inside dompdf's chroot, unlike /tmp. */
    private function download(string $url, array &$tempFiles): ?string
    {
        try {
            $dir = storage_path('app/temp');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir.'/memo-attachment-'.Str::random(16);
            file_put_contents($path, Http::timeout(30)->get($url)->throw()->body());
            $tempFiles[] = $path;

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Official memo attachment page download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
