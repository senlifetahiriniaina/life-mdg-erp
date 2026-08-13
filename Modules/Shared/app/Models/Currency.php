<?php

declare(strict_types=1);

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'shared_currencies';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'name_fr',
        'symbol',
        'symbol_native',
        'decimals',
        'is_cfa',
        'is_active',
        'exchange_rate_to_usd',
        'exchange_rate_updated_at',
        'region',
    ];

    protected $casts = [
        'decimals'                 => 'integer',
        'is_cfa'                   => 'boolean',
        'is_active'                => 'boolean',
        'exchange_rate_to_usd'     => 'decimal:6',
        'exchange_rate_updated_at' => 'datetime',
    ];

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCfa($query)
    {
        return $query->where('is_cfa', true);
    }

    public function scopeAfrican($query)
    {
        return $query->where('region', 'Africa');
    }

    public function scopeAsian($query)
    {
        return $query->where('region', 'Asia');
    }

    // --- Helpers ---

    public function format(float $amount): string
    {
        $formatted = number_format($amount, $this->decimals, '.', ',');
        return "{$this->symbol}{$formatted}";
    }

    public function convertTo(float $amount, string $targetCurrencyCode): ?float
    {
        if ($this->exchange_rate_to_usd === null) {
            return null;
        }
        $target = static::where('code', $targetCurrencyCode)->first();
        if ($target === null || $target->exchange_rate_to_usd === null) {
            return null;
        }

        $amountInUsd = $amount / (float) $this->exchange_rate_to_usd;
        return $amountInUsd * (float) $target->exchange_rate_to_usd;
    }

    public function isCfaFranc(): bool
    {
        return (bool) $this->is_cfa;
    }
}
