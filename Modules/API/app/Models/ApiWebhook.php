<?php

declare(strict_types=1);

namespace Modules\API\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiWebhook extends Model
{
    use HasFactory;

    protected $table = 'api_webhooks';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'url',
        'events',
        'secret',
        'active',
        'last_triggered_at',
        'last_status_code',
        'failure_count',
        'headers',
        'description',
    ];

    protected $casts = [
        'events'            => 'array',
        'headers'           => 'array',
        'active'            => 'boolean',
        'last_triggered_at' => 'datetime',
        'failure_count'     => 'integer',
        'last_status_code'  => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    // --- Helpers ---

    public function subscribesTo(string $event): bool
    {
        if (empty($this->events)) {
            return false;
        }
        return in_array($event, $this->events, true) || in_array('*', $this->events, true);
    }

    public function disable(): bool
    {
        return $this->update(['active' => false]);
    }

    public function enable(): bool
    {
        return $this->update(['active' => true, 'failure_count' => 0]);
    }

    public function recordSuccess(int $statusCode): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'last_status_code'  => $statusCode,
            'failure_count'     => 0,
        ]);
    }

    public function recordFailure(int $statusCode): void
    {
        $this->increment('failure_count');
        $this->update([
            'last_triggered_at' => now(),
            'last_status_code'  => $statusCode,
        ]);

        // Auto-disable after 10 consecutive failures
        if ($this->fresh()->failure_count >= 10) {
            $this->disable();
        }
    }
}
