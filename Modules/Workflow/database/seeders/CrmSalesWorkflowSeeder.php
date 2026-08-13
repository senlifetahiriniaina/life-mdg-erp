<?php

declare(strict_types=1);

namespace Modules\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflow\Models\WorkflowChainDefinition;

/**
 * CrmSalesWorkflowSeeder — Phase 39
 *
 * Seeds four pre-configured workflow definitions for the CRM→Sales→Manufacturing chain.
 *
 *  WF-001  Opportunité gagnée → Créer commande
 *  WF-002  Devis approuvé → Confirmer commande + Réserver stock
 *  WF-003  Commande confirmée → Créer ordre de production (si BOM)
 *  WF-004  Commande > 500K XOF → Approbation 3 niveaux
 */
class CrmSalesWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1; // Default tenant — adapt per environment

        $definitions = [
            // ── WF-001: Opportunité gagnée → Créer commande ────────────────────
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-001 : Opportunité gagnée → Créer commande',
                'description'    => 'Crée automatiquement une commande de vente lorsqu\'une opportunité CRM est marquée comme gagnée. Notifie l\'équipe commerciale.',
                'trigger_key'    => 'crm.opportunity.won',
                'trigger_module' => 'CRM',
                'conditions'     => [
                    ['field' => 'amount', 'operator' => 'greater_than', 'value' => 0],
                ],
                'actions' => [
                    [
                        'action_key' => 'sales.create_order_from_opportunity',
                        'params'     => ['status' => 'draft'],
                        'critical'   => true,
                        'label'      => 'Créer la commande de vente',
                    ],
                    [
                        'action_key' => 'sales.notify_sales_team',
                        'params'     => ['message' => 'Opportunité gagnée ! Une commande a été créée automatiquement.'],
                        'critical'   => false,
                        'label'      => 'Notifier l\'équipe commerciale',
                    ],
                ],
                'is_active'       => true,
                'execution_count' => 0,
            ],

            // ── WF-002: Devis approuvé → Confirmer commande + Réserver stock ───
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-002 : Devis approuvé → Confirmer commande + Réserver stock',
                'description'    => 'Lorsqu\'un devis commercial est approuvé, confirme la commande associée et crée une réservation de stock dans l\'entrepôt par défaut.',
                'trigger_key'    => 'sales.quote.approved',
                'trigger_module' => 'Sales',
                'conditions'     => [
                    ['operator' => 'always'],
                ],
                'actions' => [
                    [
                        'action_key' => 'sales.confirm_sales_order',
                        'params'     => [],
                        'critical'   => true,
                        'label'      => 'Confirmer la commande de vente',
                    ],
                    [
                        'action_key' => 'inventory.reserve_stock',
                        'params'     => ['warehouse_id' => 1],
                        'critical'   => false,
                        'label'      => 'Réserver le stock en entrepôt',
                    ],
                ],
                'is_active'       => true,
                'execution_count' => 0,
            ],

            // ── WF-003: Commande confirmée → Créer ordre de production ─────────
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-003 : Commande confirmée → Créer ordre de production',
                'description'    => 'Déclenche la création d\'un ordre de fabrication lorsqu\'une commande confirmée porte un produit avec une nomenclature (BOM) et une quantité positive.',
                'trigger_key'    => 'sales.order.confirmed',
                'trigger_module' => 'Sales',
                'conditions'     => [
                    ['field' => 'has_bom',  'operator' => 'equals',       'value' => true],
                    ['field' => 'quantity', 'operator' => 'greater_than',  'value' => 0],
                ],
                'actions' => [
                    [
                        'action_key' => 'manufacturing.create_production_order',
                        'params'     => ['priority' => 'normal'],
                        'critical'   => true,
                        'label'      => 'Créer l\'ordre de fabrication',
                    ],
                    [
                        'action_key' => 'manufacturing.schedule_production',
                        'params'     => ['start_offset_days' => 2],
                        'critical'   => false,
                        'label'      => 'Planifier la production',
                    ],
                ],
                'is_active'       => true,
                'execution_count' => 0,
            ],

            // ── WF-004: Commande > 500K XOF → Approbation 3 niveaux ───────────
            [
                'tenant_id'      => $tenantId,
                'name'           => 'WF-004 : Commande > 500 000 XOF → Approbation 3 niveaux',
                'description'    => 'Pour toute commande dont le montant total dépasse 500 000 XOF, déclenche un circuit d\'approbation à 3 niveaux (responsable commercial → directeur → DG) conformément aux règles OHADA.',
                'trigger_key'    => 'sales.order.created',
                'trigger_module' => 'Sales',
                'conditions'     => [
                    ['field' => 'total_amount', 'operator' => 'greater_than', 'value' => 500000],
                ],
                'actions' => [
                    [
                        'action_key' => 'approval.request_multi_level',
                        'params'     => [
                            'levels'    => 3,
                            'approvers' => ['sales_manager', 'director', 'ceo'],
                            'entity_type' => 'sales_order',
                        ],
                        'critical'   => true,
                        'label'      => 'Demander une approbation multi-niveaux',
                    ],
                    [
                        'action_key' => 'notify.manager',
                        'params'     => [
                            'recipient' => 'manager',
                            'channel'   => 'email',
                            'message'   => 'Une commande dépasse 500 000 XOF et requiert votre approbation.',
                        ],
                        'critical'   => false,
                        'label'      => 'Notifier le responsable',
                    ],
                ],
                'is_active'       => true,
                'execution_count' => 0,
            ],
        ];

        foreach ($definitions as $data) {
            WorkflowChainDefinition::updateOrCreate(
                [
                    'tenant_id'   => $data['tenant_id'],
                    'trigger_key' => $data['trigger_key'],
                    'name'        => $data['name'],
                ],
                $data
            );
        }

        $this->command->info('CrmSalesWorkflowSeeder: seeded 4 workflow definitions (WF-001 → WF-004).');
    }
}
