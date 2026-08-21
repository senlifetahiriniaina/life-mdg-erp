<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chantier 26 (volet D) — une revue finance mensuelle ou trimestrielle
 * enregistrée par l'équipe finance. Voir la migration de création pour le
 * détail du modèle de données et FinanceReviewService pour le calcul (en
 * direct, jamais stocké) de la réalisation des objectifs/du budget.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $reviewer_id
 * @property int|null $budget_id
 * @property string $cadence
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property \Carbon\Carbon $review_date
 * @property string|null $comments
 */
class FinanceReview extends Model
{
    use HasFactory;

    public const CADENCES = ['monthly', 'quarterly'];

    protected $table = 'acc_finance_reviews';

    protected $fillable = [
        'company_id',
        'reviewer_id',
        'budget_id',
        'cadence',
        'period_start',
        'period_end',
        'review_date',
        'comments',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'review_date'  => 'date',
    ];

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
