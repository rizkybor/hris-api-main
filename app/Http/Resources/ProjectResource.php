<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate progress based on tasks completion
        $progress = 0;
        if ($this->relationLoaded('tasks') && $this->tasks->count() > 0) {
            $completedTasks = $this->tasks->where('status', 'done')->count();
            $progress = round(($completedTasks / $this->tasks->count()) * 100);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'photo' => CloudinaryUrl::image($this->photo),
            'budget' => (float) (string) $this->budget,
            'progress' => $progress,
            'leader' => new EmployeeProfileResource($this->projectLeader),
            'team_assignment_mode' => $this->team_assignment_mode,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
            ])),
            'vendor_id' => $this->vendor_id,
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'pic_name' => $this->vendor->pic_name,
                'pic_phone' => $this->vendor->pic_phone,
                'email' => $this->vendor->email,
            ] : null),
            // Optional -- only present when a project has invoices billed
            // against it (see Invoice::project()).
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->date,
                'total' => (float) (string) $invoice->total,
                'status' => $invoice->status,
                'receipts' => $invoice->relationLoaded('receipts') ? $invoice->receipts->map(fn ($receipt) => [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'date' => $receipt->date,
                    'amount' => (float) (string) $receipt->amount,
                    'payment_status' => $receipt->payment_status,
                ]) : [],
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
