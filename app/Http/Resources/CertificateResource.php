<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'title' => $this->title,
            'recipient_name' => $this->recipient_name,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'signatory_name' => $this->signatory_name,
            'signatory_title' => $this->signatory_title,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
            'batch_id' => $this->batch_id,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator->name),
            'created_at' => $this->created_at,
        ];
    }
}
