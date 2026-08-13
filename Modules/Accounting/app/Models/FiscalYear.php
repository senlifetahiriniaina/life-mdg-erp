<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Database\Factories\FiscalYearFactory;

/**
 * @property string $name
 * @property string $start_date
 * @property string $end_date
 * @property bool $is_closed
 */
class FiscalYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acc_fiscal_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_closed',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
    ];

    protected static function newFactory(): FiscalYearFactory
    {
        return FiscalYearFactory::new();
    }
}
