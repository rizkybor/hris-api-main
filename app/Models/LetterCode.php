<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LetterCode extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name'];

    public function letters()
    {
        return $this->hasMany(Letter::class);
    }
}
