<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HR\Database\Factories\SalaryBandFactory;

/**
 * @property int $id
 * @property string $title
 * @property string $level
 * @property string $min_salary
 * @property string $mid_salary
 * @property string $max_salary
 * @property string $currency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SalaryBand extends Model
{
    use HasFactory;

    protected $table = 'hr_salary_bands';

    protected static function newFactory(): SalaryBandFactory
    {
        return SalaryBandFactory::new();
    }

    protected $fillable = [
        'title',
        'level',
        'min_salary',
        'mid_salary',
        'max_salary',
        'currency',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'mid_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
    ];

    public function simulateRaise(float $pct): array
    {
        $factor = 1 + ($pct / 100);

        return [
            'min_salary' => round((float) $this->min_salary * $factor, 2) + 0.0,
            'mid_salary' => round((float) $this->mid_salary * $factor, 2) + 0.0,
            'max_salary' => round((float) $this->max_salary * $factor, 2) + 0.0,
        ];
    }
}
