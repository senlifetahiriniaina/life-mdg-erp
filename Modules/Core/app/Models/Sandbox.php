<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sandbox — Tenant Sandbox Environments (Item #19)
 *
 * Represents a cloned, isolated sandbox environment derived from a
 * production tenant. Sandboxes are empty (no real data) and expire
 * after EXPIRY_DAYS days.
 *
 * @property int         $id
 * @property string      $tenant_id          The sandbox tenant (new isolated DB)
 * @property string      $parent_tenant_id   The production tenant it was cloned from
 * @property string      $name               Human-readable sandbox label
 * @property Carbon|null $expires_at
 * @property string      $status             active|expired|deleted
 * @property int|null    $company_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Sandbox extends Model
{
    use SoftDeletes;

    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DELETED = 'deleted';

    /** Default sandbox lifetime in days. */
    public const EXPIRY_DAYS = 30;

    // ── Eloquent config ───────────────────────────────────────────────────────

    protected $table = 'sandboxes';

    protected $fillable = [
        'tenant_id',
        'parent_tenant_id',
        'name',
        'expires_at',
        'status',
        'company_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * The sandbox tenant (the cloned, isolated DB).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The production tenant from which this sandbox was cloned.
     */
    public function parentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'parent_tenant_id');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Returns true when the sandbox's expiry date has passed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Limit results to sandboxes that are active and not yet expired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }
}
