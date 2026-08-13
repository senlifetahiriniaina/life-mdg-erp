<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $source_id
 * @property string               $name
 * @property string|null          $description
 * @property int                  $rule_order
 * @property string               $rule_type
 * @property array<string, mixed> $rule_config
 * @property bool                 $is_active
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read ExternalDataSource $source
 */
class TransformationRule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_transformation_rules';

    protected $fillable = [
        'source_id',
        'name',
        'description',
        'rule_order',
        'rule_type',
        'rule_config',
        'is_active',
    ];

    protected $casts = [
        'rule_config' => 'array',
        'is_active'   => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getRuleTypeLabel(): string
    {
        return match ($this->rule_type) {
            'filter'    => 'Filter',
            'map'       => 'Map',
            'aggregate' => 'Aggregate',
            'custom'    => 'Custom',
            default     => ucfirst($this->rule_type),
        };
    }

    public function getRuleConfig(): array
    {
        return $this->rule_config ?? [];
    }

    public function apply(mixed $data): mixed
    {
        if (! $this->is_active) {
            return $data;
        }

        return match ($this->rule_type) {
            'filter'    => $this->applyFilter($data),
            'map'       => $this->applyMap($data),
            'aggregate' => $this->applyAggregate($data),
            'custom'    => $this->applyCustom($data),
            default     => $data,
        };
    }

    private function applyFilter(mixed $data): mixed
    {
        $config = $this->getRuleConfig();
        // Implement filter logic based on config
        return $data;
    }

    private function applyMap(mixed $data): mixed
    {
        $config = $this->getRuleConfig();
        // Implement map logic based on config
        return $data;
    }

    private function applyAggregate(mixed $data): mixed
    {
        $config = $this->getRuleConfig();
        // Implement aggregate logic based on config
        return $data;
    }

    private function applyCustom(mixed $data): mixed
    {
        // Implement custom transformation
        return $data;
    }
}
