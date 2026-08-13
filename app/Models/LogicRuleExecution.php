<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogicRuleExecution extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'trigger_data',
        'conditions_met',
        'actions_executed',
        'error_message',
        'duration_ms',
        'executed_at',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'conditions_met' => 'boolean',
        'actions_executed' => 'array',
        'executed_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LogicRule::class);
    }

    public function isSuccess(): bool
    {
        return $this->conditions_met && !$this->error_message;
    }
}
