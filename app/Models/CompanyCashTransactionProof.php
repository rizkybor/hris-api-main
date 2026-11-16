<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One proof file (photo/scan or PDF) of a company cash transaction. */
class CompanyCashTransactionProof extends Model
{
    protected $fillable = ['company_cash_transaction_id', 'file', 'name', 'mime', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function transaction()
    {
        return $this->belongsTo(CompanyCashTransaction::class, 'company_cash_transaction_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
