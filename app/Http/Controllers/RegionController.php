<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Support\IndonesianRegions;
use Illuminate\Http\JsonResponse;

/**
 * Indonesian regions for cascading address pickers. Any signed-in user;
 * responses are cacheable for a day.
 */
class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        return $this->respond(IndonesianRegions::list('province'));
    }

    public function regencies(string $code): JsonResponse
    {
        return $this->respond(IndonesianRegions::list('regency', $code));
    }

    public function districts(string $code): JsonResponse
    {
        return $this->respond(IndonesianRegions::list('district', $code));
    }

    public function villages(string $code): JsonResponse
    {
        return $this->respond(IndonesianRegions::list('village', $code));
    }

    /**
     * Browsers may keep a list for a day, but never an empty one (the
     * regions may simply not be imported yet).
     */
    private function respond(array $items): JsonResponse
    {
        return ResponseHelper::jsonResponse(true, 'Regions Retrieved Successfully', $items, 200)
            ->header('Cache-Control', $items === [] ? 'no-store' : 'private, max-age='.IndonesianRegions::CACHE_TTL);
    }
}
