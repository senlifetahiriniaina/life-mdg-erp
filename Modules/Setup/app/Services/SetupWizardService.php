<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use App\Http\Controllers\Admin\RoleManagementController;
use App\Models\User;
use Modules\Setup\Models\CompanyProfile;
use RuntimeException;

/**
 * Drives the onboarding wizard.
 *
 * State used to live only in the cache (Cache::put, 24h TTL) — lost on
 * flush/restart and leaving no durable onboarding record. Now persisted on
 * CompanyProfile: the "company" step maps to its direct columns, steps
 * 2/3/4/5 to its admin_profile/modules_selected/workflows_config/
 * apps_config json columns.
 */
class SetupWizardService
{
    /**
     * @return array<string, mixed>
     */
    public function getState(string $tenantId): array
    {
        $profile = CompanyProfile::forTenant($tenantId)->first();

        if (! $profile) {
            return [
                'step'      => 0,
                'company'   => null,
                'admin'     => null,
                'modules'   => [],
                'workflows' => [],
                'apps'      => [],
                'completed' => false,
            ];
        }

        return [
            'step'      => $this->currentStep($profile),
            'company'   => $profile->only([
                'company_name', 'legal_name', 'company_type', 'industry',
                'country_code', 'currency_code', 'timezone', 'fiscal_year_start',
                'phone', 'email', 'website', 'address', 'city', 'postal_code', 'vat_number',
            ]),
            'admin'     => $profile->admin_profile,
            'modules'   => $profile->modules_selected ?? [],
            'workflows' => $profile->workflows_config ?? [],
            'apps'      => $profile->apps_config ?? [],
            'completed' => $profile->onboarding_completed,
        ];
    }

    private function currentStep(CompanyProfile $profile): int
    {
        return match (true) {
            $profile->onboarding_completed => 6,
            $profile->apps_config !== null => 5,
            $profile->workflows_config !== null => 4,
            $profile->modules_selected !== null => 3,
            $profile->admin_profile !== null => 2,
            default => 1,
        };
    }

    private function requireProfile(string $tenantId): CompanyProfile
    {
        $profile = CompanyProfile::forTenant($tenantId)->first();

        if (! $profile) {
            throw new RuntimeException('Company profile not found; complete step 1 first.');
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCompany(string $tenantId, array $data): CompanyProfile
    {
        return CompanyProfile::updateOrCreate(['tenant_id' => $tenantId], $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAdmin(string $tenantId, array $data, ?int $userId = null): void
    {
        $profile = $this->requireProfile($tenantId);
        $profile->update(['admin_profile' => $data + ['user_id' => $userId]]);

        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'locale' => $data['locale'] ?? null,
            'timezone' => $data['timezone'] ?? null,
        ], fn ($value) => $value !== null));

        if (! $user->hasAnyRole(['admin', 'super-admin'])) {
            RoleManagementController::assignRoleToUser($user, 'admin');
        }
    }

    /**
     * @param  array<int, mixed>  $modules
     */
    public function saveModules(string $tenantId, array $modules): void
    {
        $this->requireProfile($tenantId)->update(['modules_selected' => $modules]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveWorkflows(string $tenantId, array $data): void
    {
        $this->requireProfile($tenantId)->update(['workflows_config' => $data]);
    }

    /**
     * @param  array<int, mixed>  $apps
     */
    public function saveApps(string $tenantId, array $apps): void
    {
        $this->requireProfile($tenantId)->update(['apps_config' => $apps]);
    }

    /**
     * Finalise the wizard and return the assembled company profile.
     *
     * @return array<string, mixed>
     */
    public function complete(string $tenantId): array
    {
        $profile = $this->requireProfile($tenantId);

        $profile->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);

        return $profile->fresh()->toArray();
    }
}
