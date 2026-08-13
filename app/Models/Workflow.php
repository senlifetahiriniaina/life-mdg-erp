<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_event',
        'steps',
        'is_enabled',
        'created_by',
    ];

    protected $casts = [
        'steps' => 'array',
        'is_enabled' => 'boolean',
        'last_executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }
}
