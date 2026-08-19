<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectRateSetting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project Calculator');
    }

    protected $fillable = [
        'selected_fixed_cost_ids',
        'selected_sdm_resource_ids',
        'selected_infrastructure_tool_ids',
        'margin_multiplier',
        'pm_overhead_percent',
        'default_infra_setup_cost',
    ];

    protected $casts = [
        'selected_fixed_cost_ids' => 'array',
        'selected_sdm_resource_ids' => 'array',
        'selected_infrastructure_tool_ids' => 'array',
        'margin_multiplier' => 'decimal:2',
        'pm_overhead_percent' => 'decimal:2',
        'default_infra_setup_cost' => 'decimal:2',
    ];

    public function selectedFixedCosts()
    {
        return FixedCost::whereIn('id', $this->selected_fixed_cost_ids ?? [])->get();
    }

    public function selectedSdmResources()
    {
        return SdmResource::with('field')->whereIn('id', $this->selected_sdm_resource_ids ?? [])->get();
    }

    public function selectedInfrastructureTools()
    {
        return InfrastructureTool::whereIn('id', $this->selected_infrastructure_tool_ids ?? [])->get();
    }

    /**
     * Total Biaya Operasional Tim / Bulan -- no longer typed in by hand, it's
     * the sum of whichever Fixed Cost (actual), SDM Resource (actual), and
     * Infrastructure Tool (monthly_fee) line items are selected.
     */
    public function getTeamMonthlyCostAttribute(): float
    {
        $fixedCostTotal = (float) $this->selectedFixedCosts()->sum('actual');
        $sdmTotal = (float) $this->selectedSdmResources()->sum('actual');
        $infraTotal = (float) $this->selectedInfrastructureTools()->sum('monthly_fee');

        return round($fixedCostTotal + $sdmTotal + $infraTotal, 2);
    }

    /**
     * Total Jam Produktif Tim / Bulan -- summed from the productive hours of
     * whichever SDM Resource team members are selected.
     */
    public function getTotalProductiveHoursPerMonthAttribute(): float
    {
        return round((float) $this->selectedSdmResources()->sum('productive_hours_per_month'), 2);
    }

    /**
     * Jumlah Orang di Tim -- the count of selected SDM Resource team members.
     */
    public function getTeamSizeAttribute(): int
    {
        return count($this->selected_sdm_resource_ids ?? []);
    }

    public function getRateCostPerHourAttribute(): float
    {
        $hours = $this->total_productive_hours_per_month;

        return $hours > 0 ? round($this->team_monthly_cost / $hours, 2) : 0;
    }

    public function getRateSellPerHourAttribute(): float
    {
        return round($this->rate_cost_per_hour * (float) $this->margin_multiplier, 2);
    }

    /**
     * Full breakdown of this rate setting, with the selected Fixed Cost /
     * SDM Resource / Infrastructure Tool ids resolved to their actual
     * name+amount at this moment -- meant to be frozen onto a
     * ProjectCalculation at save time, since those source records (and this
     * setting itself) can change or be deleted later.
     */
    public function toSnapshotArray(): array
    {
        return [
            'margin_multiplier' => (float) $this->margin_multiplier,
            'pm_overhead_percent' => (float) $this->pm_overhead_percent,
            'default_infra_setup_cost' => (float) $this->default_infra_setup_cost,
            'team_size' => $this->team_size,
            'team_monthly_cost' => $this->team_monthly_cost,
            'total_productive_hours_per_month' => $this->total_productive_hours_per_month,
            'rate_cost_per_hour' => $this->rate_cost_per_hour,
            'rate_sell_per_hour' => $this->rate_sell_per_hour,
            'fixed_costs' => $this->selectedFixedCosts()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->financial_items,
                'actual' => (float) $item->actual,
            ])->values()->all(),
            'sdm_resources' => $this->selectedSdmResources()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->sdm_component,
                'field' => $item->field?->name,
                'actual' => (float) $item->actual,
                'productive_hours_per_month' => (float) $item->productive_hours_per_month,
            ])->values()->all(),
            'infrastructure_tools' => $this->selectedInfrastructureTools()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->tech_stack_component,
                'vendor' => $item->vendor,
                'monthly_fee' => (float) $item->monthly_fee,
            ])->values()->all(),
        ];
    }
}
