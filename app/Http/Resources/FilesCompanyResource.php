<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
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
            'document_path' => CloudinaryUrl::auto($this->document_path, $this->type_file),
            'document_name' => $this->document_name,
            'description' => $this->description,
            'type_file' => $this->type_file,
            'size_file' => $this->size_file,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
