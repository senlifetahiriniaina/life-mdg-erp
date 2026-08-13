<?php

declare(strict_types=1);

namespace Modules\API\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'api_requests';

    protected $fillable = [
        'tenant_id',
        'api_key_id',
        'user_id',
        'method',
        'endpoint',
        'query_params',
        'request_body',
        'response_body',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'query_params'  => 'array',
        'request_body'  => 'array',
        'response_body' => 'array',
        'status_code'   => 'integer',
        'duration_ms'   => 'integer',
        'created_at'    => 'datetime',
    ];

    // --- Relationships ---

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    // --- Scopes ---

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    public function scopeByEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', 'like', "%{$endpoint}%");
    }

    public function scopeSlowRequests($query, int $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    // --- Helpers ---

    public function isSuccess(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isError(): bool
    {
        return $this->status_code >= 400;
    }
}
