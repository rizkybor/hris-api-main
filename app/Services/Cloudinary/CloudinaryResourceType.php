<?php

namespace App\Services\Cloudinary;

/**
 * The one place that decides "image" vs "raw" from a mime type, so
 * uploadAuto(), CloudinaryUrl::auto(), and every delete() call for a mixed
 * (image-or-document) attachment field all agree on the same resource_type
 * for the same file.
 */
class CloudinaryResourceType
{
    public static function fromMime(?string $mimeType): string
    {
        return str_starts_with($mimeType ?? '', 'image/') ? 'image' : 'raw';
    }

    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];

    /**
     * For the couple of spots that only ever recorded a file extension
     * (e.g. "pdf", "png"), not a mime type.
     */
    public static function fromExtension(?string $extension): string
    {
        return in_array(strtolower($extension ?? ''), self::IMAGE_EXTENSIONS, true) ? 'image' : 'raw';
    }
}
