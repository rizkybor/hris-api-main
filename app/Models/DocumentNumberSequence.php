<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberSequence extends Model
{
    protected $fillable = ['document_type', 'scope_key', 'year', 'last_number'];
}
