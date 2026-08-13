<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property string      $entity_type   task | project | milestone
 * @property string      $field_name    Machine-friendly key (snake_case)
 * @property string      $field_label   Human-readable label
 * @property string      $field_type    text | number | date | select | multiselect | checkbox
 * @property list<string>|null $options  For select/multiselect
 * @property bool        $is_required
 * @property int         $sort_order
 * @property int         $created_by
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User   $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomFieldValue> $values
 */
class CustomField extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'projects_custom_fields';

    protected $fillable = [
        'entity_type',
        'field_name',
        'field_label',
        'field_type',
        'options',
        'is_required',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public const ENTITY_TYPES = ['task', 'project', 'milestone'];

    public const FIELD_TYPES = ['text', 'number', 'date', 'select', 'multiselect', 'checkbox'];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType)->orderBy('sort_order');
    }
}
