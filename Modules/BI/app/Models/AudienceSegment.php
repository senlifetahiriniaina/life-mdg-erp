<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property string                    $name
 * @property string|null               $description
 * @property array<string, mixed>      $criteria
 * @property array<string, mixed>|null $visibility_config
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\Company  $company
 */
class AudienceSegment extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_audience_segments';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'criteria',
        'visibility_config',
    ];

    protected $casts = [
        'criteria'           => 'array',
        'visibility_config'  => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function getCriteria(): array
    {
        return $this->criteria ?? [];
    }

    public function getVisibleStories(): array
    {
        return $this->visibility_config['visible_stories'] ?? [];
    }
}
