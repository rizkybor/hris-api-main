<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompanyCashTransaction extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Company Cash Book');
    }

    protected $fillable = [
        'project_id',
        'project_cash_transaction_id',
        'type',
        'description',
        'amount',
        'transaction_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectCashTransaction()
    {
        return $this->belongsTo(ProjectCashTransaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * True when this row was auto-mirrored from a project's own cash
     * ledger -- such rows can't be edited/deleted directly here, only from
     * the project's ledger itself (see
     * CompanyCashTransactionController::canEditDirectly()).
     */
    public function isSynced(): bool
    {
        return $this->project_cash_transaction_id !== null;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('description', 'like', '%'.$search.'%');
    }
}
