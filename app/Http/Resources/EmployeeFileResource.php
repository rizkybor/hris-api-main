<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeFileResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'original_name' => $this->original_name,
            'display_name' => $this->display_name ?: $this->original_name,
            'url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'mime_type' => $this->mime_type,
            'size_file' => $this->size_file,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at,
        ];
    }
}
