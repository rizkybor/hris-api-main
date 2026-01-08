<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilesCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Gunakan asset() untuk public disk, aman dan IDE-friendly
            'document_path' => $this->document_path 
                ? asset('storage/company-files/' . $this->document_path) 
                : null,
            'document_name' => $this->document_name,
            'description' => $this->description,
            'type_file' => $this->type_file,
            'size_file' => $this->size_file,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
