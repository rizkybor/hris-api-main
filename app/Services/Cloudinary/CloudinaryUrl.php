<?php

namespace App\Services\Cloudinary;

/**
 * Builds a delivery URL from a stored public_id -- the Cloudinary
 * equivalent of the old `asset('storage/'.$path)` calls scattered across
 * every Resource. No SDK call needed: Cloudinary's delivery URL shape is
 * stable and doesn't require a signed request to view an asset.
 */
class CloudinaryUrl
{
    public static function image(?string $publicId): ?string
    {
        return self::for($publicId, 'image');
    }

    public static function raw(?string $publicId): ?string
    {
        return self::for($publicId, 'raw');
    }

    /**
     * For attachments that can be either an image or a raw file (mixed
     * multi-file uploads) -- mirrors CloudinaryManager::uploadAuto()'s same
     * mime-based choice, so the delivery resource_type always matches
     * whichever one the file was actually uploaded as.
     */
    public static function auto(?string $publicId, ?string $mimeType): ?string
    {
        return self::for($publicId, CloudinaryResourceType::fromMime($mimeType));
    }

    /**
     * Same as auto(), for the couple of spots that only ever recorded a
     * file extension (e.g. "pdf"), not a mime type.
     */
    public static function autoByExtension(?string $publicId, ?string $extension): ?string
    {
        return self::for($publicId, CloudinaryResourceType::fromExtension($extension));
    }

    public static function for(?string $publicId, string $resourceType = 'image'): ?string
    {
        if (! $publicId) {
            return null;
        }

        $cloudName = config('services.cloudinary.cloud_name');

        return "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$publicId}";
    }
}
