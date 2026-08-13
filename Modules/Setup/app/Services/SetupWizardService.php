<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Drives the onboarding wizard.
 *
 * State is kept per tenant in the cache so the multi-step flow can be resumed.
 * Each `save*` step merges its slice into the stored state; `complete()`
 * finalises and returns the assembled company profile.
 */
class SetupWizardService
{
    private const TTL = 86400; // 24h

    private function key(string $tenantId): string
    {
        return "setup:wizard:{$tenantId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(string $tenantId): array
    {
        return Cache::get($this->key($tenantId), [
            'step'      => 0,
            'company'   => null,
            'admin'     => null,
            'modules'   => [],
            'workflows' => [],
            'apps'      => [],
            'completed' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function merge(string $tenantId, array $data): array
    {
        $state = array_merge($this->getState($tenantId), $data);
        Cache::put($this->key($tenantId), $state, self::TTL);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveCompany(string $tenantId, array $data): array
    {
        $this->merge($tenantId, ['company' => $data, 'step' => 1]);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAdmin(string $tenantId, array $data, ?int $userId = null): void
    {
        $this->merge($tenantId, ['admin' => $data + ['user_id' => $userId], 'step' => 2]);
    }

    /**
     * @param  array<int, mixed>  $modules
     */
    public function saveModules(string $tenantId, array $modules): void
    {
        $this->merge($tenantId, ['modules' => $modules, 'step' => 3]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveWorkflows(string $tenantId, array $data): void
    {
        $this->merge($tenantId, ['workflows' => $data, 'step' => 4]);
    }

    /**
     * @param  array<int, mixed>  $apps
     */
    public function saveApps(string $tenantId, array $apps): void
    {
        $this->merge($tenantId, ['apps' => $apps, 'step' => 5]);
    }

    /**
     * Finalise the wizard and return the assembled company profile.
     *
     * @return array<string, mixed>
     */
    public function complete(string $tenantId): array
    {
        $state = $this->getState($tenantId);

        if (empty($state['company'])) {
            throw new RuntimeException('Company profile not found; complete step 1 first.');
        }

        $state['completed'] = true;
        $state['step'] = 6;
        Cache::put($this->key($tenantId), $state, self::TTL);

        return $state['company'] + ['onboarding_completed' => true];
    }
}
