<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $dashboard_id
 * @property int         $tenant_id
 * @property string      $token_hash    bcrypt hash of raw JWT (for revocation lookup)
 * @property string      $jti           JWT ID (UUID) — indexed for fast revocation check
 * @property list<string> $allowed_domains
 * @property Carbon      $expires_at
 * @property int         $created_by
 * @property Carbon|null $revoked_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property-read Dashboard $dashboard
 * @property-read User       $creator
 */
class EmbedToken extends Model
{
    use HasFactory;

    protected $table = 'bi_embed_tokens';

    protected $fillable = [
        'dashboard_id',
        'tenant_id',
        'token_hash',
        'jti',
        'allowed_domains',
        'expires_at',
        'created_by',
        'revoked_at',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'expires_at'      => 'datetime',
        'revoked_at'      => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
