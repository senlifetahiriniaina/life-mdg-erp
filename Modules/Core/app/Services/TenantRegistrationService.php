<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Jobs\ProvisionTenantJob;

class TenantRegistrationService
{
    /** Default modules to enable for all new tenants */
    private const DEFAULT_MODULES = [
        'Core', 'CRM', 'HR', 'Inventory', 'Accounting',
        'Manufacturing', 'POS', 'Ecommerce', 'BI', 'Email',
        'Documents', 'Helpdesk', 'Projects', 'WhatsApp',
    ];

    /**
     * Generate a URL-safe tenant slug from a company name.
     *
     * Format: {company-part}-{8-random-chars}
     * Max total length: 25 characters.
     */
    public function generateSlug(string $companyName): string
    {
        $suffix = '-' . Str::lower(Str::random(8));      // e.g. "-a3b7kxmn"
        $maxCompanyLen = 25 - strlen($suffix);            // 25 - 9 = 16

        $companyPart = Str::slug($companyName);           // lowercase, URL-safe
        if (strlen($companyPart) > $maxCompanyLen) {
            $companyPart = substr($companyPart, 0, $maxCompanyLen);
            $companyPart = rtrim($companyPart, '-');
        }

        if ($companyPart === '') {
            $companyPart = 'tenant';
        }

        $slug = $companyPart . $suffix;

        // Ensure uniqueness — regenerate suffix until unique
        while (DB::table('tenants')->where('slug', $slug)->exists()) {
            $suffix = '-' . Str::lower(Str::random(8));
            $slug = $companyPart . $suffix;
        }

        return $slug;
    }

    /**
     * Generate the widehalo.cloud domain for a slug.
     */
    public function generateDomain(string $slug): string
    {
        return "{$slug}.widehalo.cloud";
    }

    /**
     * Register a new tenant.
     *
     * @return array{tenant_id: string, slug: string, domain: string, company_name: string, plan: string}
     */
    public function register(string $companyName, string $email, string $plan = 'starter'): array
    {
        $slug   = $this->generateSlug($companyName);
        $domain = $this->generateDomain($slug);

        // Generate a 25-char alphanumeric lowercase ID
        $tenantId = $this->generateTenantId($companyName);

        $row = [
            'id'           => $tenantId,
            'slug'         => $slug,
            'company_name' => $companyName,
            'domain'       => $domain,
            'plan'         => $plan,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        // Include the stancl/tenancy data column when it exists (production uses
        // the vendor migration; the in-memory test SQLite db uses the Core stub).
        if (Schema::hasColumn('tenants', 'data')) {
            $row['data'] = json_encode(['email' => $email]);
        }

        DB::table('tenants')->insert($row);

        // Enable all default modules for the new tenant
        $now = now();
        $moduleRows = array_map(fn (string $module) => [
            'tenant_id'  => $tenantId,
            'module'     => $module,
            'enabled'    => true,
            'department' => null,
            'settings'   => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::DEFAULT_MODULES);

        DB::table('tenant_modules')->insert($moduleRows);

        // Provision the tenant database asynchronously (migrations + reference data)
        ProvisionTenantJob::dispatch($tenantId);

        return [
            'tenant_id'    => $tenantId,
            'slug'         => $slug,
            'domain'       => $domain,
            'company_name' => $companyName,
            'plan'         => $plan,
        ];
    }

    /**
     * Generate a 25-char alphanumeric lowercase tenant ID derived from the
     * company name slug + random suffix.
     */
    private function generateTenantId(string $companyName): string
    {
        $base   = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::slug($companyName)));
        $base   = substr((string) $base, 0, 15);
        $random = Str::lower(Str::random(25 - strlen($base)));
        $id     = $base . $random;

        // Pad/trim to exactly 25 chars
        $id = str_pad(substr($id, 0, 25), 25, Str::lower(Str::random(1)));

        while (DB::table('tenants')->where('id', $id)->exists()) {
            $random = Str::lower(Str::random(25 - strlen($base)));
            $id     = str_pad(substr($base . $random, 0, 25), 25, Str::lower(Str::random(1)));
        }

        return $id;
    }
}
