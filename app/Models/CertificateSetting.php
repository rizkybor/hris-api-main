<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
    protected $fillable = [
        'company_code',
        'number_format',
    ];

    public const DEFAULT_FORMAT = '{company}/{category}/{program}/{year}/{month_roman}/{sequence}';
}
