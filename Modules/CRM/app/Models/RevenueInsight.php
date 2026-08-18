<?php

namespace Modules\CRM\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $insight_type
 * @property string $category
 * @property string $title
 * @property string $description
 * @property array|null $data
 * @property int $impact_score
 * @property string $status
 * @property int|null $relevant_user_id
 * @property \Carbon\Carbon $insight_generated_at
 */
class RevenueInsight extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity, AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_revenue_insights';

    protected string $auditModule = 'CRM';
    protected array $auditableFields = ['status', 'title', 'insight_type'];

    protected $fillable = [
        'company_id', 'insight_type', 'category', 'title', 'description', 'data',
        'impact_score', 'status', 'relevant_user_id', 'insight_generated_at',
    ];

    protected $casts = [
        'data'                  => 'json',
        'insight_generated_at'  => 'datetime',
    ];

    public function relevantUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'relevant_user_id');
    }

    public function getImpactLevelAttribute(): string
    {
        return match (true) {
            $this->impact_score >= 8 => 'High',
            $this->impact_score >= 5 => 'Medium',
            default => 'Low'
        };
    }
}
