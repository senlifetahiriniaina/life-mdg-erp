<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Settings\Services\SettingsService;

/**
 * Chantier 37 — registre de « rôles de compte comptable » configurables,
 * suite directe du remap Chantier 36. Chaque rôle représente un concept
 * métier (« le compte de trésorerie par défaut », « le compte clients »,
 * ...) dont le code exact était jusqu'ici codé en dur dans 4 services
 * (Sales/Achats/Payroll/Accounting) — ce service centralise la résolution
 * du code réellement utilisé, avec un override par tenant persisté via
 * Modules\Settings (module 'accounting', clé 'account_role.<role>').
 *
 * DEFAULT_ROLES::default_code mirrors exactly what Chantier 36 just
 * hardcoded at each call site — a tenant that never customizes anything
 * sees zero behavior change. Same static-fallback-table precedent as
 * Modules\Core\Services\SmartDefaultsService::COUNTRIES.
 *
 * resolveAccount() throws rather than silently degrading when a code is
 * unresolvable — matching the Chantier 22 precedent already established in
 * SalesDepositService/PurchaseDepositService (a "successful" journal
 * posting with no real account behind it is worse than an explicit
 * failure; this is a financial posting integrity concern, not a UX
 * fallback-first case).
 */
class AccountRoleService
{
    private const DEFAULT_ROLES = [
        'default_treasury_account' => [
            'label' => 'Compte de trésorerie par défaut',
            'default_code' => '52',
        ],
        'default_clients_account' => [
            'label' => 'Compte clients par défaut',
            'default_code' => '41',
        ],
        'avances_recues_clients' => [
            'label' => 'Avances reçues des clients',
            'default_code' => '419',
        ],
        'default_suppliers_account' => [
            'label' => 'Compte fournisseurs par défaut',
            'default_code' => '40',
        ],
        'avances_versees_fournisseurs' => [
            'label' => 'Avances versées aux fournisseurs',
            'default_code' => '4091',
        ],
        'personnel_remuneration_expense' => [
            'label' => 'Charge de rémunération du personnel',
            'default_code' => '661',
        ],
        'salary_payable_liability' => [
            'label' => 'Salaires à payer',
            'default_code' => '422',
        ],
        'irsa_withholding_liability' => [
            'label' => 'Retenue IRSA à reverser',
            'default_code' => '4471',
        ],
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function resolveAccount(string $role): ChartOfAccount
    {
        $this->assertKnownRole($role);

        $code = (string) $this->settings->get(
            'accounting',
            "account_role.{$role}",
            self::DEFAULT_ROLES[$role]['default_code']
        );

        $account = ChartOfAccount::where('code', $code)->where('is_active', true)->first();

        if ($account === null) {
            throw new \RuntimeException(
                "Compte comptable introuvable ou inactif pour le rôle « {$role} » (code {$code}). "
                . 'Vérifiez le paramétrage Comptabilité > Comptes de rôle.'
            );
        }

        return $account;
    }

    public function setRole(string $role, string $code): void
    {
        $this->assertKnownRole($role);

        if (! ChartOfAccount::where('code', $code)->where('is_active', true)->exists()) {
            throw new \InvalidArgumentException("Le code comptable {$code} ne correspond à aucun compte actif.");
        }

        $this->settings->set('accounting', "account_role.{$role}", $code);
    }

    /**
     * @return list<array{role: string, label: string, default_code: string, resolved_code: string, resolved_account_id: ?int, resolved_account_name: ?string, is_customized: bool, is_resolvable: bool}>
     */
    public function listRoles(): array
    {
        $result = [];

        foreach (self::DEFAULT_ROLES as $role => $meta) {
            $override = $this->settings->get('accounting', "account_role.{$role}");
            $code = $override !== null ? (string) $override : $meta['default_code'];
            $account = ChartOfAccount::where('code', $code)->where('is_active', true)->first();

            $result[] = [
                'role' => $role,
                'label' => $meta['label'],
                'default_code' => $meta['default_code'],
                'resolved_code' => $code,
                'resolved_account_id' => $account?->id,
                'resolved_account_name' => $account?->name,
                'is_customized' => $override !== null && (string) $override !== $meta['default_code'],
                'is_resolvable' => $account !== null,
            ];
        }

        return $result;
    }

    private function assertKnownRole(string $role): void
    {
        if (! array_key_exists($role, self::DEFAULT_ROLES)) {
            throw new \InvalidArgumentException("Unknown account role: {$role}.");
        }
    }
}
