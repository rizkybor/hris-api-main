<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentLetterAttachment extends Model
{
    protected $fillable = [
        'document_letter_id',
        'original_name',
        'file_path',
        'mime_type',
        'size_file',
        'uploaded_by',
    ];

    public function documentLetter()
    {
        return $this->belongsTo(DocumentLetter::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
