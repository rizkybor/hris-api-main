<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningItem extends Model
{
    protected $fillable = [
        'screening_id', 'service_item_id', 'name', 'category', 'qty', 'hours', 'unit_price', 'price_unit',
        'duration_text', 'reason', 'is_selected', 'source', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'hours' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'is_selected' => 'boolean',
        ];
    }

    public function serviceItem()
    {
        return $this->belongsTo(ServiceItem::class);
    }

    /** qty x unit price, or null when priced by quote. */
    public function lineTotal(): ?float
    {
        return $this->unit_price === null ? null : round((float) $this->qty * (float) $this->unit_price, 2);
    }
}
