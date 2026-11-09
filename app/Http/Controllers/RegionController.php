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

    private function respond(array $items): JsonResponse
    {
        return ResponseHelper::jsonResponse(true, 'Regions Retrieved Successfully', $items, 200)
            ->header('Cache-Control', 'private, max-age='.IndonesianRegions::CACHE_TTL);
    }
}
