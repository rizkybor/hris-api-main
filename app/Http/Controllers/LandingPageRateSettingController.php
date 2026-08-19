<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LandingPageRateSettingRequest;
use App\Http\Resources\LandingPageRateSettingResource;
use App\Models\LandingPageRateSetting;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LandingPageRateSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-calculator-menu']), only: ['show', 'update']),
        ];
    }

    /**
     * There is only ever one Landing Page rate setting row (company-wide
     * package pricing). Seeded with the defaults given in the spec on first
     * read if it doesn't exist yet.
     */
    private function current(): LandingPageRateSetting
    {
        return LandingPageRateSetting::first() ?? LandingPageRateSetting::create([
            'server_dedicated_price' => 2000000,
            'server_shared_price' => 1000000,
            'design_dedicated_price' => 4000000,
            'design_template_price' => 1500000,
            'default_rate_developer' => 100000,
            'margin_percent' => 30,
        ]);
    }

    public function show()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'Landing Page Rate Setting Retrieved Successfully', new LandingPageRateSettingResource($this->current()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(LandingPageRateSettingRequest $request)
    {
        $validated = $request->validated();

        try {
            $setting = $this->current();
            $setting->update($validated);

            return ResponseHelper::jsonResponse(true, 'Landing Page Rate Setting Updated Successfully', new LandingPageRateSettingResource($setting->fresh()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
