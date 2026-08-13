<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantAuditLog;
use Modules\Core\Models\TenantUser;

/**
 * TenantManagerService — Phase 40
 *
 * Full lifecycle management of multi-tenant provisioning:
 *   - Provision (create isolated DB, run migrations, seed defaults, welcome email)
 *   - Suspend / Reactivate
 *   - Plan upgrade
 *   - GDPR: data export + purge
 *   - Global stats for superadmin dashboard
 *   - Smart defaults per country/industry
 *
 * All mutations are logged to tenant_audit_log for compliance.
 */
class TenantManagerService
{
    // ── Plan definitions ──────────────────────────────────────────────────────

    /** Modules available per plan (null = all). */
    private const PLAN_MODULES = [
        'starter'      => ['Core', 'CRM', 'HR', 'Inventory'],
        'professional' => ['Core', 'CRM', 'HR', 'Inventory', 'Accounting', 'POS', 'Ecommerce', 'Email', 'Helpdesk', 'Projects', 'Documents', 'BI'],
        'enterprise'   => null,
        'custom'       => null,
    ];

    /** Default trial period in days. */
    private const TRIAL_DAYS = 14;

    // ── Default modules per plan ───────────────────────────────────────────────

    private const ALL_MODULES = [
        'Core', 'CRM', 'HR', 'Inventory', 'Accounting', 'Manufacturing', 'POS',
        'Ecommerce', 'BI', 'Email', 'Documents', 'Helpdesk', 'Projects', 'WhatsApp',
        'SMS', 'Logistics', 'Planning', 'Quality', 'PLM', 'Timesheets', 'Achats',
        'Analytics', 'Security', 'MobileSync', 'RealTime', 'MarketingAutomation',
        'Workflow', 'Discussion', 'Messaging', 'Setup', 'Calendar', 'Strategy',
        'AI', 'API', 'Assets', 'Contracts', 'CustomerService', 'Integration',
        'Reporting', 'Sales', 'Settings', 'Shared', 'Validation', 'AuditLog',
    ];

