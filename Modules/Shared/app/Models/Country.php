<?php

declare(strict_types=1);

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'shared_countries';

    public $timestamps = false;

    protected $fillable = [
        'iso_alpha2',
        'iso_alpha3',
        'name',
        'name_fr',
        'name_local',
        'currency_code',
        'phone_prefix',
        'region',
        'subregion',
        'is_ohada',
        'is_uemoa',
        'is_cemac',
        'vat_rate',
        'fiscal_year_start',
        'timezone',
        'flag_emoji',
    ];

    protected $casts = [
        'is_ohada'          => 'boolean',
        'is_uemoa'          => 'boolean',
        'is_cemac'          => 'boolean',
        'vat_rate'          => 'decimal:2',
    ];

    // --- Scopes ---

    public function scopeOhada($query)
    {
        return $query->where('is_ohada', true);
    }

    public function scopeUemoa($query)
    {
        return $query->where('is_uemoa', true);
    }

    public function scopeCemac($query)
    {
        return $query->where('is_cemac', true);
    }

    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    public function scopeAfrica($query)
    {
        return $query->where('region', 'Africa');
    }

    public function scopeAsia($query)
    {
        return $query->where('region', 'Asia');
    }

    // --- Helpers ---

    public function getCurrencySymbol(): ?string
    {
        $currency = Currency::where('code', $this->currency_code)->first();
        return $currency?->symbol;
    }

    public function isCfaFranc(): bool
    {
        return in_array($this->currency_code, ['XOF', 'XAF'], true);
    }
}
