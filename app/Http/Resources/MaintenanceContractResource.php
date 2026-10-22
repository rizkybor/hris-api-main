<?php

namespace App\Http\Resources;

use App\Models\MaintenancePeriod;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contract with its current month and development balance. withDetail()
 * adds every month, the features and the balance ledger (detail page).
 */
class MaintenanceContractResource extends JsonResource
{
    private bool $detail = false;

    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $service = app(MaintenanceService::class);
        $current = $this->periods
            ->first(fn ($p) => $p->period_month->format('Y-m') === $service->currentMonth());

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'client' => $this->client ? ['id' => $this->client->id, 'name' => $this->client->name, 'email' => $this->client->email ?? null, 'pic_phone' => $this->client->pic_phone ?? null] : null,
            'project' => $this->project ? ['id' => $this->project->id, 'name' => $this->project->name] : null,
            'monthly_quota_hours' => $this->monthly_quota_hours,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'leftover_policy' => $this->leftover_policy,
            'bank_cap_hours' => $this->bank_cap_hours,
            'bank_expiry_months' => $this->bank_expiry_months,
            'overage_rate' => $this->overage_rate,
            'notes' => $this->notes,
            'current_period' => $current ? self::period($current) : null,
            'balance_hours' => $service->balance($this->resource),
            'expiring_soon' => $service->expiringSoon($this->resource),
            'open_features' => $this->features->whereIn('status', ['proposed', 'approved', 'in_progress'])->count(),
            'share_url' => $this->shareUrl(),
            'share_last_viewed_at' => $this->share_last_viewed_at,
            'created_at' => $this->created_at,
        ];

        if ($this->detail) {
            $data['periods'] = $this->periods->sortByDesc('period_month')->values()->map(fn ($p) => self::period($p));
            $data['features'] = $this->features->sortByDesc('id')->values()->map(fn ($f) => [
                ...self::feature($f),
                'logs' => $f->relationLoaded('logs')
                    ? $f->logs->sortByDesc('work_date')->values()->map(fn ($log) => \App\Http\Controllers\MaintenanceWorkController::logData($log))
                    : [],
            ]);
            $data['bank_entries'] = $this->bankEntries->sortByDesc('id')->values()->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'hours' => $e->hours,
                'remaining_hours' => $e->remaining_hours,
                'expires_at' => $e->expires_at?->toDateString(),
                'note' => $e->note,
                'created_by' => $e->creator?->name,
                'created_at' => $e->created_at,
            ]);
        }

        return $data;
    }

    public static function period(MaintenancePeriod $period): array
    {
        return [
            'id' => $period->id,
            'month' => $period->period_month->format('Y-m'),
            ...$period->totals(),
            'leftover_action' => $period->leftover_action,
            'is_closed' => $period->isClosed(),
            'closed_at' => $period->closed_at,
            'leftover_hours' => $period->leftover_hours,
            'leftover_applied' => $period->leftover_applied,
            'leftover_banked_hours' => $period->leftover_banked_hours,
        ];
    }

    public static function feature($feature): array
    {
        return [
            'id' => $feature->id,
            'title' => $feature->title,
            'description' => $feature->description,
            'estimated_hours' => $feature->estimated_hours,
            'status' => $feature->status,
            'reserved_hours' => $feature->reserved_hours,
            'used_hours' => $feature->usedHours(),
            'billable_hours' => $feature->billable_hours,
            'visible_to_client' => $feature->visible_to_client,
            'approved_at' => $feature->approved_at,
            'completed_at' => $feature->completed_at,
            'created_at' => $feature->created_at,
        ];
    }
}
