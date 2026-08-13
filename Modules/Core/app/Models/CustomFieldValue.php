<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\CustomFieldValueFactory;

/**
 * @property int $id
 * @property int $custom_field_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CustomField|null $customField
 */
class CustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'core_custom_field_values';

    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value',
    ];

    protected $casts = [
        'entity_id' => 'integer',
    ];

    protected static function newFactory(): CustomFieldValueFactory
    {
        return CustomFieldValueFactory::new();
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    /**
     * Return the value cast to the proper PHP type via the associated field.
     */
    public function castValue(): mixed
    {
        return $this->customField->castValue((string) ($this->value ?? ''));
    }

    /**
     * Whether the stored value is null or empty.
     */
    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '';
    }
}
