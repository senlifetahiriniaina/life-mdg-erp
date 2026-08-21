<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Database\Factories\PipelineSnapshotFactory;

/**
 * @property int $id
 * @property int $pipeline_id
 * @property Carbon $snapshot_date
 * @property string $total_value
 * @property int $deal_count
 * @property string $avg_deal_size
 * @property array<string, mixed>|null $stage_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PipelineSnapshot extends Model
{
    use HasFactory;

    protected static function newFactory(): PipelineSnapshotFactory
    {
        return PipelineSnapshotFactory::new();
    }

    protected $table = 'crm_pipeline_snapshots';

    protected $fillable = [
        'tenant_id',
        'pipeline_id',
        'snapshot_date',
        'total_value',
        'deal_count',
        'avg_deal_size',
        'stage_data',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'total_value' => 'decimal:4',
        'avg_deal_size' => 'decimal:4',
        'stage_data' => 'array',
    ];

    public function getStageData(): array
    {
        return $this->stage_data ?? [];
    }
}
