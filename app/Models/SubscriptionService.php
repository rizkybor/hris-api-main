<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionService extends Model
{
    protected $fillable = [
        'service_type',
        'product_name',
        'amount',
        'ppn_percentage',
        'icann_fee',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'ppn_percentage' => 'decimal:2',
            'icann_fee' => 'decimal:2',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
