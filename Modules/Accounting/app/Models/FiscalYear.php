<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Database\Factories\FiscalYearFactory;

/**
 * Chantier 32 (volet A1) — un modèle réel jusque-là orphelin (zéro
 * contrôleur/route/policy/seed) : formalise le concept d'année d'exercice
 * comptable, avec `company_id` pour le cloisonnement multi-tenant (jamais
 * la colonne fantôme `tenant_id`, motif déjà établi partout dans l'app).
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $start_date
 * @property string $end_date
 * @property bool $is_closed
 * @property string|null $status
 */
class FiscalYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acc_fiscal_years';

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'is_closed',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
    ];

    protected static function newFactory(): FiscalYearFactory
    {
        return FiscalYearFactory::new();
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * L'exercice couvrant une date donnée, pour la société indiquée —
     * utilisé par JournalEntryApiController::store() pour résoudre
     * `fiscal_year_id` automatiquement à partir de la date de l'écriture.
     */
    public static function coveringDate(string $date, ?int $companyId): ?self
    {
        return static::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'fiscal_year_id');
    }
}
