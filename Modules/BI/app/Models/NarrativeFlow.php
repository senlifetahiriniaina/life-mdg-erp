<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                  $id
 * @property int                  $story_id
 * @property string               $name
 * @property string|null          $description
 * @property array<string, mixed> $flow_config
 * @property bool                 $is_active
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property \Carbon\Carbon|null  $deleted_at
 * @property-read DataStory       $story
 */
class NarrativeFlow extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_narrative_flows';

    protected $fillable = [
        'story_id',
        'name',
        'description',
        'flow_config',
        'is_active',
    ];

    protected $casts = [
        'flow_config' => 'array',
        'is_active'   => 'boolean',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DataStory::class);
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
}
