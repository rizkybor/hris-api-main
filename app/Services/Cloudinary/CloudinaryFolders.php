<?php

namespace App\Services\Cloudinary;

/**
 * Central place for this app's two Cloudinary folders and its file-naming
 * convention, so every controller/repository that uploads something builds
 * its folder/filename the same way instead of hand-rolling its own string.
 *
 * - Project-related files (cover photo, task images, project documents) all
 *   live flat under project-files/, with every filename prefixed by the
 *   project's initials (e.g. "EPR-3-photo-<uniqid>") so files from the same
 *   project sort and scan together in the Cloudinary UI. The numeric
 *   project id is appended to the initials because initials alone collide
 *   easily ("Project Alpha" and "Property Analytics" both -> "PA").
 * - Everything else lives under company-files/<category>/, one subfolder
 *   per file type.
 */
class CloudinaryFolders
{
    private const PROJECT_FILES = CloudinaryManager::BASE_FOLDER.'/project-files';

    private const COMPANY_FILES = CloudinaryManager::BASE_FOLDER.'/company-files';

    public static function projectFiles(): string
    {
        return self::PROJECT_FILES;
    }

    public static function companyFiles(string $category): string
    {
        return self::COMPANY_FILES.'/'.trim($category, '/');
    }

    /**
     * "EPR-3" for a project named "E-commerce Platform Redesign" with id 3.
     */
    public static function projectPrefix(string $projectName, int|string $projectId): string
    {
        return self::initials($projectName).'-'.$projectId;
    }

    /**
     * A stable, unique-enough filename: a caller-provided prefix plus a
     * short random suffix, so re-uploading the same logical file (e.g.
     * replacing a photo) never collides with the old one still being
     * deleted, and two uploads in the same request never collide either.
     */
    public static function filename(string $prefix): string
    {
        return $prefix.'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private static function initials(string $name): string
    {
        $words = array_filter(preg_split('/\s+/', trim($name)) ?: []);
        $initials = implode('', array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), $words));

        return $initials !== '' ? $initials : 'PRJ';
    }
}
