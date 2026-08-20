<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sales\Database\Factories\RecurringOrderTemplateFactory;

/**
 * Chantier 25 (volet E de la feuille de route Chantier 21) — commandes
 * récurrentes. Un modèle réutilisable (client + lignes + périodicité) qui
 * génère une vraie SalesOrder à échéance via RecurringOrderService, plutôt
 * que de ressaisir la même commande manuellement à chaque cycle.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $reference
 * @property string $name
 * @property string $recurrence
 * @property \Illuminate\Support\Carbon $next_run_at
 * @property bool $is_active
 */
class RecurringOrderTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sales_recurring_order_templates';

    protected $fillable = [
        'tenant_id',
        'reference',
        'name',
        'contact_id',
        'account_id',
        'currency',
        'recurrence',
        'next_run_at',
        'last_run_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'next_run_at' => 'date',
        'last_run_at' => 'date',
        'is_active' => 'boolean',
    ];

    public const RECURRENCES = ['weekly', 'monthly', 'quarterly'];

    protected static function newFactory()
    {
        return RecurringOrderTemplateFactory::new();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringOrderTemplateLine::class, 'recurring_order_template_id')->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Templates active dont l'échéance est aujourd'hui ou passée. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereDate('next_run_at', '<=', now()->toDateString());
    }
}
