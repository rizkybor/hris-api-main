<?php

namespace App\Services;

use App\Models\ProjectRateSetting;
use App\Models\Screening;
use App\Models\ServiceItem;
use App\Support\ScreeningDefaults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a screening's answers into recommended catalog items, a system type,
 * a complexity size and notes for the team, using the effects attached to
 * each answer option (see ScreeningDefaults). Prices come from the catalog:
 * the Rate Card "start from" price, or hours x the Project Calculator sell
 * rate for hourly items. Totals and budget fit are computed from the items
 * as they stand, so manual edits count.
 */
class ScreeningRecommender
{
    public const COMPLEXITY = [[10, 'S'], [25, 'M'], [45, 'L'], [PHP_INT_MAX, 'XL']];

    public function isVisible(?array $showIf, array $answers): bool
    {
        if (! $showIf) {
            return true;
        }
        $value = $answers[$showIf['question']] ?? null;
        $values = is_array($value) ? $value : ($value === null ? [] : [$value]);

        if (isset($showIf['includes'])) {
            return (bool) array_intersect((array) $showIf['includes'], $values);
        }
        if (array_key_exists('equals', $showIf)) {
            return in_array($showIf['equals'], $values, true);
        }

        return true;
    }

    /** Sections and questions the person actually sees for these answers. */
    public function visibleQuestions(array $definition, array $answers): Collection
    {
        return collect($definition['sections'] ?? [])
            ->filter(fn ($s) => $this->isVisible($s['show_if'] ?? null, $answers))
            ->flatMap(fn ($s) => collect($s['questions'] ?? [])
                ->filter(fn ($q) => $this->isVisible($q['show_if'] ?? null, $answers))
                ->map(fn ($q) => $q + ['section' => $s['key']]))
            ->values();
    }

    /** The option rows picked for a question. */
    public function selectedOptions(array $question, mixed $value): array
    {
        if (! isset($question['options']) || $value === null || $value === '' || $value === []) {
            return [];
        }
        $picked = is_array($value) ? $value : [$value];

        return array_values(array_filter($question['options'], fn ($o) => in_array($o['value'], $picked, true)));
    }

