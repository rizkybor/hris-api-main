<?php

namespace App\Services\ProjectCalculator;

use App\Models\LandingPageRateSetting;

class ProjectCalculatorService
{
    /**
     * Landing Page's Development rate and Margin Jual are not
     * company-wide-configurable (only Server/Design pricing is, via
     * LandingPageRateSetting) -- these are just the pre-filled defaults for
     * a new calculation, freely overridable per calculation in the
     * Create/Edit form.
     */
    private const DEFAULT_RATE_DEVELOPER = 100000;

    private const DEFAULT_MARGIN_PERCENT = 30;

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

        return [
            'items' => $computedItems,
            'subtotal' => $subtotal,
            'buffer_total' => $bufferTotal,
            'pm_overhead_total' => $pmOverheadTotal,
            'infra_setup_cost' => $scenario === 'build' ? $infraCost : null,
            'grand_total' => $grandTotal,
            'total_hours' => $totalHours,
            'estimated_duration_weeks' => $estimatedDurationWeeks,
            ...$this->applyTax($grandTotal, $includePpn, $ppnPercent, $includePph, $pphPercent),
        ];
    }

    /**
     * Landing Page is a fixed-shape package (not a repeatable items list
     * like Feature/Build): one Server choice, one Design choice, and a
     * Development cost (hours x rate x developer count), marked up by its
     * own sell margin -- kept as a separate method rather than folded into
     * calculate() since none of that method's per-item/hourly-rate math
     * applies here.
     *
     * @param  array  $data  'server_type' (dedicated|shared), 'design_type'
     *   (dedicated|template), 'estimated_hours', optional 'rate_developer'
     *   (falls back to DEFAULT_RATE_DEVELOPER), optional 'developer_count'
     *   (falls back to 1), optional 'margin_percent' (falls back to
     *   DEFAULT_MARGIN_PERCENT)
     */
    public function calculateLandingPage(
        array $data,
        LandingPageRateSetting $settings,
        bool $includePpn,
        float $ppnPercent,
        bool $includePph = false,
        float $pphPercent = 0,
    ): array {
        $serverType = $data['server_type'];
        $serverCost = $serverType === 'dedicated'
            ? (float) $settings->server_dedicated_price
            : (float) $settings->server_shared_price;

        $designType = $data['design_type'];
        $designCost = $designType === 'dedicated'
            ? (float) $settings->design_dedicated_price
            : (float) $settings->design_template_price;

        $estimatedHours = (float) ($data['estimated_hours'] ?? 0);
        $rateDeveloper = (float) ($data['rate_developer'] ?? self::DEFAULT_RATE_DEVELOPER);
        $developerCount = max(1, (int) ($data['developer_count'] ?? 1));
        $developmentCost = round($estimatedHours * $rateDeveloper * $developerCount, 2);

        // Optional extra line items (e.g. domain, third-party integration,
        // extra revisions) -- free-form Description x Amount x Price, added
        // to the cost base the same way Server/Design/Development are, so
        // the margin below applies to them too.
        $additionalItems = array_map(function ($item) {
            $amount = (float) ($item['amount'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            return [
                'description' => $item['description'] ?? '',
                'amount' => $amount,
                'price' => $price,
                'subtotal' => round($amount * $price, 2),
            ];
        }, $data['additional_items'] ?? []);
        $additionalItemsTotal = round(array_sum(array_column($additionalItems, 'subtotal')), 2);

        $subtotal = round($serverCost + $designCost + $developmentCost + $additionalItemsTotal, 2);

        $marginPercent = (float) ($data['margin_percent'] ?? self::DEFAULT_MARGIN_PERCENT);
        $marginTotal = round($subtotal * ($marginPercent / 100), 2);
        $grandTotal = round($subtotal + $marginTotal, 2);

        // Simple capacity estimate: total dev-hours split evenly across the
        // developer count, at a standard 40-hour work week -- there's no
        // team-wide productive-hours setting for this scenario to anchor to
        // (unlike Feature/Build, which derive it from the Rate Setup team).
        $estimatedDurationWeeks = $developerCount > 0 && $estimatedHours > 0
            ? (int) ceil($estimatedHours / $developerCount / 40)
            : null;

        return [
            'items' => [[
                'server_type' => $serverType,
                'server_cost' => $serverCost,
                'design_type' => $designType,
                'design_cost' => $designCost,
                'estimated_hours' => $estimatedHours,
                'rate_developer' => $rateDeveloper,
                'developer_count' => $developerCount,
                'development_cost' => $developmentCost,
                'additional_items' => $additionalItems,
                'additional_items_total' => $additionalItemsTotal,
            ]],
            'subtotal' => $subtotal,
            'buffer_total' => 0,
            'pm_overhead_total' => 0,
            'infra_setup_cost' => null,
            'margin_percent' => $marginPercent,
            'margin_total' => $marginTotal,
            'grand_total' => $grandTotal,
            'total_hours' => $estimatedHours,
            'estimated_duration_weeks' => $estimatedDurationWeeks,
            ...$this->applyTax($grandTotal, $includePpn, $ppnPercent, $includePph, $pphPercent),
        ];
    }

    /**
     * Shared by calculate() and calculateLandingPage() so both scenarios'
     * PPN/PPh math can never drift apart.
     */
    private function applyTax(float $grandTotal, bool $includePpn, float $ppnPercent, bool $includePph, float $pphPercent): array
    {
        $ppnAmount = $includePpn ? round($grandTotal * ($ppnPercent / 100), 2) : 0;
        $totalWithPpn = $includePpn ? round($grandTotal + $ppnAmount, 2) : null;

        // PPh's DPP (tax base) is the service fee itself (grand_total),
        // never the PPN on top of it -- PPN isn't the vendor's income, so
        // it isn't part of what PPh is withheld against.
        $pphAmount = $includePph ? round($grandTotal * ($pphPercent / 100), 2) : 0;
        $netReceived = $includePph ? round(($includePpn ? $totalWithPpn : $grandTotal) - $pphAmount, 2) : null;

        return [
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
