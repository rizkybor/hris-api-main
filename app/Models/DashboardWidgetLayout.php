<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetLayout extends Model
{
    protected $fillable = [
        'user_id',
        'widget_key',
        'position',
        'size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
