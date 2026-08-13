<?php

declare(strict_types=1);

namespace Modules\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HR → Payroll Workflow Chain Seeder
 *
 * Seeds workflow definitions WF-010 to WF-015 for the HR→Payroll chain.
 * Uses the workflow_chain_definitions table (created in Phase 39 migration).
 *
 * Chain: RH → Paie
 * Covers: leave adjustments, onboarding enrollment, overtime, contract alerts,
 *         offboarding access revocation, payroll anomaly notifications.
 */
class HrPayrollWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1; // Default/demo tenant

        $workflows = [
            // WF-010 — Congé approuvé → Ajustement paie
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-010 — Congé approuvé → Ajustement paie',
                'description'    => 'Lorsqu\'un congé sans solde est approuvé, calcule et enregistre automatiquement l\'ajustement de paie pour la période concernée.',
                'trigger_key'    => 'hr.leave_approved',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'leave_type', 'operator' => '==', 'value' => 'unpaid'],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'payroll.adjust_for_leave',
                        'order'      => 1,
                        'params'     => ['sick_grace_days' => 3],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],

            // WF-011 — Onboarding complété → Inscription paie + Accès IT
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-011 — Onboarding complété → Inscription paie + Accès IT',
                'description'    => 'À la fin du processus d\'onboarding, crée automatiquement le profil de paie de l\'employé et provisionne son accès système.',
                'trigger_key'    => 'hr.onboarding_completed',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'always', 'operator' => '==', 'value' => true],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'payroll.enroll_new_employee',
                        'order'      => 1,
                        'params'     => [],
                    ],
                    [
                        'action_key' => 'it.provision_access',
                        'order'      => 2,
                        'params'     => [],
                    ],
                    [
                        'action_key' => 'notify.in_app',
                        'order'      => 3,
                        'params'     => [
                            'message'  => 'Onboarding terminé : profil paie créé et accès IT provisionné.',
                            'severity' => 'success',
                        ],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],

            // WF-012 — Heures supplémentaires validées → Ajout paie
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-012 — Heures supplémentaires validées → Ajout paie',
                'description'    => 'Lorsque des heures supplémentaires sont validées par le responsable, ajoute automatiquement la prime correspondante au bulletin de paie du mois.',
                'trigger_key'    => 'hr.overtime_validated',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'hours', 'operator' => '>', 'value' => 0],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'payroll.add_overtime',
                        'order'      => 1,
                        'params'     => [],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],

            // WF-013 — Contrat expire dans 30 jours → Alerte RH
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-013 — Contrat expire dans 30 jours → Alerte RH',
                'description'    => 'Vérification planifiée quotidienne : envoie une alerte au responsable RH lorsqu\'un contrat expire dans 30, 15 ou 7 jours.',
                'trigger_key'    => 'hr.contract_expiry_check',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'days_until_expiry', 'operator' => '<=', 'value' => 30],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'hr.notify_contract_expiry',
                        'order'      => 1,
                        'params'     => ['alert_days' => [30, 15, 7]],
                    ],
                    [
                        'action_key' => 'notify.email',
                        'order'      => 2,
                        'params'     => [
                            'template'    => 'hr.contract_expiry_alert',
                            'recipient'   => 'hr_manager',
                        ],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],

            // WF-014 — Employé quitte → Révocation accès IT + Solde paie
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-014 — Employé quitte → Révocation accès IT + Solde paie',
                'description'    => 'Lors du départ d\'un employé (démission, licenciement, fin de contrat), révoque immédiatement les accès système et calcule le solde de tout compte.',
                'trigger_key'    => 'hr.employee_offboarded',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'always', 'operator' => '==', 'value' => true],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'it.revoke_access',
                        'order'      => 1,
                        'params'     => ['immediate' => true],
                    ],
                    [
                        'action_key' => 'payroll.calculate_final_settlement',
                        'order'      => 2,
                        'params'     => [],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],

            // WF-015 — Anomalie paie détectée IA → Alerte manager
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-015 — Anomalie paie détectée IA → Alerte manager',
                'description'    => 'Lorsque le moteur IA détecte une anomalie sur la paie (montant inhabituel, doublon, dérive de tendance), alerte le responsable paie et le manager de l\'employé.',
                'trigger_key'    => 'hr.payroll_anomaly_detected',
                'trigger_module' => 'HR',
                'conditions'     => json_encode([
                    ['field' => 'severity', 'operator' => 'in', 'value' => ['warning', 'critical']],
                ]),
                'actions' => json_encode([
                    [
                        'action_key' => 'notify.in_app',
                        'order'      => 1,
                        'params'     => [
                            'message'  => 'Anomalie paie détectée : veuillez vérifier le bulletin.',
                            'severity' => 'warning',
                            'target'   => 'payroll_manager',
                        ],
                    ],
                    [
                        'action_key' => 'notify.email',
                        'order'      => 2,
                        'params'     => [
                            'template'  => 'hr.payroll_anomaly_alert',
                            'recipient' => 'payroll_manager',
                        ],
                    ],
                ]),
                'is_active'      => true,
                'execution_count' => 0,
                'last_executed_at' => null,
            ],
        ];

        // Upsert by tenant_id + trigger_key to avoid duplicate seeding
        foreach ($workflows as $workflow) {
            DB::table('workflow_chain_definitions')->updateOrInsert(
                [
                    'tenant_id'   => $workflow['tenant_id'],
                    'trigger_key' => $workflow['trigger_key'],
                ],
                array_merge($workflow, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command?->info('✓ HR→Payroll workflows seeded (WF-010 to WF-015).');
    }
}
