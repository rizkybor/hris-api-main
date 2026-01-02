<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorsTaskScopeResource extends JsonResource
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Opsional: jika ingin menampilkan tasks terkait
            // 'vendor_tasks' => $this->whenLoaded('taskVendor', function () {
            //     return VendorsTaskPivotResource::collection($this->taskVendor);
            // }),
        ];
    }
}
