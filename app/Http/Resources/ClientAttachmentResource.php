<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientAttachmentResource extends JsonResource
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
            'document_name' => $this->document_name,
            // Was a raw relative path before (unlike every other
            // attachment Resource, which already wrapped it) -- now a full
            // URL like the rest, since document_path holds a Cloudinary
            // public_id rather than a local path.
            'document_path' => CloudinaryUrl::autoByExtension($this->document_path, $this->type_file),
            'type_file' => $this->type_file,
            'size_file' => $this->size_file,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
