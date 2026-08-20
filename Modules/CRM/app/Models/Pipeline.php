<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\PipelineFactory;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_default
 * @property array<int,mixed> $stages
 */
class Pipeline extends Model
{
    use HasFactory;

    protected static function newFactory(): PipelineFactory
    {
        return PipelineFactory::new();
    }

    protected $table = 'crm_pipelines';

    protected $fillable = ['name', 'is_default', 'stages', 'company_id'];

    protected $casts = [
        'stages' => 'array',
        'is_default' => 'boolean',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'pipeline_id');
    }
}
