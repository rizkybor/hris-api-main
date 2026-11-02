<?php

namespace App\Http\Resources;

use App\Services\ScreeningForm;
use App\Services\ScreeningRecommender;
use App\Services\ScreeningService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningResource extends JsonResource
{
    /** List rows skip the heavier detail (answers, items, estimate). */
    public bool $detail = false;

    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'code' => $this->code,
            'source' => $this->source,
            'status' => $this->status,
            'lang' => $this->lang,
            'contact_name' => $this->contact_name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'client' => $this->whenLoaded('client', fn () => $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null),
            'needs' => $this->needsLabels(),
            'system_types' => $this->result['system_types'] ?? [],
            'complexity' => $this->result['complexity'] ?? null,
            'submitted_at' => $this->submitted_at,
            'created_at' => $this->created_at,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'link_open' => $this->linkIsOpen(),
            'invite_url' => $this->source === 'invite' && $this->linkIsOpen() ? app(ScreeningService::class)->inviteUrl($this->resource) : null,
            'token_expires_at' => $this->token_expires_at,
        ];

        if (! $this->detail) {
            return $base;
        }

        $this->resource->loadMissing('items.serviceItem');

        return $base + [
            'internal_notes' => $this->internal_notes,
            // The questionnaire (Indonesian) for filling it in from HRIS.
            'form' => app(ScreeningForm::class)->publicDefinition($this->template_snapshot ?? [], 'id'),
            'answers' => (object) ($this->answers ?? []),
            'readable_answers' => app(ScreeningForm::class)->readable($this->resource),
            'result' => $this->result,
            'estimate' => app(ScreeningRecommender::class)->estimate($this->resource),
            'items' => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'service_item_id' => $i->service_item_id,
                'code' => $i->serviceItem?->code,
                'pricing_mode' => $i->serviceItem?->pricing_mode,
                'availability' => $i->serviceItem?->availability,
                'name' => $i->name,
                'category' => $i->category,
                'qty' => (float) $i->qty,
                'hours' => $i->hours !== null ? (float) $i->hours : null,
                'unit_price' => $i->unit_price !== null ? (float) $i->unit_price : null,
                'price_unit' => $i->price_unit,
                'line_total' => $i->lineTotal(),
                'duration_text' => $i->duration_text,
                'reason' => $i->reason,
                'is_selected' => $i->is_selected,
                'source' => $i->source,
            ])->values(),
        ];
    }

    private function needsLabels(): array
    {
        $question = collect($this->template_snapshot['sections'] ?? [])->flatMap(fn ($s) => $s['questions'] ?? [])->firstWhere('key', 'needs');

        return collect($question['options'] ?? [])
            ->filter(fn ($o) => in_array($o['value'], (array) ($this->answers['needs'] ?? []), true))
            ->map(fn ($o) => $o['label']['id'] ?? $o['value'])
            ->values()->all();
    }
}
