<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class CompanyAbout extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Company');
    }

    protected $table = 'company_abouts';

    protected $fillable = [
        'name', 'legal_name', 'npwp', 'description', 'established_date', 'vision', 'mission', 'branches', 'address', 'email', 'phone'
    ];

        protected $casts = [
        'established_date' => 'date',
        'mission' => 'array',
        'branches' => 'array',
    ];
}
