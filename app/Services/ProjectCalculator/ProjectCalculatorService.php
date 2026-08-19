<?php

namespace App\Services\ProjectCalculator;

class ProjectCalculatorService
{
    /**
     * Recompute every derived number for a calculation server-side, rather
     * than trusting whatever totals the client sent. Mirrors the formulas
     * from the source spreadsheet (Rate Setup / Scenario A / Scenario B),
     * plus two additive, non-breaking refinements: an estimated delivery
     * duration (sanity-checks the quote against real team capacity) and an
     * optional PPN (Indonesian VAT) line, since a real client-facing quote
     * needs both.
     *
     * @param  string  $scenario  'feature' or 'build'
     * @param  array  $items  raw item rows from the request
     * @param  float  $rateSellPerHour
     * @param  float|null  $pmOverheadPercent  build scenario only
     * @param  float|null  $infraSetupCost  build scenario only
     * @param  float  $productiveHoursPerMonth  team_size * productive_hours_per_person, for duration estimate
     * @param  bool  $includePpn
     * @param  float  $ppnPercent
     * @param  bool  $includePph  PPh is withheld by the client from the
     *   payment, not added like PPN -- it reduces what the vendor actually
     *   receives in cash, it does not change what the client is invoiced.
     * @param  float  $pphPercent
     * @return array
     */
    public function calculate(
        string $scenario,
        array $items,
        float $rateSellPerHour,
        ?float $pmOverheadPercent,
        ?float $infraSetupCost,
        float $productiveHoursPerMonth,
        bool $includePpn,
        float $ppnPercent,
        bool $includePph = false,
        float $pphPercent = 0,
    ): array {
        $computedItems = $scenario === 'feature'
            ? $this->calculateFeatureItems($items, $rateSellPerHour)
            : $this->calculateModuleItems($items, $rateSellPerHour);

        $subtotal = round(array_sum(array_column($computedItems, 'subtotal')), 2);
        $bufferTotal = round(array_sum(array_column($computedItems, 'buffer_amount')), 2);
        $itemsTotal = round(array_sum(array_column($computedItems, 'final_price')), 2);

        $pmOverheadTotal = 0;
        $infraCost = 0;
        $grandTotal = $itemsTotal;

        if ($scenario === 'build') {
            $pmOverheadTotal = round($itemsTotal * (($pmOverheadPercent ?? 0) / 100), 2);
            $infraCost = round($infraSetupCost ?? 0, 2);
            $grandTotal = round($itemsTotal + $pmOverheadTotal + $infraCost, 2);
        }

        $totalHours = round(array_sum(array_column($computedItems, 'total_hours_used')), 2);
        $estimatedDurationWeeks = $productiveHoursPerMonth > 0
            ? (int) ceil($totalHours / ($productiveHoursPerMonth / 4.33))
            : null;

        $ppnAmount = $includePpn ? round($grandTotal * ($ppnPercent / 100), 2) : 0;
        $totalWithPpn = $includePpn ? round($grandTotal + $ppnAmount, 2) : null;

        // PPh's DPP (tax base) is the service fee itself (grand_total),
        // never the PPN on top of it -- PPN isn't the vendor's income, so
        // it isn't part of what PPh is withheld against.
        $pphAmount = $includePph ? round($grandTotal * ($pphPercent / 100), 2) : 0;
        $netReceived = $includePph ? round(($includePpn ? $totalWithPpn : $grandTotal) - $pphAmount, 2) : null;

        return [
            'items' => $computedItems,
            'subtotal' => $subtotal,
            'buffer_total' => $bufferTotal,
            'pm_overhead_total' => $pmOverheadTotal,
            'infra_setup_cost' => $scenario === 'build' ? $infraCost : null,
            'grand_total' => $grandTotal,
            'total_hours' => $totalHours,
            'estimated_duration_weeks' => $estimatedDurationWeeks,
            'ppn_amount' => $ppnAmount,
            'total_with_ppn' => $totalWithPpn,
            'pph_amount' => $pphAmount,
            'net_received' => $netReceived,
        ];
    }

    private function calculateFeatureItems(array $items, float $rateSellPerHour): array
    {
        return array_map(function ($item) use ($rateSellPerHour) {
            $baseHours = (float) ($item['analysis_hours'] ?? 0)
                + (float) ($item['dev_hours'] ?? 0)
                + (float) ($item['testing_hours'] ?? 0)
                + (float) ($item['deploy_hours'] ?? 0);

            $complexityFactor = (float) ($item['complexity_factor'] ?? 1);
            $totalHoursUsed = round($baseHours * $complexityFactor, 2);
            $subtotal = round($totalHoursUsed * $rateSellPerHour, 2);
            $bufferPercent = (float) ($item['buffer_percent'] ?? 0);
            $bufferAmount = round($subtotal * ($bufferPercent / 100), 2);
            $finalPrice = round($subtotal + $bufferAmount, 2);

            return [
                'name' => $item['name'] ?? '',
                'analysis_hours' => (float) ($item['analysis_hours'] ?? 0),
                'dev_hours' => (float) ($item['dev_hours'] ?? 0),
                'testing_hours' => (float) ($item['testing_hours'] ?? 0),
                'deploy_hours' => (float) ($item['deploy_hours'] ?? 0),
                'base_hours' => round($baseHours, 2),
                'complexity_factor' => $complexityFactor,
                'total_hours_used' => $totalHoursUsed,
                'subtotal' => $subtotal,
                'buffer_percent' => $bufferPercent,
                'buffer_amount' => $bufferAmount,
                'final_price' => $finalPrice,
            ];
        }, $items);
    }

    private function calculateModuleItems(array $items, float $rateSellPerHour): array
    {
        return array_map(function ($item) use ($rateSellPerHour) {
            $estimatedHours = (float) ($item['estimated_hours'] ?? 0);
            $complexityFactor = (float) ($item['complexity_factor'] ?? 1);
            $totalHoursUsed = round($estimatedHours * $complexityFactor, 2);
            $subtotal = round($totalHoursUsed * $rateSellPerHour, 2);
            $bufferPercent = (float) ($item['buffer_percent'] ?? 0);
            $bufferAmount = round($subtotal * ($bufferPercent / 100), 2);
            $finalPrice = round($subtotal + $bufferAmount, 2);

            return [
                'name' => $item['name'] ?? '',
                'estimated_hours' => $estimatedHours,
                'complexity_factor' => $complexityFactor,
                'total_hours_used' => $totalHoursUsed,
                'subtotal' => $subtotal,
                'buffer_percent' => $bufferPercent,
                'buffer_amount' => $bufferAmount,
                'final_price' => $finalPrice,
            ];
        }, $items);
    }
}
