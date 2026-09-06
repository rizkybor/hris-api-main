<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Bundled services -- each becomes one line item when an
            // invoice is generated. Labels for service_type come from
            // Settings -> Dropdown Options (category
            // "subscription_service_type"), resolved client-side.
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'service_type' => $service->service_type,
                'product_name' => $service->product_name,
                'amount' => (float) (string) $service->amount,
                // Optional per-service VAT/PPN override, only meaningful
                // when there's more than one service on the subscription
                // -- see Subscription::ppn_percentage.
                'ppn_percentage' => $service->ppn_percentage !== null ? (float) (string) $service->ppn_percentage : null,
                // Optional pass-through fee (e.g. ICANN's registrar fee on
                // a domain service) -- not every service has one.
                'icann_fee' => $service->icann_fee !== null ? (float) (string) $service->icann_fee : null,
                'notes' => $service->notes,
            ])),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null),
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'pic_name' => $this->client->pic_name,
                'email' => $this->client->email,
                'pic_phone' => $this->client->pic_phone,
            ] : null),
            'billing_cycle' => $this->billing_cycle,
            'amount' => (float) (string) $this->amount,
            'start_date' => $this->start_date,
            'next_due_date' => $this->next_due_date,
            'status' => $this->status,
            'last_invoiced_at' => $this->last_invoiced_at,
            'notes' => $this->notes,
            'ppn_percentage' => (float) (string) $this->ppn_percentage,
            'admin_fee' => (float) (string) $this->admin_fee,
            'bank_name' => $this->bank_name,
            'terms' => $this->terms,
            'pph23_type' => $this->pph23_type,
            'pph23_percent' => $this->pph23_percent !== null ? (float) (string) $this->pph23_percent : null,
            'invoices_count' => $this->whenCounted('invoices'),
            // Only set when an invoice already exists for the SAME period
            // generating now would cover (same month for monthly, same year
            // for yearly) -- an invoice from a different period doesn't
            // count as a duplicate, so it's left null.
            'duplicate_invoice_number' => $this->whenLoaded('invoices', function () {
                $periodLabel = $this->currentPeriodLabel();

                return $periodLabel
                    ? $this->invoices->firstWhere('billing_period', $periodLabel)?->invoice_number
                    : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
