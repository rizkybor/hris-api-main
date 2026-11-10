<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompanyCashTransaction extends Model
{
    use LogsActivity, SoftDeletes;

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
        'category',
        'employee_id',
        'description',
        'amount',
        'transaction_date',
        'notes',
        'proof_file',
        'proof_name',
        'proof_mime',
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

    /**
     * Spending: an entry recorded on behalf of an employee. Always a Debit
     * row (owner's convention), see CompanyCashTransactionController.
     */
    public const CATEGORY_SPENDING = 'spending';

    public const CATEGORIES = [self::CATEGORY_SPENDING];

    /** The employee a spending entry is on behalf of. */
    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id')->withTrashed();
    }

    public function isSpending(): bool
    {
        return $this->category === self::CATEGORY_SPENDING;
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
