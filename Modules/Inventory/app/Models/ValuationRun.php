<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ValuationRunFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $method
 * @property Carbon $valuation_date
 * @property string $status
 * @property string $total_value
 * @property int|null $product_count
 * @property array<mixed>|null $results
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ValuationRun extends Model
{
    use HasFactory;

    protected static function newFactory(): ValuationRunFactory
    {
        return ValuationRunFactory::new();
    }

    protected $table = 'inventory_valuation_runs';

    protected $fillable = [
        'name',
        'method',
        'valuation_date',
        'status',
        'total_value',
        'product_count',
        'results',
        'created_by',
    ];

    protected $casts = [
        'valuation_date' => 'date',
        'results' => 'array',
        'total_value' => 'decimal:4',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getResults(): array
    {
        return $this->results ?? [];
    }

    public function productCount(): int
    {
        return count($this->getResults());
    }
}
