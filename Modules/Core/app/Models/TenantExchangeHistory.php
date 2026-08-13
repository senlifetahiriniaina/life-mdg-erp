<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exchange_id
 * @property string $action
 * @property string $actor_tenant_id
 * @property int|null $actor_user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantExchangeHistory extends Model
{
    use HasFactory;
    protected $table = 'core_tenant_exchange_history';

    protected $fillable = [
        'exchange_id',
        'action',
        'actor_tenant_id',
        'actor_user_id',
        'note',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(TenantExchange::class, 'exchange_id');
    }
}
