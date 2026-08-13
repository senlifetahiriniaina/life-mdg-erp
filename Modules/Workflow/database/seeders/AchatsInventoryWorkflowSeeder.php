<?php

declare(strict_types=1);

namespace Modules\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflow\Models\WorkflowChainDefinition;

/**
 * Achats → Inventory → Accounting Workflow Chain Seeder
 *
 * Seeds WF-005 to WF-009 — the five pre-configured workflow definitions that
 * automate the purchase-to-stock-to-accounting cycle, including OHADA-compliant
 * approval thresholds.
 *
 * Run via:
 *   php artisan db:seed --class="Modules\Workflow\Database\Seeders\AchatsInventoryWorkflowSeeder"
 *
 * All definitions are seeded for tenant_id = 1 (default/demo tenant).
 * Adjust tenant_id or extract to a loop for multi-tenant seeds.
 */
class AchatsInventoryWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions as $data) {
            WorkflowChainDefinition::updateOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }

        $this->command->info(sprintf(
            'AchatsInventoryWorkflowSeeder: %d workflow definition(s) seeded (WF-005 → WF-009).',
            count($definitions),
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    private function definitions(): array
    {
        return [
            // ── WF-005 ────────────────────────────────────────────────────────────
            [
                'tenant_id'      => 1,
                'name'           => 'WF-005 — Stock sous seuil → Créer BC automatique',
                'description'    => 'Déclenché quand le stock d\'un produit passe sous son point de réappro. '
                                  . 'Crée automatiquement un bon de commande auprès du fournisseur préféré '
                                  . 'si la récommande automatique est activée.',
                'trigger_key'    => 'inventory.stock_below_reorder_point',
                'trigger_module' => 'Inventory',
                'conditions'     => [
                    [
                        'field'    => 'auto_reorder',
                        'operator' => 'equals',
                        'value'    => true,
                    ],
                    [
                        'field'    => 'preferred_supplier_id',
                        'operator' => 'not_equals',
                        'value'    => null,
                    ],
                ],
                'actions'        => [
                    [
                        'action_key' => 'achats.auto_create_po',
                        'params'     => [],
                        'critical'   => true,
                    ],
                    [
                        'action_key' => 'notify.in_app',
                        'params'     => [
                            'to'             => 'purchasing_manager',
                            'title_template' => 'BC automatique créé — produit {{product_id}}',
                            'body_template'  => 'Stock actuel : {{current_stock}} / Seuil : {{reorder_point}}. Un bon de commande a été généré automatiquement.',
                            'type'           => 'info',
                            'module'         => 'Achats',
                        ],
                        'critical'   => false,
                    ],
                ],
                'is_active'      => true,
            ],

            // ── WF-006 ────────────────────────────────────────────────────────────
            [
                'tenant_id'      => 1,
                'name'           => 'WF-006 — BC reçu → Mise à jour stock + écriture comptable',
                'description'    => 'Déclenché à la réception d\'un bon de commande. '
                                  . 'Met à jour les niveaux de stock dans l\'entrepôt cible, '
                                  . 'puis enregistre la variation de stock en comptabilité (OHADA Cl.3/Cl.6).',
                'trigger_key'    => 'achats.po_received',
                'trigger_module' => 'Achats',
                'conditions'     => [
                    [
                        'operator' => 'always',
                    ],
                ],
                'actions'        => [
                    [
                        'action_key' => 'inventory.receive_purchase_order',
                        'params'     => [],
                        'critical'   => true,
                    ],
                    [
                        'action_key' => 'accounting.book_stock_variation',
                        'params'     => [],
                        'critical'   => false,
                    ],
                    [
                        'action_key' => 'notify.in_app',
                        'params'     => [
                            'to'             => 'warehouse_manager',
                            'title_template' => 'Réception BC #{{po_id}} enregistrée',
                            'body_template'  => 'Stock mis à jour pour {{item_count}} article(s). Écriture comptable de variation de stock créée.',
                            'type'           => 'success',
                            'module'         => 'Inventory',
                        ],
                        'critical'   => false,
                    ],
                ],
                'is_active'      => true,
            ],

            // ── WF-007 ────────────────────────────────────────────────────────────
            [
                'tenant_id'      => 1,
                'name'           => 'WF-007 — Facture fournisseur reçue → Comptabilisation OHADA',
                'description'    => 'Déclenché à la réception d\'une facture fournisseur. '
                                  . 'Enregistre l\'écriture comptable OHADA (Débit Cl.6 / Crédit Cl.4 — Fournisseurs). '
                                  . 'S\'applique à toutes les factures sans condition de montant.',
                'trigger_key'    => 'achats.invoice_received',
                'trigger_module' => 'Achats',
                'conditions'     => [
                    [
                        'operator' => 'always',
                    ],
                ],
                'actions'        => [
                    [
                        'action_key' => 'accounting.book_purchase_invoice',
                        'params'     => [],
                        'critical'   => true,
                    ],
                ],
                'is_active'      => true,
            ],

            // ── WF-008 ────────────────────────────────────────────────────────────
            [
                'tenant_id'      => 1,
                'name'           => 'WF-008 — Facture > 100 000 XOF → Approbation 1 niveau',
                'description'    => 'Déclenché à la réception d\'une facture fournisseur dont le montant '
                                  . 'dépasse 100 000 XOF (seuil OHADA/UEMOA niveau 1). '
                                  . 'Soumet la facture à l\'approbation du manager direct avant paiement.',
                'trigger_key'    => 'achats.invoice_received',
                'trigger_module' => 'Achats',
                'conditions'     => [
                    [
                        'field'    => 'amount',
                        'operator' => 'greater_than',
                        'value'    => 100000,
                    ],
                ],
                'actions'        => [
                    [
                        'action_key' => 'accounting.flag_for_approval',
                        'params'     => [
                            'approvers' => ['manager'],
                        ],
                        'critical'   => true,
                    ],
                    [
                        'action_key' => 'notify.email',
                        'params'     => [
                            'to'               => 'manager',
                            'subject_template' => '[Approbation requise] Facture {{invoice_id}} — {{amount}} XOF',
                            'body_template'    => "Bonjour,\n\nUne facture fournisseur nécessite votre approbation :\n"
                                               . "- Facture n° : {{invoice_id}}\n"
                                               . "- Montant : {{amount}} {{currency}}\n"
                                               . "- Fournisseur : {{supplier_id}}\n\n"
                                               . "Merci de vous connecter à WideHalo ERP pour approuver ou rejeter.",
                        ],
                        'critical'   => false,
                    ],
                ],
                'is_active'      => true,
            ],

            // ── WF-009 ────────────────────────────────────────────────────────────
            [
                'tenant_id'      => 1,
                'name'           => 'WF-009 — Facture > 500 000 XOF → Approbation 3 niveaux + échéancier',
                'description'    => 'Déclenché à la réception d\'une facture fournisseur dont le montant '
                                  . 'dépasse 500 000 XOF (seuil OHADA/UEMOA niveau 3). '
                                  . 'Lance un circuit d\'approbation 3 niveaux (Manager → DAF → DG) '
                                  . 'ET génère un échéancier de paiement en 3 versements mensuels.',
                'trigger_key'    => 'achats.invoice_received',
                'trigger_module' => 'Achats',
                'conditions'     => [
                    [
                        'field'    => 'amount',
                        'operator' => 'greater_than',
                        'value'    => 500000,
                    ],
                ],
                'actions'        => [
                    [
                        'action_key' => 'accounting.flag_for_approval',
                        'params'     => [
                            'levels'    => 3,
                            'approvers' => ['manager', 'daf', 'dg'],
                        ],
                        'critical'   => true,
                    ],
                    [
                        'action_key' => 'accounting.generate_payment_schedule',
                        'params'     => [
                            'installments'  => 3,
                            'interval_days' => 30,
                        ],
                        'critical'   => false,
                    ],
                    [
                        'action_key' => 'notify.email',
                        'params'     => [
                            'to'               => 'dg',
                            'subject_template' => '[URGENT — Approbation DG] Facture {{invoice_id}} — {{amount}} XOF',
                            'body_template'    => "Bonjour,\n\nUne facture importante nécessite une approbation à 3 niveaux :\n"
                                               . "- Facture n° : {{invoice_id}}\n"
                                               . "- Montant : {{amount}} {{currency}} (> seuil OHADA 500 000 XOF)\n"
                                               . "- Fournisseur : {{supplier_id}}\n\n"
                                               . "Un échéancier de paiement en 3 versements a été automatiquement généré.\n\n"
                                               . "Merci de vous connecter à WideHalo ERP pour traiter ce dossier.",
                        ],
                        'critical'   => false,
                    ],
                    [
                        'action_key' => 'notify.in_app',
                        'params'     => [
                            'to'             => ['manager', 'daf', 'dg'],
                            'title_template' => '[Circuit d\'approbation] Facture {{invoice_id}} — {{amount}} XOF',
                            'body_template'  => 'Facture fournisseur > 500 000 XOF soumise au circuit d\'approbation 3 niveaux. Échéancier 3 × 30 jours créé.',
                            'type'           => 'warning',
                            'module'         => 'Achats',
                            'action_url'     => '/achats/invoices/{{invoice_id}}',
                        ],
                        'critical'   => false,
                    ],
                ],
                'is_active'      => true,
            ],
        ];
    }
}
