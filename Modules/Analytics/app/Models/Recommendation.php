<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class Recommendation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    /**
     * Chantier 32.25 (audit 14 couches, Analytics — couche 6, IDOR) :
     * `recipient_type`/`recommended_type` sont des `morphTo()` génériques
     * remplies depuis la requête client, sans aucun cloisonnement avant ce
     * chantier — `RecommendationController::store()` ne validait que
     * `'required|string'`, donc un appelant pouvait y placer le FQCN réel
     * d'un modèle sensible (ex. `App\Models\User`) plutôt que l'alias
     * générique déjà attendu par le frontend/les tests (`'Customer'`,
     * `'Product'`) — confirmé empiriquement qu'un `GET
     * .../recommendations/{id}` avec un `recipient/recommended` ainsi
     * eager-loadé renvoyait alors les vraies colonnes (email, etc.) de ce
     * modèle arbitraire, y compris d'une autre société. Même motif déjà
     * documenté pour `HelpdeskLinkable` (CLAUDE.md § « Helpdesk: coupled to
     * every other module ») : liste blanche registrée, jamais un nom de
     * classe brut venu du client. Enregistrée en morph-map dans
     * `AnalyticsServiceProvider::boot()`.
     */
    public const MORPH_TYPE_ALIASES = [
        'Customer'    => \App\Models\Customer::class,
        'Contact'     => \Modules\CRM\Models\Contact::class,
        'Employee'    => \Modules\HR\Models\Employee::class,
        'Product'     => \Modules\Inventory\Models\Product::class,
        'Opportunity' => \Modules\CRM\Models\Opportunity::class,
    ];

    protected $table = 'recommendations';

    protected $fillable = [
        'recommendation_model_id',
        'company_id',
        'recipient_type',
        'recipient_id',
        'recommended_type',
        'recommended_id',
        'relevance_score',
        'rank',
        'reason',
        'metadata',
        'status',
        'viewed_at',
        'clicked_at',
        'acted_at',
        'expires_at',
    ];

    protected $casts = [
        'relevance_score' => 'float',
        'metadata' => 'array',
        'viewed_at' => 'datetime',
        'clicked_at' => 'datetime',
        'acted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function recommendationModel(): BelongsTo
    {
        return $this->belongsTo(RecommendationModel::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function recommended(): MorphTo
    {
        return $this->morphTo();
    }
}
