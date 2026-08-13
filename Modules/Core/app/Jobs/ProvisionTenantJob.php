<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Database\Seeders\TenantDefaultSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs tenant database migrations and seeds reference data after tenant creation.
 * Dispatched asynchronously so the registration HTTP response stays fast.
 *
 * Special Case: ProvisionTenantJob creates the tenant/company itself, so it may not have
 * a valid company_id at construction time. We pass 0 as a placeholder; the actual company_id
 * would be created during tenant provisioning if needed for logging.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $tenantId, public readonly ?int $companyId = null)
    {}

    public function handle(): void
    {
        try {
            Log::info('[Tenant] Starting provisioning', ['tenant_id' => $this->tenantId]);

            Artisan::call('tenants:migrate', [
                '--tenants' => [$this->tenantId],
                '--force' => true,
            ]);

            Log::info('[Tenant] Migrations complete, seeding reference data', ['tenant_id' => $this->tenantId]);

            Artisan::call('tenants:seed', [
                '--tenants' => [$this->tenantId],
                '--class' => TenantDefaultSeeder::class,
                '--force' => true,
            ]);

            Log::info('[Tenant] Provisioning complete', ['tenant_id' => $this->tenantId]);
        } catch (\Throwable $e) {
            Log::error('[Tenant] Provisioning failed', [
                'tenant_id' => $this->tenantId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
