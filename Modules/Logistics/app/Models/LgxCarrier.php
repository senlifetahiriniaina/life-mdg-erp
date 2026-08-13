<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LgxCarrier extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'lgx_carriers';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'api_type',
        'api_key_encrypted',
        'api_endpoint',
        'tracking_url_pattern',
        'is_active',
        'countries_served',
    ];

    protected $hidden = ['api_key_encrypted'];

    protected $casts = [
        'is_active'       => 'boolean',
        'countries_served' => 'array',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function shipments(): HasMany
    {
        return $this->hasMany(LgxShipment::class, 'carrier_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId): void {
            $q->whereNull('company_id')->orWhere('company_id', $companyId);
        });
    }

    public function scopeServingCountry(Builder $query, string $countryCode): Builder
    {
        return $query->whereJsonContains('countries_served', strtoupper($countryCode));
    }

    // ---------------------------------------------------------------
    // Accessors / helpers
    // ---------------------------------------------------------------

    public function buildTrackingUrl(string $trackingNumber): ?string
    {
        if ($this->tracking_url_pattern === null) {
            return null;
        }

        return str_replace('{tracking_number}', $trackingNumber, $this->tracking_url_pattern);
    }

    public function isAfrican(): bool
    {
        return in_array($this->api_type, ['senpost', 'campost', 'camex'], true);
    }

    public function isManual(): bool
    {
        return $this->api_type === 'manual';
    }
}