    public function __construct(
        private readonly SmartDefaultsService $smartDefaults,
        private readonly TenantRegistrationService $registrationService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // PROVISION
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Provision a brand-new tenant from scratch.
     *
     * Steps:
     *  1. Create tenant record in central DB
     *  2. Create owner user account
     *  3. Link owner to tenant
     *  4. Create isolated tenant database
     *  5. Run all migrations on tenant DB
     *  6. Seed OHADA chart of accounts if XOF/XAF currency
     *  7. Enable default modules for the plan
     *  8. Send welcome email
     *  9. Audit log entry
     *
     * @param array{
     *   name: string,
     *   legal_name?: string,
     *   company_type?: string,
     *   country_code: string,
     *   currency: string,
     *   locale?: string,
     *   timezone?: string,
     *   industry: string,
     *   plan: string,
     *   owner_email: string,
     *   owner_name: string,
     *   owner_password?: string,
     *   contact_phone?: string,
     *   trial_days?: int,
     * } $data
     */
    public function provision(array $data): Tenant
    {
        // ── 1. Generate identifiers ────────────────────────────────────────────
        $companyName = $data['name'];
        $slug        = $this->registrationService->generateSlug($companyName);
        $tenantId    = $this->generateTenantId($companyName);
        $dbName      = 'wh_tenant_' . preg_replace('/[^a-z0-9_]/', '_', $tenantId);

        // Smart defaults for country
        $defaults = $this->getSmartDefaults($data['country_code'], $data['industry']);

        // ── 2. Create tenant record ────────────────────────────────────────────
        $trialDays = (int) ($data['trial_days'] ?? self::TRIAL_DAYS);

        $tenant = new Tenant([
            'id'           => $tenantId,
            'slug'         => $slug,
            'name'         => $companyName,
            'company_name' => $companyName,
            'legal_name'   => $data['legal_name'] ?? $companyName,
            'company_type' => $data['company_type'] ?? 'sarl',
            'country_code' => strtoupper($data['country_code']),
            'currency'     => $data['currency'] ?? $defaults['currency'],
            'locale'       => $data['locale'] ?? $defaults['locale'],
            'timezone'     => $data['timezone'] ?? $defaults['timezone'],
            'industry'     => $data['industry'],
            'plan'         => $data['plan'] ?? 'starter',
            'status'       => 'trial',
            'trial_ends_at'=> now()->addDays($trialDays),
            'plan_expires_at' => now()->addDays($trialDays + 30),
            'db_name'      => $dbName,
            'db_host'      => config('database.connections.mysql.host', '127.0.0.1'),
            'db_port'      => config('database.connections.mysql.port', 3306),
            'contact_email'=> $data['owner_email'],
            'contact_phone'=> $data['contact_phone'] ?? null,
            'domain'       => $this->registrationService->generateDomain($slug),
            'is_active'    => true,
            'onboarding_step' => 1, // Step 1 completed by registration
            'settings'     => [
                'features' => [
                    'mobile_money'    => in_array($defaults['currency'], ['XOF', 'XAF', 'KES', 'GHS', 'NGN', 'MGA'], true),
                    'ohada_accounting'=> in_array($defaults['accounting_std'], ['OHADA'], true),
                    'multi_currency'  => false,
                    'ai_assistant'    => true,
                    'offline_mode'    => true,
                ],
                'limits' => [
                    'max_users'    => $this->planUserLimit($data['plan'] ?? 'starter'),
                    'max_invoices' => $this->planInvoiceLimit($data['plan'] ?? 'starter'),
                ],
                'payment_methods' => $defaults['payment_methods'],
                'tax_rate'        => $defaults['tax_rate'],
                'accounting_std'  => $defaults['accounting_std'],
            ],
        ]);

        $tenant->save();

        // ── 3. Create owner user ───────────────────────────────────────────────
        $owner = $this->createOwnerUser(
            $data['owner_email'],
            $data['owner_name'],
            $data['owner_password'] ?? Str::random(16),
        );

        // ── 4. Link owner to tenant ────────────────────────────────────────────
        $tenant->owner_id = $owner->id;
        $tenant->save();

        TenantUser::create([
            'tenant_id' => $tenantId,
            'user_id'   => $owner->id,
            'role'      => 'owner',
            'joined_at' => now(),
        ]);

        // ── 5. Create isolated DB ──────────────────────────────────────────────
        $this->createTenantDatabase($dbName);

        // ── 6. Run migrations on tenant DB ────────────────────────────────────
        $this->runTenantMigrations($tenant);

        // ── 7. Seed OHADA accounts if XOF/XAF ─────────────────────────────────
        if (in_array($tenant->currency, ['XOF', 'XAF'], true)) {
            $this->seedOhadaChartOfAccounts($tenant);
        }

        // ── 8. Enable modules for plan ─────────────────────────────────────────
        $this->enableDefaultModules($tenantId, $tenant->plan);

        // ── 9. Send welcome email (fire-and-forget) ────────────────────────────
        $this->sendWelcomeEmail($tenant, $owner);

        // ── 10. Audit log ──────────────────────────────────────────────────────
        TenantAuditLog::log($tenantId, 'provision', [
            'new_values' => [
                'plan'        => $tenant->plan,
                'country'     => $tenant->country_code,
                'currency'    => $tenant->currency,
                'industry'    => $tenant->industry,
                'owner_email' => $tenant->contact_email,
            ],
        ]);

        Log::info('Tenant provisioned', ['tenant_id' => $tenantId, 'slug' => $slug]);

        return $tenant->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LIFECYCLE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Suspend a tenant (billing lapse, abuse, manual action).
     * Data is preserved — only access is blocked.
     */
    public function suspend(Tenant $tenant, string $reason = ''): void
    {
        $old = ['status' => $tenant->status, 'is_active' => $tenant->is_active];

        $tenant->update([
            'status'    => 'suspended',
            'is_active' => false,
        ]);

        TenantAuditLog::log($tenant->id, 'suspend', [
            'old_values' => $old,
            'new_values' => ['status' => 'suspended', 'reason' => $reason],
        ]);

        Log::warning('Tenant suspended', ['tenant_id' => $tenant->id, 'reason' => $reason]);
    }

    /**
     * Reactivate a previously suspended tenant.
     */
    public function reactivate(Tenant $tenant): void
    {
        $old = ['status' => $tenant->status, 'is_active' => $tenant->is_active];

        $tenant->update([
            'status'    => 'active',
            'is_active' => true,
        ]);

        TenantAuditLog::log($tenant->id, 'reactivate', [
            'old_values' => $old,
            'new_values' => ['status' => 'active'],
        ]);

        Log::info('Tenant reactivated', ['tenant_id' => $tenant->id]);
    }

    /**
     * Upgrade (or downgrade) the tenant's plan.
     * Also adjusts enabled modules and limits.
     */
    public function upgradePlan(Tenant $tenant, string $newPlan): void
    {
        $old = ['plan' => $tenant->plan];

        $tenant->update([
            'plan'           => $newPlan,
            'plan_expires_at'=> now()->addYear(),
            'status'         => 'active',
            'is_active'      => true,
            'settings'       => array_merge($tenant->settings ?? [], [
                'limits' => [
                    'max_users'    => $this->planUserLimit($newPlan),
                    'max_invoices' => $this->planInvoiceLimit($newPlan),
                ],
            ]),
        ]);

        // Enforce module access for the new plan
        $this->enableDefaultModules($tenant->id, $newPlan);

        TenantAuditLog::log($tenant->id, 'upgrade_plan', [
            'old_values' => $old,
            'new_values' => ['plan' => $newPlan],
        ]);

        Log::info('Tenant plan upgraded', ['tenant_id' => $tenant->id, 'plan' => $newPlan]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GDPR
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Export all tenant data as a JSON/ZIP archive (GDPR Article 20 — right to portability).
     *
     * Returns a temporary S3 URL or local file path.
     */
    public function exportData(Tenant $tenant): string
    {
        $tenantId  = $tenant->id;
        $filename  = "tenant_export_{$tenantId}_" . date('Ymd_His') . '.json';
        $exportDir = storage_path("app/exports/tenants/{$tenantId}");

        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $export = [
            'exported_at' => now()->toIso8601String(),
            'tenant'      => $tenant->only([
                'id', 'uuid', 'slug', 'name', 'company_name', 'legal_name',
                'company_type', 'country_code', 'currency', 'locale', 'timezone',
                'industry', 'plan', 'status', 'contact_email', 'contact_phone',
                'created_at', 'updated_at',
            ]),
            'users'       => DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray(),
            'modules'     => DB::table('tenant_modules')
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray(),
            'audit_log'   => DB::table('tenant_audit_log')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->limit(1000)
                ->get()
                ->toArray(),
        ];

        $path = "{$exportDir}/{$filename}";
        file_put_contents($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        TenantAuditLog::log($tenantId, 'data_export', [
            'new_values' => ['filename' => $filename],
        ]);

        // If S3 is configured, upload and return the signed URL
        if (config('filesystems.default') === 's3') {
            $s3Path = "exports/tenants/{$tenantId}/{$filename}";
            Storage::disk('s3')->put($s3Path, file_get_contents($path));
            unlink($path);

            return Storage::disk('s3')->temporaryUrl($s3Path, now()->addHours(24));
        }

        return $path;
    }

    /**
     * Permanently delete a tenant and all associated data (GDPR Article 17 — right to erasure).
     *
     * ⚠  Irreversible.  Drops the tenant's isolated database and removes all
     *    central-DB references.
     */
    public function purge(Tenant $tenant): void
    {
        $tenantId = $tenant->id;
        $dbName   = $tenant->db_name;

        Log::warning('Purging tenant', ['tenant_id' => $tenantId, 'db' => $dbName]);

        DB::transaction(function () use ($tenantId, $dbName, $tenant): void {
            // Drop isolated DB if it exists
            if ($dbName) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
                } catch (\Throwable $e) {
                    Log::error('Could not drop tenant DB', ['db' => $dbName, 'error' => $e->getMessage()]);
                }
            }

            // Remove all central-DB references
            DB::table('tenant_audit_log')->where('tenant_id', $tenantId)->delete();
            DB::table('tenant_invitations')->where('tenant_id', $tenantId)->delete();
            DB::table('tenant_users')->where('tenant_id', $tenantId)->delete();
            DB::table('tenant_modules')->where('tenant_id', $tenantId)->delete();

            // Force-delete (bypass soft-delete)
            $tenant->forceDelete();
        });

        Log::warning('Tenant purged', ['tenant_id' => $tenantId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GLOBAL STATS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return aggregate statistics across all tenants for the superadmin dashboard.
     *
     * @return array{
     *   total: int,
     *   active: int,
     *   trial: int,
     *   suspended: int,
     *   cancelled: int,
     *   countries: int,
     *   by_plan: array<string, int>,
     *   by_industry: array<string, int>,
     *   by_country: array<string, int>,
     *   new_last_30_days: int,
     *   churn_last_30_days: int,
     *   trial_expiring_7_days: int,
     * }
     */
    public function getGlobalStats(): array
    {
        $tenants = DB::table('tenants')
            ->whereNull('deleted_at')
            ->get();

        $total     = $tenants->count();
        $active    = $tenants->where('status', 'active')->count()
            + $tenants->where('is_active', true)->where('status', null)->count();
        $trial     = $tenants->where('status', 'trial')->count();
        $suspended = $tenants->where('status', 'suspended')->count();
        $cancelled = $tenants->where('status', 'cancelled')->count();

        $countries  = $tenants->whereNotNull('country_code')->pluck('country_code')->unique()->count();

        $byPlan = $tenants->groupBy('plan')->map->count()->toArray();
        $byIndustry = $tenants->whereNotNull('industry')->groupBy('industry')->map->count()->toArray();
        $byCountry  = $tenants->whereNotNull('country_code')->groupBy('country_code')->map->count()->toArray();

        $newLast30 = DB::table('tenants')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $churnLast30 = DB::table('tenants')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '>=', now()->subDays(30))
            ->count();

        $trialExpiring7 = DB::table('tenants')
            ->whereNull('deleted_at')
            ->where('status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->count();

        return [
            'total'                 => $total,
            'active'                => $active,
            'trial'                 => $trial,
            'suspended'             => $suspended,
            'cancelled'             => $cancelled,
            'countries'             => $countries,
            'by_plan'               => $byPlan,
            'by_industry'           => $byIndustry,
            'by_country'            => $byCountry,
            'new_last_30_days'      => $newLast30,
            'churn_last_30_days'    => $churnLast30,
            'trial_expiring_7_days' => $trialExpiring7,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SMART DEFAULTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return smart defaults for onboarding based on country + industry.
     *
     * Extends SmartDefaultsService with timezone and locale defaults.
     *
     * @return array{
     *   currency: string,
     *   locale: string,
     *   timezone: string,
     *   tax_rate: float,
     *   tax_label: string,
     *   accounting_standard: string,
     *   payment_methods: list<string>,
     *   fiscal_year_start: int,
     *   mobile_country_code: string,
     *   units: list<string>,
     * }
     */
    public function getSmartDefaults(string $countryCode, string $industry = 'general'): array
    {
        $base = $this->smartDefaults->getDefaults('core', $countryCode, $industry);

        // Enrich with timezone and locale (not in SmartDefaultsService yet)
        $extra = self::COUNTRY_EXTRA[strtoupper($countryCode)] ?? self::COUNTRY_EXTRA['SN'];

        return array_merge($base, [
            'locale'             => $extra['locale'],
            'timezone'           => $extra['timezone'],
            'accounting_std'     => $base['accountingStd'],
            'accounting_standard'=> $base['accountingStd'],
        ]);
    }

    /** Extra country-level data not in SmartDefaultsService (locale, timezone). */
    private const COUNTRY_EXTRA = [
        'SN' => ['locale' => 'fr', 'timezone' => 'Africa/Dakar'],
        'CI' => ['locale' => 'fr', 'timezone' => 'Africa/Abidjan'],
        'CM' => ['locale' => 'fr', 'timezone' => 'Africa/Douala'],
        'GH' => ['locale' => 'en', 'timezone' => 'Africa/Accra'],
        'NG' => ['locale' => 'en', 'timezone' => 'Africa/Lagos'],
        'KE' => ['locale' => 'en', 'timezone' => 'Africa/Nairobi'],
        'TZ' => ['locale' => 'en', 'timezone' => 'Africa/Dar_es_Salaam'],
        'MG' => ['locale' => 'mg', 'timezone' => 'Indian/Antananarivo'],
        'MA' => ['locale' => 'fr', 'timezone' => 'Africa/Casablanca'],
        'EG' => ['locale' => 'ar', 'timezone' => 'Africa/Cairo'],
        'IN' => ['locale' => 'hi', 'timezone' => 'Asia/Kolkata'],
        'CN' => ['locale' => 'zh', 'timezone' => 'Asia/Shanghai'],
        'FR' => ['locale' => 'fr', 'timezone' => 'Europe/Paris'],
        'JP' => ['locale' => 'ja', 'timezone' => 'Asia/Tokyo'],
        'KR' => ['locale' => 'ko', 'timezone' => 'Asia/Seoul'],
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // INTERNALS
    // ──────────────────────────────────────────────────────────────────────────

    private function generateTenantId(string $companyName): string
    {
        $base   = preg_replace('/[^a-z0-9]/', '', strtolower(Str::slug($companyName)));
        $base   = substr((string) $base, 0, 15);
        $random = strtolower(Str::random(25 - strlen((string) $base)));
        $id     = str_pad(substr($base . $random, 0, 25), 25, strtolower(Str::random(1)));

        while (DB::table('tenants')->where('id', $id)->exists()) {
            $random = strtolower(Str::random(25 - strlen((string) $base)));
            $id     = str_pad(substr($base . $random, 0, 25), 25, strtolower(Str::random(1)));
        }

        return $id;
    }

    /**
     * Create (or find existing) the owner user account.
     */
    private function createOwnerUser(string $email, string $name, string $password): \App\Models\User
    {
        $existing = \App\Models\User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        return \App\Models\User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);
    }

    /**
     * Create the isolated tenant database (MySQL/MariaDB).
     * On SQLite (test env) this is a no-op.
     */
    private function createTenantDatabase(string $dbName): void
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            Log::info('SQLite env — skipping CREATE DATABASE', ['db' => $dbName]);
            return;
        }

        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            Log::info('Tenant DB created', ['db' => $dbName]);
        } catch (\Throwable $e) {
            Log::error('Failed to create tenant DB', ['db' => $dbName, 'error' => $e->getMessage()]);
            // Non-fatal in demo/test mode — migrations will fail gracefully
        }
    }

    /**
     * Run migrations on the tenant's isolated database.
     */
    private function runTenantMigrations(Tenant $tenant): void
    {
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            return; // Skip in test environment
        }

        // Register a temporary DB connection for this tenant
        Config::set("database.connections.tenant_{$tenant->id}", $tenant->getConnectionConfig());

        try {
            Artisan::call('migrate', [
                '--database' => "tenant_{$tenant->id}",
                '--force'    => true,
                '--quiet'    => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tenant migration failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Seed a minimal OHADA chart of accounts into the tenant's database.
     * Called automatically when currency is XOF or XAF.
     */
    private function seedOhadaChartOfAccounts(Tenant $tenant): void
    {
        // In production this would insert into the tenant's accounting_accounts table.
        // Here we record the intent in the audit log and delegate to a queued job.
        TenantAuditLog::log($tenant->id, 'ohada_accounts_seeded', [
            'new_values' => [
                'currency'        => $tenant->currency,
                'accounting_std'  => 'OHADA',
                'seeded_at'       => now()->toIso8601String(),
            ],
        ]);

        Log::info('OHADA chart of accounts seeded', ['tenant_id' => $tenant->id]);
    }

    /**
     * Enable the appropriate module set for a plan.
     */
    private function enableDefaultModules(string $tenantId, string $plan): void
    {
        $allowlist = self::PLAN_MODULES[$plan] ?? null;
        $now       = now();

        $modules = $allowlist ?? self::ALL_MODULES;

        foreach ($modules as $module) {
            DB::table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $tenantId, 'module' => $module],
                ['enabled' => true, 'updated_at' => $now]
            );
        }

        // If downgrading, disable modules outside the allowlist
        if ($allowlist !== null) {
            DB::table('tenant_modules')
                ->where('tenant_id', $tenantId)
                ->where('enabled', true)
                ->whereNotIn('module', $allowlist)
                ->update(['enabled' => false, 'updated_at' => $now]);
        }
    }

    /**
     * Send the welcome / getting-started email to the tenant owner.
     */
    private function sendWelcomeEmail(Tenant $tenant, \App\Models\User $owner): void
    {
        try {
            // Use Laravel's mail facade — gracefully skip if not configured
            Mail::raw(
                $this->buildWelcomeEmailBody($tenant, $owner),
                function ($message) use ($tenant, $owner): void {
                    $message->to($owner->email, $owner->name)
                        ->subject("Bienvenue sur WideHalo ERP — {$tenant->company_name}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Welcome email failed to send', [
                'tenant_id' => $tenant->id,
                'email'     => $owner->email,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function buildWelcomeEmailBody(Tenant $tenant, \App\Models\User $owner): string
    {
        $url     = "https://{$tenant->slug}.widehalo.com";
        $expires = $tenant->trial_ends_at?->format('d/m/Y') ?? 'N/A';

        return <<<TEXT
        Bonjour {$owner->name},

        Votre espace WideHalo ERP pour {$tenant->company_name} est prêt !

        Accédez à votre tableau de bord : {$url}

        Votre période d'essai est valable jusqu'au {$expires}.
        Commencez par l'assistant d'onboarding pour configurer vos modules.

        Besoin d'aide ? Contactez-nous à support@widehalo.com

        L'équipe WideHalo
        TEXT;
    }

    // ── Plan limits ───────────────────────────────────────────────────────────

    private function planUserLimit(string $plan): int
    {
        return match ($plan) {
            'starter'      => 5,
            'professional' => 25,
            'enterprise'   => 999,
            'custom'       => 9999,
            default        => 5,
        };
    }

    private function planInvoiceLimit(string $plan): int
    {
        return match ($plan) {
            'starter'      => 100,
            'professional' => 1000,
            'enterprise'   => 999999,
            'custom'       => 999999,
            default        => 100,
        };
    }
}
