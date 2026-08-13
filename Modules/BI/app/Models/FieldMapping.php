<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                   $id
 * @property int                   $source_id
 * @property string                $source_field
 * @property string                $target_field
 * @property string                $data_type
 * @property string|null           $transformation_rule
 * @property bool                  $is_primary_key
 * @property bool                  $is_mapped
 * @property \Carbon\Carbon        $created_at
 * @property \Carbon\Carbon        $updated_at
 * @property-read ExternalDataSource $source
 */
class FieldMapping extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_field_mappings';

    protected $fillable = [
        'source_id',
        'source_field',
        'target_field',
        'data_type',
        'transformation_rule',
        'is_primary_key',
        'is_mapped',
    ];

    protected $casts = [
        'is_primary_key' => 'boolean',
        'is_mapped'      => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function markAsMapped(): void
    {
        $this->update(['is_mapped' => true]);
    }

    public function markAsUnmapped(): void
    {
        $this->update(['is_mapped' => false]);
    }

    public function isMapped(): bool
    {
        return $this->is_mapped;
    }

    public function isPrimaryKey(): bool
    {
        return $this->is_primary_key;
    }

    public function getDataTypeLabel(): string
    {
        return match ($this->data_type) {
            'string'   => 'String',
            'integer'  => 'Integer',
            'decimal'  => 'Decimal',
            'datetime' => 'DateTime',
            'boolean'  => 'Boolean',
            'json'     => 'JSON',
            default    => ucfirst($this->data_type),
        };
    }

    public function applyTransformation(mixed $value): mixed
    {
        if ($this->transformation_rule === null) {
            return $value;
        }

        // Simple transformation rule parsing
        // Format: "field.path" for JSON path, or custom functions
        if (str_contains($this->transformation_rule, '.')) {
            return $this->applyJsonPath($value, $this->transformation_rule);
        }

        return $value;
    }

    private function applyJsonPath(mixed $value, string $path): mixed
    {
        if (! is_array($value) && ! is_object($value)) {
            return $value;
        }

        $parts = explode('.', $path);
        $result = $value;

        foreach ($parts as $part) {
            if (is_array($result)) {
                $result = $result[$part] ?? null;
            } elseif (is_object($result)) {
                $result = $result->{$part} ?? null;
            } else {
                return null;
            }
        }

        return $result;
    }
}
