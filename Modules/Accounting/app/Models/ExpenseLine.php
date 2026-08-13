<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\ExpenseLineFactory;

/**
 * @property int $id
 * @property int $report_id
 * @property Carbon $date
 * @property string $category
 * @property string|null $description
 * @property string $amount
 * @property string $currency
 * @property string|null $receipt_url
 * @property string|null $km
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExpenseReport $report
 */
class ExpenseLine extends Model
{
    use HasFactory;

    protected static function newFactory(): ExpenseLineFactory
    {
        return ExpenseLineFactory::new();
    }

    protected $table = 'acc_expense_lines';

    protected $fillable = [
        'report_id',
        'date',
        'category',
        'description',
        'amount',
        'currency',
        'receipt_url',
        'km',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'amount' => 'encrypted:decimal:2',
        'km' => 'decimal:1',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class, 'report_id');
    }
}
