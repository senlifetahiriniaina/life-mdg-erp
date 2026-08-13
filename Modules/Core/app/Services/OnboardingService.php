<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantAuditLog;
use Modules\Core\Models\TenantInvitation;

/**
 * OnboardingService — Phase 40
 *
 * Drives the 5-step onboarding wizard for new tenants.
 *
 * Step 1 — Company information (country, industry, currency, timezone)
 * Step 2 — Import legacy data (Excel/CSV/PDF) OR start from scratch
 * Step 3 — Activate modules (which of the 47 modules to enable)
 * Step 4 — Invite team members (emails + roles)
 * Step 5 — Choose automation templates (from Phase 39 Workflow Engine)
 */
class OnboardingService
{
    // ── Step labels (for status API) ──────────────────────────────────────────

    private const STEP_LABELS = [
        1 => 'Informations entreprise',
        2 => 'Import de données',
        3 => 'Activation des modules',
        4 => 'Invitation de l\'équipe',
        5 => 'Automatisations',
    ];

    private const TOTAL_STEPS = 5;

    public function __construct(
        private readonly SmartDefaultsService $smartDefaults,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 1 — Company information
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Complete step 1: persist company details and apply smart defaults.
     *
     * @param array{
     *   name?: string,
     *   legal_name?: string,
     *   company_type?: string,
     *   country_code?: string,
     *   currency?: string,
     *   locale?: string,
     *   timezone?: string,
     *   industry?: string,
     *   city?: string,
     *   region?: string,
     *   contact_phone?: string,
     *   logo_url?: string,
     *   primary_color?: string,
     * } $data
     * @return array{tenant: Tenant, next_step: int, smart_defaults: array<string,mixed>}
     */
    public function completeStep1(Tenant $tenant, array $data): array
    {
        $countryCode = strtoupper($data['country_code'] ?? $tenant->country_code ?? 'SN');
        $industry    = $data['industry'] ?? $tenant->industry ?? 'general';

        // Fetch smart defaults for the chosen country + industry
        $defaults = $this->smartDefaults->getDefaults('core', $countryCode, $industry);

        $tenant->update(array_filter([
            'name'          => $data['name'] ?? $tenant->name,
            'company_name'  => $data['name'] ?? $tenant->company_name,
            'legal_name'    => $data['legal_name'] ?? $tenant->legal_name,
            'company_type'  => $data['company_type'] ?? $tenant->company_type,
            'country_code'  => $countryCode,
            'city'          => $data['city'] ?? $tenant->city,
            'region'        => $data['region'] ?? $tenant->region,
            'currency'      => $data['currency'] ?? $defaults['currency'],
            'locale'        => $data['locale'] ?? $defaults['taxLabel'] ?? $tenant->locale,
            'timezone'      => $data['timezone'] ?? $tenant->timezone,
            'industry'      => $industry,
            'contact_phone' => $data['contact_phone'] ?? $tenant->contact_phone,
            'logo_url'      => $data['logo_url'] ?? $tenant->logo_url,
            'primary_color' => $data['primary_color'] ?? $tenant->primary_color,
        ], fn ($v) => $v !== null));

        $this->advanceStep($tenant, 1);

        TenantAuditLog::log($tenant->id, 'onboarding_step_1_completed', [
            'new_values' => ['country' => $countryCode, 'industry' => $industry],
        ]);

        return [
            'tenant'        => $tenant->fresh(),
            'next_step'     => 2,
            'smart_defaults'=> $defaults,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 2 — Import legacy data
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Complete step 2: record the import choice and optionally queue an import job.
     *
     * @param array{
     *   import_type: 'file'|'scratch'|'demo',
     *   import_job_id?: string,       When import_type=file, the ImportJob ID created by Setup module
     *   file_path?: string,
     *   file_type?: 'excel'|'csv'|'pdf',
     * } $data
     * @return array{tenant: Tenant, next_step: int, import_job_id: string|null}
     */
    public function completeStep2(Tenant $tenant, array $data): array
    {
        $importType  = $data['import_type'] ?? 'scratch';
        $importJobId = $data['import_job_id'] ?? null;

        // Persist import preference in settings
        $settings                    = $tenant->settings ?? [];
        $settings['onboarding_import'] = [
            'type'       => $importType,
            'job_id'     => $importJobId,
            'file_type'  => $data['file_type'] ?? null,
            'started_at' => now()->toIso8601String(),
        ];
        $tenant->settings = $settings;
        $tenant->save();

        $this->advanceStep($tenant, 2);

        TenantAuditLog::log($tenant->id, 'onboarding_step_2_completed', [
            'new_values' => ['import_type' => $importType, 'import_job_id' => $importJobId],
        ]);

        return [
            'tenant'        => $tenant->fresh(),
            'next_step'     => 3,
            'import_job_id' => $importJobId,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 3 — Activate modules
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Complete step 3: enable/disable the selected set of modules.
     *
     * @param list<string> $moduleKeys  e.g. ['CRM', 'HR', 'Accounting', 'Inventory']
     * @return array{tenant: Tenant, next_step: int, enabled_modules: list<string>}
     */
    public function completeStep3(Tenant $tenant, array $moduleKeys): array
    {
        $now = now();

        // Disable all first (reset), then enable selected
        DB::table('tenant_modules')
            ->where('tenant_id', $tenant->id)
            ->update(['enabled' => false, 'updated_at' => $now]);

        // Core is always enabled
        $moduleKeys = array_unique(array_merge(['Core'], $moduleKeys));

        foreach ($moduleKeys as $module) {
            DB::table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $tenant->id, 'module' => $module],
                ['enabled' => true, 'updated_at' => $now]
            );
        }

        $this->advanceStep($tenant, 3);

        TenantAuditLog::log($tenant->id, 'onboarding_step_3_completed', [
            'new_values' => ['enabled_modules' => $moduleKeys],
        ]);

        return [
            'tenant'          => $tenant->fresh(),
            'next_step'       => 4,
            'enabled_modules' => $moduleKeys,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 4 — Invite team members
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Complete step 4: send invitations to team members.
     *
     * @param list<array{email: string, role: string}> $invitations
     * @return array{tenant: Tenant, next_step: int, invited: list<string>}
     */
    public function completeStep4(Tenant $tenant, array $invitations): array
    {
        $invited = [];

        foreach ($invitations as $inv) {
            if (empty($inv['email'])) {
                continue;
            }

            TenantInvitation::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $inv['email']],
                [
                    'role'       => $inv['role'] ?? 'user',
                    'invited_by' => auth()->id(),
                ]
            );

            $invited[] = $inv['email'];
        }

        $this->advanceStep($tenant, 4);

        TenantAuditLog::log($tenant->id, 'onboarding_step_4_completed', [
            'new_values' => ['invited_count' => count($invited), 'emails' => $invited],
        ]);

        return [
            'tenant'    => $tenant->fresh(),
            'next_step' => 5,
            'invited'   => $invited,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 5 — Automation templates
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Complete step 5: activate selected Phase 39 workflow automation templates.
     *
     * @param list<string> $templateIds  e.g. ['WF-001', 'WF-005', 'WF-010']
     * @return array{tenant: Tenant, next_step: int, activated_templates: list<string>}
     */
    public function completeStep5(Tenant $tenant, array $templateIds): array
    {
        // Persist selected templates in tenant settings
        $settings                            = $tenant->settings ?? [];
        $settings['automation_templates']    = array_values(array_unique($templateIds));
        $settings['automation_activated_at'] = now()->toIso8601String();
        $tenant->settings                    = $settings;
        $tenant->save();

        // Mark onboarding as fully complete
        $tenant->update([
            'onboarding_step'        => self::TOTAL_STEPS + 1,
            'onboarding_completed_at'=> now(),
            'status'                 => $tenant->status === 'trial' ? 'trial' : 'active',
        ]);

        TenantAuditLog::log($tenant->id, 'onboarding_completed', [
            'new_values' => [
                'templates_activated' => $templateIds,
                'completed_at'        => now()->toIso8601String(),
            ],
        ]);

        Log::info('Tenant onboarding completed', ['tenant_id' => $tenant->id]);

        return [
            'tenant'              => $tenant->fresh(),
            'next_step'           => null,
            'activated_templates' => $templateIds,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STATUS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return the full onboarding status for a tenant.
     *
     * @return array{
     *   current_step: int,
     *   total_steps: int,
     *   completion_pct: int,
     *   completed: bool,
     *   steps: list<array{step: int, label: string, status: string}>,
     *   completed_at: string|null,
     * }
     */
    public function getStatus(Tenant $tenant): array
    {
        $currentStep = (int) ($tenant->onboarding_step ?? 0);
        $completed   = $currentStep > self::TOTAL_STEPS;

        $steps = [];
        for ($i = 1; $i <= self::TOTAL_STEPS; $i++) {
            if ($i < $currentStep) {
                $status = 'completed';
            } elseif ($i === $currentStep) {
                $status = 'in_progress';
            } else {
                $status = 'pending';
            }

            $steps[] = [
                'step'   => $i,
                'label'  => self::STEP_LABELS[$i] ?? "Étape {$i}",
                'status' => $status,
            ];
        }

        return [
            'current_step'  => $completed ? self::TOTAL_STEPS : $currentStep,
            'total_steps'   => self::TOTAL_STEPS,
            'completion_pct'=> $this->getCompletionPercentage($tenant),
            'completed'     => $completed,
            'steps'         => $steps,
            'completed_at'  => $tenant->onboarding_completed_at?->toIso8601String(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SKIP
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Skip remaining onboarding steps and go directly to the dashboard.
     * Marks onboarding as complete with the current step.
     */
    public function skip(Tenant $tenant): void
    {
        $tenant->update([
            'onboarding_step'        => self::TOTAL_STEPS + 1,
            'onboarding_completed_at'=> now(),
        ]);

        TenantAuditLog::log($tenant->id, 'onboarding_skipped', [
            'new_values' => ['skipped_at_step' => $tenant->onboarding_step],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // COMPLETION PERCENTAGE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Calculate onboarding completion percentage (0–100).
     * Used by the dashboard widget.
     */
    public function getCompletionPercentage(Tenant $tenant): int
    {
        $step = (int) ($tenant->onboarding_step ?? 0);

        if ($step > self::TOTAL_STEPS) {
            return 100;
        }

        return (int) round(($step / self::TOTAL_STEPS) * 100);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INTERNAL
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Advance the onboarding step counter (only moves forward, never backward).
     */
    private function advanceStep(Tenant $tenant, int $completedStep): void
    {
        $newStep = $completedStep + 1;

        if ((int) $tenant->onboarding_step < $newStep) {
            $tenant->onboarding_step = $newStep;
            $tenant->save();
        }
    }
}
