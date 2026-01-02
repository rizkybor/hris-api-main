<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pic_name' => $this->pic_name,
            'pic_phone' => $this->pic_phone,
            'email' => $this->email,
            'address' => $this->address,
            'type' => $this->type,
            'field' => $this->field,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relasi task pivots
            'task_pivots' => $this->whenLoaded('taskPivots', function () {
                return VendorsTaskPivotResource::collection($this->taskPivots);
            }),

            // Relasi attachments
            'attachments' => $this->whenLoaded('attachments', function () {
                return VendorsAttachmentResource::collection($this->attachments);
            }),
        ];
    }
}
