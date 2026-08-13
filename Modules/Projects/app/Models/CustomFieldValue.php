<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a value for a custom field on any entity (task/project/milestone).
 *
 * @property int                   $id
 * @property int                   $custom_field_id
 * @property string                $entity_type      task | project | milestone
 * @property int                   $entity_id
 * @property mixed                 $value            JSON — scalar, array or null
 * @property Carbon                $created_at
 * @property Carbon                $updated_at
 * @property-read CustomField      $field
 */
class CustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'projects_custom_field_values';

    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }
}
