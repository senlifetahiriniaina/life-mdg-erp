<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Harmonised System code catalogue.
 * 50 most common codes in West & Central African trade.
 */
class HsCode extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_hs_codes';

    protected $fillable = [
        'code',
        'description_fr',
        'description_en',
        'duty_rate_default',
        'vat_applicable',
        'requires_license',
        'notes',
        'chapter',
        'section',
        'unit',
    ];

    protected $casts = [
        'duty_rate_default' => 'decimal:2',
        'vat_applicable'    => 'boolean',
        'requires_license'  => 'boolean',
    ];

    public function getFormattedRateAttribute(): string
    {
        return number_format((float) $this->duty_rate_default, 1) . '%';
    }

    /**
     * Scope: filter by chapter (2-digit WCO chapter code).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     */
    public function scopeChapter(\Illuminate\Database\Eloquent\Builder $query, string $chapter): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('chapter', $chapter);
    }

    /**
     * Scope: full-text search on code, description_fr, description_en.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     */
    public function scopeSearchByCode(\Illuminate\Database\Eloquent\Builder $query, string $term): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('description_fr', 'like', "%{$term}%")
              ->orWhere('description_en', 'like', "%{$term}%");
        });
    }
}
