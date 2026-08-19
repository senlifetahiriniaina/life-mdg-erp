<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\FinancialSimulationFactory;
use Modules\Core\Traits\RecordsActivity;

class FinancialSimulation extends Model
{
    use HasFactory, RecordsActivity;

    protected static string $auditModule = 'Accounting';

    protected $table = 'acc_financial_simulations';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'granularity',
        'start_date',
        'horizon_periods',
        'opening_cash_balance',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'opening_cash_balance' => 'decimal:2',
        'horizon_periods' => 'integer',
    ];

    protected static function newFactory(): FinancialSimulationFactory
    {
        return FinancialSimulationFactory::new();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialSimulationLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