    public function analyse(array $definition, array $answers): array
    {
        $catalog = ServiceItem::where('is_active', true)->get()->keyBy('code');
        $rate = (float) (ProjectRateSetting::first()?->rate_sell_per_hour ?? 0);

        $lines = [];
        $replaces = [];
        $tags = [];
        $flags = [];
        $points = 0;
        $budget = null;

        foreach ($this->visibleQuestions($definition, $answers) as $q) {
            foreach ($this->selectedOptions($q, $answers[$q['key']] ?? null) as $opt) {
                $reason = ($q['label']['id'] ?? $q['key']).': '.($opt['label']['id'] ?? $opt['value']);
                $points += (int) ($opt['points'] ?? 0);
                array_push($tags, ...($opt['tags'] ?? []));
                foreach ($opt['flags'] ?? [] as $f) {
                    $flags[] = $f['id'] ?? (string) $f;
                }
                foreach ($opt['replace'] ?? [] as $r) {
                    $replaces[] = $r;
                }
                if ($q['key'] === 'budget' && (isset($opt['min']) || isset($opt['max']))) {
                    $budget = ['label' => $opt['label']['id'] ?? $opt['value'], 'min' => $opt['min'] ?? null, 'max' => $opt['max'] ?? null];
                }
                foreach ($opt['add'] ?? [] as $add) {
                    $item = $catalog->get($add['item']);
                    if (! $item) {
                        continue;
                    }
                    $name = $add['label']['id'] ?? $item->name;
                    $key = $item->code.'|'.$name;
                    $lines[$key] ??= ['item' => $item, 'name' => $name, 'hours' => 0.0, 'qty' => (float) ($add['qty'] ?? 1), 'reasons' => []];
                    $lines[$key]['hours'] += (float) ($add['hours'] ?? 0);
                    $lines[$key]['reasons'][] = $reason;
                }
            }
        }

        // e.g. a multi-language company profile needs JCD Compro, not Lite.
        foreach ($replaces as $r) {
            foreach ($lines as $key => $line) {
                if ($line['item']->code !== $r['from'] || ! ($to = $catalog->get($r['to']))) {
                    continue;
                }
                unset($lines[$key]);
                $newKey = $to->code.'|'.$to->name;
                $lines[$newKey] ??= ['item' => $to, 'name' => $to->name, 'hours' => 0.0, 'qty' => 1.0, 'reasons' => []];
                $lines[$newKey]['reasons'] = array_merge($lines[$newKey]['reasons'], $line['reasons'], ['Diupgrade dari '.$line['item']->name]);
            }
        }

        $items = collect($lines)->values()->map(function ($l, $i) use ($rate) {
            $item = $l['item'];
            $hours = $item->pricing_mode === 'hourly' ? ($l['hours'] ?: (float) $item->default_hours) : null;
            $price = match (true) {
                $item->availability !== 'available' => null,
                $item->pricing_mode === 'rate_card' => $item->price_from !== null ? (float) $item->price_from : null,
                $item->pricing_mode === 'hourly' => $rate > 0 && $hours ? round($hours * $rate, -3) : null,
                default => null,
            };

            return [
                'service_item_id' => $item->id,
                'name' => $l['name'],
                'category' => $item->category,
                'qty' => $l['qty'],
                'hours' => $hours,
                'unit_price' => $price,
                'price_unit' => $item->price_unit,
                'duration_text' => $item->duration_text,
                'reason' => implode('; ', array_unique($l['reasons'])),
                'is_selected' => $item->availability === 'available',
                'source' => 'auto',
                'sort_order' => $this->sortKey($item, $i),
            ];
        })->sortBy('sort_order')->values();

        $order = array_keys(ScreeningDefaults::SYSTEM_TYPES);
        $tags = array_values(array_intersect($order, array_unique($tags)));

        return [
            'items' => $items->all(),
            'result' => [
                'system_types' => array_map(fn ($t) => ScreeningDefaults::SYSTEM_TYPES[$t], $tags),
                'points' => $points,
                'complexity' => collect(self::COMPLEXITY)->first(fn ($c) => $points < $c[0])[1],
                'flags' => array_values(array_unique($flags)),
                'budget' => $budget,
                'hourly_rate' => $rate,
                'analysed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /** Packages first (by catalog order), then the per-hour add-ons. */
    private function sortKey(ServiceItem $item, int $i): int
    {
        return $item->sort_order * 100 + $i;
    }

    /** Re-run the analysis: replaces the auto items, keeps manual ones. */
    public function apply(Screening $screening): void
    {
        $analysis = $this->analyse($screening->template_snapshot ?? [], $screening->answers ?? []);

        DB::transaction(function () use ($screening, $analysis) {
            $screening->items()->where('source', 'auto')->delete();
            foreach ($analysis['items'] as $row) {
                $screening->items()->create($row);
            }
            // Items the team added by hand stay, listed after the recommendations.
            $after = (int) collect($analysis['items'])->max('sort_order');
            $screening->items()->where('source', 'manual')->orderBy('sort_order')->get()
                ->each(fn ($item, $i) => $item->update(['sort_order' => $after + ($i + 1) * 10]));
            $screening->update(['result' => $analysis['result']]);
        });
    }

    /** Totals and budget fit from the items as they stand now. */
    public function estimate(Screening $screening): array
    {
        $items = $screening->relationLoaded('items') ? $screening->items : $screening->items()->get();
        $selected = $items->where('is_selected', true);

        $sum = fn ($unit) => round($selected->where('price_unit', $unit)->sum(fn ($i) => $i->lineTotal() ?? 0), 2);
        $once = $sum('once');
        $monthly = $sum('month');
        $yearly = $sum('year');
        $byQuote = $selected->filter(fn ($i) => $i->unit_price === null)->pluck('name')->values();
        $hours = round($selected->sum(fn ($i) => (float) $i->hours * (float) $i->qty), 1);

        $budget = $screening->result['budget'] ?? null;
        $fit = 'unknown';
        $deferrable = [];
        if ($budget && ($budget['max'] ?? null) !== null) {
            $fit = $once <= $budget['max'] ? 'fit' : 'over';
            if ($fit === 'over') {
                // Biggest add-ons first until the rest fits the budget.
                $left = $once;
                foreach ($selected->where('price_unit', 'once')->filter(fn ($i) => $i->unit_price !== null && $i->serviceItem?->pricing_mode !== 'rate_card')->sortByDesc(fn ($i) => $i->lineTotal()) as $i) {
                    if ($left <= $budget['max']) {
                        break;
                    }
                    $deferrable[] = $i->name;
                    $left -= $i->lineTotal();
                }
            }
        } elseif ($budget && ($budget['min'] ?? null) !== null) {
            $fit = 'fit';
        }

        return [
            'one_time' => $once,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'by_quote' => $byQuote,
            'hourly_items_hours' => $hours,
            // Packages run on their Rate Card duration; add-on hours at about
            // 30 productive hours a week on top.
            'extra_weeks' => $hours > 0 ? (int) ceil($hours / 30) : 0,
            'longest_duration' => $selected->filter(fn ($i) => $i->duration_text && $i->serviceItem?->pricing_mode === 'rate_card' && $i->price_unit === 'once')
                ->sortByDesc(fn ($i) => (float) $i->unit_price)->first()?->duration_text,
            'budget_fit' => $fit,
            'deferrable' => $deferrable,
        ];
    }
}
