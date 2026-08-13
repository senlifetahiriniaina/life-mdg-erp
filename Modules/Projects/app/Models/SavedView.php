<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int              $id
 * @property string           $entity_type      task | project | milestone
 * @property string           $name
 * @property array|null       $filters          JSON filter definitions
 * @property string|null      $sort_by          column name or null
 * @property string           $sort_direction   asc | desc
 * @property string|null      $group_by         column name to group by
 * @property list<string>|null $visible_columns  List of visible column keys
 * @property bool             $is_default
 * @property bool             $is_shared        Shared across the workspace
 * @property int              $created_by
 * @property Carbon           $created_at
 * @property Carbon           $updated_at
 * @property Carbon|null      $deleted_at
 * @property-read User        $creator
 */
class SavedView extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'projects_saved_views';

    protected $fillable = [
        'entity_type',
        'name',
        'filters',
        'sort_by',
        'sort_direction',
        'group_by',
        'visible_columns',
        'is_default',
        'is_shared',
        'created_by',
    ];

    protected $casts = [
        'filters'         => 'array',
        'visible_columns' => 'array',
        'is_default'      => 'boolean',
        'is_shared'       => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public const ENTITY_TYPES = ['task', 'project', 'milestone'];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)->orWhere('is_shared', true);
        });
    }
}
