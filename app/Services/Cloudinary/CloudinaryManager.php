<?php

namespace App\Services\Cloudinary;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Cloudinary SDK for this app's two folders
 * (JCD-OFFICE/project-files, JCD-OFFICE/company-files). Every upload gets a
 * public_id we choose ourselves (never Cloudinary's auto-generated one) so
 * the same id can always be used for deletion later without having to parse
 * it back out of a URL -- the existing DB columns that used to hold a local
 * relative path (e.g. "team-icons/abc.png") now hold this public_id
 * instead; CloudinaryUrl::for() replaces the old asset('storage/'.$path)
 * calls in Resources.
 *
 * Image public_ids never include an extension (Cloudinary infers format on
 * delivery); raw public_ids (PDFs, docs, etc.) always do, since Cloudinary
 * can't infer format for the "raw" resource type.
 *
 * This account uses Cloudinary's Dynamic Folder Mode, where a slash in the
 * public_id alone does NOT place the asset in that folder in the Media
 * Library UI (it stays under Home) -- `asset_folder` must be passed
 * explicitly on every upload for the Console folder tree to match the
 * public_id path.
 */
class CloudinaryManager
{
    public const BASE_FOLDER = 'JCD-OFFICE';

    private Cloudinary $client;

    public function __construct()
    {
        $this->client = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    /**
     * @return string the public_id to store in place of the old local path
     */
    public function uploadImage(UploadedFile $file, string $folder, string $filename): string
    {
        $publicId = $this->buildPublicId($folder, $filename);

        $this->upload($file->getRealPath(), [
            'public_id' => $publicId,
            'asset_folder' => $folder,
            'resource_type' => 'image',
            'overwrite' => true,
        ]);

        return $publicId;
    }

    /**
     * For non-image files (PDF, DOC, etc.) -- resource_type "raw" requires
     * the extension to be part of the public_id, unlike images.
     *
     * @return string the public_id to store in place of the old local path
     */
    public function uploadRaw(UploadedFile $file, string $folder, string $filename): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $publicId = $this->buildPublicId($folder, "{$filename}.{$extension}");

        $this->upload($file->getRealPath(), [
            'public_id' => $publicId,
            'asset_folder' => $folder,
            'resource_type' => 'raw',
            'overwrite' => true,
        ]);

        return $publicId;
    }

    /**
     * Uploads either an image or a raw file depending on its mime type,
     * for spots (multi-file attachments) that accept a mix of both.
     */
    public function uploadAuto(UploadedFile $file, string $folder, string $filename): string
    {
        return CloudinaryResourceType::fromMime($file->getMimeType()) === 'image'
            ? $this->uploadImage($file, $folder, $filename)
            : $this->uploadRaw($file, $folder, $filename);
    }

    /**
     * For server-generated content (e.g. a rendered certificate PDF) that
     * never existed as an UploadedFile.
     */
    public function uploadFromString(string $content, string $folder, string $filename, string $extension = 'pdf'): string
    {
        $publicId = $this->buildPublicId($folder, "{$filename}.{$extension}");

        $this->upload(
            'data:application/octet-stream;base64,'.base64_encode($content),
            [
                'public_id' => $publicId,
                'asset_folder' => $folder,
                'resource_type' => 'raw',
                'overwrite' => true,
            ]
        );

        return $publicId;
    }

    /**
     * Best-effort: failing to delete an old/orphaned asset is logged but
     * never thrown, since by the time delete() runs the DB write it's
     * cleaning up after has already committed successfully -- surfacing a
     * 500 here would tell the user an update failed when it actually
     * succeeded, it just left one stray file behind in Cloudinary.
     */
    public function delete(?string $publicId, string $resourceType = 'image'): void
    {
        if (! $publicId) {
            return;
        }

        try {
            $this->client->uploadApi()->destroy($publicId, ['resource_type' => $resourceType]);
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws \RuntimeException a user-safe message; the real SDK/cURL
     *   error (which can leak transport details like hostnames or timeouts)
     *   is logged instead of being allowed to reach the API response.
     */
    private function upload(string $source, array $options): void
    {
        try {
            $this->client->uploadApi()->upload($source, $options);
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'public_id' => $options['public_id'] ?? null,
                'resource_type' => $options['resource_type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('File upload failed, please try again.', 0, $e);
        }
    }

    private function buildPublicId(string $folder, string $filename): string
    {
        return trim($folder, '/').'/'.trim($filename, '/');
    }
}
