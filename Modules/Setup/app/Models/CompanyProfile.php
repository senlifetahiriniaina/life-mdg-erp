<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $tenant_id
 * @property string      $company_name
 * @property string|null $legal_name
 * @property string|null $company_type
 * @property string|null $industry
 * @property string|null $country_code
 * @property string|null $currency_code
 * @property string|null $timezone
 * @property int|null    $fiscal_year_start
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $vat_number
 * @property bool         $vat_exempt
 * @property string|null $logo_path
 * @property array|null  $admin_profile
 * @property array|null  $modules_selected
 * @property array|null  $workflows_config
 * @property array|null  $apps_config
 * @property bool         $onboarding_completed
 * @property \Illuminate\Support\Carbon|null $onboarding_completed_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
class CompanyProfile extends Model
{
    // Chantier 32.10 (deep 14-layer audit, CORE layer): this model — which
    // holds the tenant's real legal name, VAT number/exempt status,
    // currency, and country config — had no audit trail at all, unlike
    // every sibling model in this module (ImportJob/FieldMapping/
    // OnboardingSession/etc. all use this same trait). Added for real
    // Compliance-First coverage of admin/wizard edits to company config.
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'setup_company_profiles';

    protected $fillable = [
        'tenant_id',
        'company_name',
        'legal_name',
        'company_type',
        'industry',
        'country_code',
        'currency_code',
        'timezone',
        'fiscal_year_start',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'postal_code',
        'vat_number',
        'vat_exempt',
        'logo_path',
        'admin_profile',
        'modules_selected',
        'workflows_config',
        'apps_config',
        'onboarding_completed',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'admin_profile' => 'array',
        'modules_selected' => 'array',
        'workflows_config' => 'array',
        'apps_config' => 'array',
        'onboarding_completed' => 'boolean',
        'vat_exempt' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'fiscal_year_start' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
