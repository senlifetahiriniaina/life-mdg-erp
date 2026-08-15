<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class DepreciationSchedule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'depreciation_schedules';

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_method',
        'useful_life_years',
        'residual_value',
        'depreciation_start_date',
        'depreciation_end_date',
        'annual_depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'depreciation_expense_account_id',
        'accumulated_depreciation_account_id',
        'status',
        'depreciation_method_details',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'residual_value' => 'decimal:2',
        'depreciation_start_date' => 'date',
        'depreciation_end_date' => 'date',
        'annual_depreciation_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'depreciation_method_details' => 'json',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\FixedAsset::class, 'fixed_asset_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'depreciation_expense_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'accumulated_depreciation_account_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class, 'depreciation_schedule_id');
    }
}
