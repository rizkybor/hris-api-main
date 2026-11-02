<?php

namespace App\Services;

use App\Models\Screening;

/**
 * The questionnaire as the client sees it (no prices, rules or items) and
 * clean-up/validation of submitted answers against it.
 */
class ScreeningForm
{
    public const TEXT_LIMITS = ['text' => 255, 'textarea' => 3000];

    public function __construct(private ScreeningRecommender $recommender) {}

    /** Public payload for one language: labels only, effects stripped. */
    public function publicDefinition(array $definition, string $lang): array
    {
        $tr = fn ($v) => is_array($v) ? ($v[$lang] ?? $v['id'] ?? '') : (string) $v;

        return [
            'sections' => collect($definition['sections'] ?? [])->map(fn ($s) => [
                'key' => $s['key'],
                'title' => $tr($s['title'] ?? $s['key']),
                'description' => isset($s['description']) ? $tr($s['description']) : null,
                'show_if' => $s['show_if'] ?? null,
                'questions' => collect($s['questions'] ?? [])->map(fn ($q) => [
                    'key' => $q['key'],
                    'type' => $q['type'],
                    'required' => (bool) ($q['required'] ?? false),
                    'label' => $tr($q['label'] ?? $q['key']),
                    'help' => isset($q['help']) ? $tr($q['help']) : null,
                    'show_if' => $q['show_if'] ?? null,
                    'options' => isset($q['options'])
                        ? collect($q['options'])->map(fn ($o) => ['value' => $o['value'], 'label' => $tr($o['label'] ?? $o['value'])])->values()->all()
                        : null,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** Keep only known questions with well-formed values. */
    public function clean(array $definition, array $input): array
    {
        $questions = collect($definition['sections'] ?? [])->flatMap(fn ($s) => $s['questions'] ?? [])->keyBy('key');
        $out = [];

        foreach ($input as $key => $value) {
            $q = $questions->get($key);
            if (! $q || $value === null) {
                continue;
            }
            $allowed = collect($q['options'] ?? [])->pluck('value')->all();

            $clean = match ($q['type']) {
                'single', 'boolean' => is_scalar($value) && in_array((string) $value, $allowed, true) ? (string) $value : null,
                'multi' => is_array($value) ? array_values(array_unique(array_filter(array_map('strval', array_filter($value, 'is_scalar')), fn ($v) => in_array($v, $allowed, true)))) : null,
                'number' => is_numeric($value) ? (float) $value : null,
                'text', 'textarea' => is_scalar($value) ? mb_substr(trim((string) $value), 0, self::TEXT_LIMITS[$q['type']]) : null,
                default => null,
            };

            if ($clean !== null && $clean !== '' && $clean !== []) {
                $out[$key] = $clean;
            }
        }

        return $out;
    }

    /** Required questions left empty among the ones visible for these answers. */
    public function missing(array $definition, array $answers, string $lang = 'id'): array
    {
        return $this->recommender->visibleQuestions($definition, $answers)
            ->filter(fn ($q) => ($q['required'] ?? false) && ! array_key_exists($q['key'], $answers))
            ->mapWithKeys(fn ($q) => [$q['key'] => $q['label'][$lang] ?? $q['label']['id'] ?? $q['key']])
            ->all();
    }

    /** Answers with their question/option labels, section by section (for display/PDF). */
    public function readable(Screening $screening, string $lang = 'id'): array
    {
        $definition = $screening->template_snapshot ?? [];
        $answers = $screening->answers ?? [];
        $tr = fn ($v) => is_array($v) ? ($v[$lang] ?? $v['id'] ?? '') : (string) $v;

        return collect($definition['sections'] ?? [])
            ->filter(fn ($s) => $this->recommender->isVisible($s['show_if'] ?? null, $answers))
            ->map(fn ($s) => [
                'key' => $s['key'],
                'title' => $tr($s['title'] ?? $s['key']),
                'answers' => collect($s['questions'] ?? [])
                    ->filter(fn ($q) => $this->recommender->isVisible($q['show_if'] ?? null, $answers) && array_key_exists($q['key'], $answers))
                    ->map(function ($q) use ($answers, $tr) {
                        $value = $answers[$q['key']];
                        $text = isset($q['options'])
                            ? collect($this->recommender->selectedOptions($q, $value))->map(fn ($o) => $tr($o['label']))->implode(', ')
                            : (string) $value;

                        return ['key' => $q['key'], 'question' => $tr($q['label']), 'answer' => $text, 'type' => $q['type']];
                    })->values()->all(),
            ])
            ->filter(fn ($s) => $s['answers'])
            ->values()->all();
    }
}
