<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Database\Factories\CustomFieldFactory;

/**
 * @property int $id
 * @property string $entity_type
 * @property string $field_key
 * @property string $field_label
 * @property string $field_type
 * @property bool $is_required
 * @property bool $is_unique
 * @property bool $is_searchable
 * @property string|null $default_value
 * @property array<string, mixed>|null $options
 * @property string|null $validation_rules
 * @property string|null $group_name
 * @property int $sort_order
 * @property bool $is_active
 */
class CustomField extends Model
{
    use HasFactory;

    protected $table = 'core_custom_fields';

    protected $fillable = [
        'entity_type',
        'field_key',
        'field_label',
        'field_type',
        'is_required',
        'is_unique',
        'is_searchable',
        'default_value',
        'options',
        'validation_rules',
        'group_name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'is_searchable' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'json',
        'sort_order' => 'integer',
    ];

    protected static function newFactory(): CustomFieldFactory
    {
        return CustomFieldFactory::new();
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Parse the validation_rules string and merge type-specific rules.
     *
     * @return array<int|string, string>
     */
    public function getValidationRules(): array
    {
        $rules = [];

        if ($this->validation_rules) {
            $rules = array_filter(
                array_map('trim', explode('|', $this->validation_rules)),
                fn ($r) => $r !== ''
            );
        }

        // Merge type-specific rules (avoid duplicates)
        $typeRules = match ($this->field_type) {
            'number' => ['integer'],
            'decimal' => ['numeric'],
            'boolean' => ['boolean'],
            'date' => ['date'],
            'datetime' => ['date'],
            'email' => ['email'],
            'url' => ['url'],
            'select' => [],
            'multi_select' => [],
            default => [],
        };

        foreach ($typeRules as $typeRule) {
            if (! in_array($typeRule, $rules, true)) {
                $rules[] = $typeRule;
            }
        }

        if ($this->is_required && ! in_array('required', $rules, true)) {
            array_unshift($rules, 'required');
        }

        return array_values($rules);
    }

    /**
     * Cast a raw stored string to the proper PHP type for this field.
     */
    public function castValue(string $raw): mixed
    {
        if ($raw === '') {
            return null;
        }

        return match ($this->field_type) {
            'number' => (int) $raw,
            'decimal' => (float) $raw,
            'boolean' => in_array(strtolower($raw), ['true', '1', 'yes', 'on'], true),
            'date',
            'datetime' => $raw,
            'multi_select' => json_decode($raw, true) ?? explode(',', $raw),
            default => $raw,
        };
    }

    /**
     * Run validation rules against a value.
     */
    public function validateValue(mixed $value): bool
    {
        $rules = $this->getValidationRules();

        if (empty($rules) && ! $this->is_required) {
            return true;
        }

        $validator = Validator::make(
            ['value' => $value],
            ['value' => $rules]
        );

        return ! $validator->fails();
    }
}
