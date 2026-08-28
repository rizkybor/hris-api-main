<?php

namespace App\Services;

use App\Models\CompanyAsset;
use Carbon\Carbon;

/**
 * Builds company asset codes in the format:
 *   JCD-{CATEGORY}-{YY}-{SEQUENCE}
 *   e.g. JCD-IT-HW-26-0001
 *
 * The 4-digit sequence is scoped per (category, purchase year) so each
 * category keeps its own running series within a year, mirroring
 * EmployeeCodeGenerator's approach: it derives the next number straight
 * from the highest existing asset_code matching the prefix, rather than a
 * separate counter table. This matters here specifically because the code
 * is user-editable after being suggested -- a separate counter could drift
 * from what's actually saved once someone edits or skips a suggestion.
 */
class AssetCodeGenerator
{
    public function generate(string $categoryCode, ?string $purchaseDate = null): string
    {
        $year = $purchaseDate ? Carbon::parse($purchaseDate)->format('y') : now()->format('y');
        $prefix = "JCD-{$categoryCode}-{$year}-";

        return $prefix.str_pad((string) $this->nextSequence($prefix), 4, '0', STR_PAD_LEFT);
    }

    private function nextSequence(string $prefix): int
    {
        $last = CompanyAsset::withTrashed()
            ->where('asset_code', 'like', $prefix.'%')
            ->orderByDesc('asset_code')
            ->first();

        if (! $last || ! preg_match('/(\d{4})$/', $last->asset_code, $matches)) {
            return 1;
        }

        return ((int) $matches[1]) + 1;
    }
}
