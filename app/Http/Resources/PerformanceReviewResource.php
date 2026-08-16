<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->user?->name,
                'job_title' => $this->employee?->jobInformation?->job_title,
            ]),
            'reviewer' => $this->when($this->relationLoaded('reviewer'), fn () => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
            ]),
            'period' => $this->period,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'overall_rating' => (float) $this->overall_rating,
            'category_scores' => $this->category_scores,
            'strengths' => $this->strengths,
            'areas_for_improvement' => $this->areas_for_improvement,
            'goals_next_period' => $this->goals_next_period,
            'status' => $this->status,
            'employee_acknowledged_at' => $this->employee_acknowledged_at,
            'created_at' => $this->created_at,
        ];
    }
}
