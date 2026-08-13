<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\WorkflowDefinition;

/**
 * Seeds 10 workflow definitions covering the main ERP modules.
 */
class WorkflowDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            WorkflowDefinition::updateOrCreate(
                [
                    'module'        => $definition['module'],
                    'resource_type' => $definition['resource_type'],
                ],
                $definition
            );
        }
    }

    // ─── Definitions ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // ── 1. Manufacturing / WorkOrder ──────────────────────────────────
            [
                'name'          => 'Ordre de fabrication',
                'module'        => 'manufacturing',
                'resource_type' => 'WorkOrder',
                'is_active'     => true,
                'steps'         => [
                    [
                        'key'          => 'draft',
                        'label'        => 'Draft',
                        'label_fr'     => 'Brouillon',
                        'color'        => '#6B7280',
                        'icon'         => 'pi pi-file',
                        'alert_note'   => null,
                        'is_terminal'  => false,
                        'allowed_roles' => [],
                    ],
                    [
                        'key'          => 'confirmed',
                        'label'        => 'Confirmed',
                        'label_fr'     => 'Confirmé',
                        'color'        => '#3B82F6',
                        'icon'         => 'pi pi-check-circle',
                        'alert_note'   => 'Vérifier les matières premières avant confirmation.',
                        'is_terminal'  => false,
                        'allowed_roles' => [],
                    ],
                    [
                        'key'          => 'in_progress',
                        'label'        => 'In Progress',
                        'label_fr'     => 'En cours',
                        'color'        => '#F97316',
                        'icon'         => 'pi pi-spin pi-cog',
                        'alert_note'   => null,
                        'is_terminal'  => false,
                        'allowed_roles' => [],
                    ],
                    [
                        'key'          => 'quality_check',
                        'label'        => 'Quality Check',
                        'label_fr'     => 'Contrôle qualité',
                        'color'        => '#8B5CF6',
                        'icon'         => 'pi pi-search',
                        'alert_note'   => 'Inspecter le produit selon les critères qualité définis.',
                        'is_terminal'  => false,
                        'allowed_roles' => [],
                    ],
                    [
                        'key'          => 'completed',
                        'label'        => 'Completed',
                        'label_fr'     => 'Terminé',
                        'color'        => '#10B981',
                        'icon'         => 'pi pi-check',
                        'alert_note'   => null,
                        'is_terminal'  => true,
                        'allowed_roles' => [],
                    ],
                    [
                        'key'          => 'cancelled',
                        'label'        => 'Cancelled',
                        'label_fr'     => 'Annulé',
                        'color'        => '#EF4444',
                        'icon'         => 'pi pi-times-circle',
                        'alert_note'   => null,
                        'is_terminal'  => true,
                        'allowed_roles' => [],
                    ],
                ],
                'transitions' => [
                    ['from' => 'draft',         'to' => 'confirmed',     'label' => 'Confirm',       'label_fr' => 'Confirmer',         'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'confirmed',     'to' => 'in_progress',   'label' => 'Start',         'label_fr' => 'Démarrer',          'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'in_progress',   'to' => 'quality_check', 'label' => 'Send to QC',    'label_fr' => 'Envoyer au CQ',     'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'quality_check', 'to' => 'completed',     'label' => 'Approve',       'label_fr' => 'Approuver',         'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'quality_check', 'to' => 'in_progress',   'label' => 'Send back',     'label_fr' => 'Renvoyer en prod',  'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'draft',         'to' => 'cancelled',     'label' => 'Cancel',        'label_fr' => 'Annuler',           'requires_approval' => false, 'allowed_roles' => ['admin', 'manager']],
                    ['from' => 'confirmed',     'to' => 'cancelled',     'label' => 'Cancel',        'label_fr' => 'Annuler',           'requires_approval' => false, 'allowed_roles' => ['admin', 'manager']],
                ],
            ],

            // ── 2. Manufacturing / ProductionOrder ────────────────────────────
            [
                'name'          => 'Ordre de production',
                'module'        => 'manufacturing',
                'resource_type' => 'ProductionOrder',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'draft',     'label' => 'Draft',     'label_fr' => 'Brouillon',  'color' => '#6B7280', 'icon' => 'pi pi-file',            'alert_note' => null,                                             'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'planned',   'label' => 'Planned',   'label_fr' => 'Planifié',   'color' => '#3B82F6', 'icon' => 'pi pi-calendar',        'alert_note' => 'Vérifier la disponibilité des ressources.',       'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'launched',  'label' => 'Launched',  'label_fr' => 'Lancé',      'color' => '#F97316', 'icon' => 'pi pi-play',            'alert_note' => null,                                             'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'completed', 'label' => 'Completed', 'label_fr' => 'Terminé',    'color' => '#10B981', 'icon' => 'pi pi-check',           'alert_note' => null,                                             'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'draft',    'to' => 'planned',   'label' => 'Plan',     'label_fr' => 'Planifier', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'planned',  'to' => 'launched',  'label' => 'Launch',   'label_fr' => 'Lancer',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                    ['from' => 'launched', 'to' => 'completed', 'label' => 'Complete', 'label_fr' => 'Terminer',  'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'production-manager']],
                ],
            ],

            // ── 3. Accounting / Invoice ───────────────────────────────────────
            [
                'name'          => 'Facture',
                'module'        => 'accounting',
                'resource_type' => 'Invoice',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'draft',     'label' => 'Draft',     'label_fr' => 'Brouillon',  'color' => '#6B7280', 'icon' => 'pi pi-file',          'alert_note' => null,                                              'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'submitted', 'label' => 'Submitted', 'label_fr' => 'Soumis',     'color' => '#EAB308', 'icon' => 'pi pi-send',          'alert_note' => 'Vérifier les montants et la TVA.',                'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'approved',  'label' => 'Approved',  'label_fr' => 'Approuvé',   'color' => '#3B82F6', 'icon' => 'pi pi-check-circle',  'alert_note' => null,                                              'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'sent',      'label' => 'Sent',      'label_fr' => 'Envoyé',     'color' => '#F97316', 'icon' => 'pi pi-envelope',      'alert_note' => null,                                              'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'paid',      'label' => 'Paid',      'label_fr' => 'Payé',       'color' => '#10B981', 'icon' => 'pi pi-dollar',        'alert_note' => null,                                              'is_terminal' => true,  'allowed_roles' => []],
                    ['key' => 'cancelled', 'label' => 'Cancelled', 'label_fr' => 'Annulé',     'color' => '#EF4444', 'icon' => 'pi pi-times-circle',  'alert_note' => null,                                              'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'draft',     'to' => 'submitted', 'label' => 'Submit',  'label_fr' => 'Soumettre', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'accountant', 'finance-manager']],
                    ['from' => 'submitted', 'to' => 'approved',  'label' => 'Approve', 'label_fr' => 'Approuver', 'requires_approval' => true,  'allowed_roles' => ['admin', 'manager', 'finance-manager']],
                    ['from' => 'approved',  'to' => 'sent',      'label' => 'Send',    'label_fr' => 'Envoyer',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'accountant', 'finance-manager']],
                    ['from' => 'sent',      'to' => 'paid',      'label' => 'Mark Paid','label_fr' => 'Marquer payé', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'accountant', 'finance-manager']],
                    ['from' => 'draft',     'to' => 'cancelled', 'label' => 'Cancel',  'label_fr' => 'Annuler',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'finance-manager']],
                    ['from' => 'submitted', 'to' => 'cancelled', 'label' => 'Cancel',  'label_fr' => 'Annuler',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'finance-manager']],
                ],
            ],

            // ── 4. Inventory / PurchaseOrder ──────────────────────────────────
            [
                'name'          => 'Bon de commande',
                'module'        => 'inventory',
                'resource_type' => 'PurchaseOrder',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'draft',     'label' => 'Draft',     'label_fr' => 'Brouillon',     'color' => '#6B7280', 'icon' => 'pi pi-file',          'alert_note' => null,                                                           'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'submitted', 'label' => 'Submitted', 'label_fr' => 'Soumis',        'color' => '#EAB308', 'icon' => 'pi pi-send',          'alert_note' => 'Vérifier le stock avant confirmation.',                        'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'approved',  'label' => 'Approved',  'label_fr' => 'Approuvé',      'color' => '#3B82F6', 'icon' => 'pi pi-check-circle',  'alert_note' => null,                                                           'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'ordered',   'label' => 'Ordered',   'label_fr' => 'Commandé',      'color' => '#F97316', 'icon' => 'pi pi-shopping-cart', 'alert_note' => 'Confirmer la date de livraison avec le fournisseur.',          'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'received',  'label' => 'Received',  'label_fr' => 'Réceptionné',   'color' => '#8B5CF6', 'icon' => 'pi pi-inbox',         'alert_note' => 'Contrôler la conformité des marchandises reçues.',             'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'closed',    'label' => 'Closed',    'label_fr' => 'Clôturé',       'color' => '#10B981', 'icon' => 'pi pi-lock',          'alert_note' => null,                                                           'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'draft',     'to' => 'submitted', 'label' => 'Submit',    'label_fr' => 'Soumettre',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'purchasing-manager', 'logistics-manager']],
                    ['from' => 'submitted', 'to' => 'approved',  'label' => 'Approve',   'label_fr' => 'Approuver',   'requires_approval' => true,  'allowed_roles' => ['admin', 'manager', 'purchasing-manager']],
                    ['from' => 'approved',  'to' => 'ordered',   'label' => 'Order',     'label_fr' => 'Commander',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'purchasing-manager']],
                    ['from' => 'ordered',   'to' => 'received',  'label' => 'Receive',   'label_fr' => 'Réceptionner','requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'purchasing-manager', 'warehouse-operator', 'logistics-manager']],
                    ['from' => 'received',  'to' => 'closed',    'label' => 'Close',     'label_fr' => 'Clôturer',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'purchasing-manager']],
                ],
            ],

            // ── 5. HR / Leave ─────────────────────────────────────────────────
            [
                'name'          => 'Demande de congé',
                'module'        => 'hr',
                'resource_type' => 'Leave',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'submitted',    'label' => 'Submitted',    'label_fr' => 'Soumis',       'color' => '#EAB308', 'icon' => 'pi pi-send',         'alert_note' => null,                                                     'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'under_review', 'label' => 'Under Review', 'label_fr' => 'En révision',  'color' => '#F97316', 'icon' => 'pi pi-eye',          'alert_note' => 'Vérifier le solde de congés disponible.',                'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'approved',     'label' => 'Approved',     'label_fr' => 'Approuvé',     'color' => '#10B981', 'icon' => 'pi pi-check-circle', 'alert_note' => null,                                                     'is_terminal' => true,  'allowed_roles' => []],
                    ['key' => 'rejected',     'label' => 'Rejected',     'label_fr' => 'Refusé',       'color' => '#EF4444', 'icon' => 'pi pi-times-circle', 'alert_note' => null,                                                     'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'submitted',    'to' => 'under_review', 'label' => 'Review',  'label_fr' => 'Réviser',  'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'hr-manager']],
                    ['from' => 'under_review', 'to' => 'approved',     'label' => 'Approve', 'label_fr' => 'Approuver','requires_approval' => true,  'allowed_roles' => ['admin', 'manager', 'hr-manager']],
                    ['from' => 'under_review', 'to' => 'rejected',     'label' => 'Reject',  'label_fr' => 'Refuser',  'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'hr-manager']],
                    ['from' => 'submitted',    'to' => 'rejected',     'label' => 'Reject',  'label_fr' => 'Refuser',  'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'hr-manager']],
                ],
            ],

            // ── 6. POS / PosOrder ─────────────────────────────────────────────
            [
                'name'          => 'Commande POS',
                'module'        => 'pos',
                'resource_type' => 'PosOrder',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'open',      'label' => 'Open',      'label_fr' => 'Ouverte',    'color' => '#6B7280', 'icon' => 'pi pi-shopping-bag',  'alert_note' => null,                                          'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'preparing', 'label' => 'Preparing', 'label_fr' => 'En prépa.',  'color' => '#F97316', 'icon' => 'pi pi-spin pi-cog',   'alert_note' => null,                                          'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'ready',     'label' => 'Ready',     'label_fr' => 'Prête',      'color' => '#8B5CF6', 'icon' => 'pi pi-bell',          'alert_note' => 'Prévenir le client que la commande est prête.','is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'paid',      'label' => 'Paid',      'label_fr' => 'Payée',      'color' => '#10B981', 'icon' => 'pi pi-dollar',        'alert_note' => null,                                          'is_terminal' => true,  'allowed_roles' => []],
                    ['key' => 'refunded',  'label' => 'Refunded',  'label_fr' => 'Remboursée', 'color' => '#EF4444', 'icon' => 'pi pi-replay',        'alert_note' => null,                                          'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'open',     'to' => 'preparing', 'label' => 'Prepare',  'label_fr' => 'Préparer',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'cashier']],
                    ['from' => 'preparing','to' => 'ready',     'label' => 'Ready',    'label_fr' => 'Prête',      'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'cashier']],
                    ['from' => 'ready',    'to' => 'paid',      'label' => 'Pay',      'label_fr' => 'Payer',      'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'cashier']],
                    ['from' => 'paid',     'to' => 'refunded',  'label' => 'Refund',   'label_fr' => 'Rembourser', 'requires_approval' => true,  'allowed_roles' => ['admin', 'manager']],
                    ['from' => 'open',     'to' => 'refunded',  'label' => 'Cancel',   'label_fr' => 'Annuler',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'cashier']],
                ],
            ],

            // ── 7. Helpdesk / Ticket ──────────────────────────────────────────
            [
                'name'          => 'Ticket support',
                'module'        => 'helpdesk',
                'resource_type' => 'Ticket',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'new',         'label' => 'New',         'label_fr' => 'Nouveau',       'color' => '#6B7280', 'icon' => 'pi pi-inbox',         'alert_note' => null,                                                       'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'in_progress', 'label' => 'In Progress', 'label_fr' => 'En traitement', 'color' => '#F97316', 'icon' => 'pi pi-spin pi-cog',   'alert_note' => null,                                                       'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'waiting',     'label' => 'Waiting',     'label_fr' => 'En attente',    'color' => '#EAB308', 'icon' => 'pi pi-clock',         'alert_note' => 'En attente de réponse du client.',                         'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'resolved',    'label' => 'Resolved',    'label_fr' => 'Résolu',        'color' => '#3B82F6', 'icon' => 'pi pi-check',         'alert_note' => null,                                                       'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'closed',      'label' => 'Closed',      'label_fr' => 'Fermé',         'color' => '#10B981', 'icon' => 'pi pi-lock',          'alert_note' => null,                                                       'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'new',         'to' => 'in_progress', 'label' => 'Start',    'label_fr' => 'Prendre en charge', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin', 'customer-service']],
                    ['from' => 'in_progress', 'to' => 'waiting',     'label' => 'Wait',     'label_fr' => 'Mettre en attente', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin', 'customer-service']],
                    ['from' => 'waiting',     'to' => 'in_progress', 'label' => 'Resume',   'label_fr' => 'Reprendre',         'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin', 'customer-service']],
                    ['from' => 'in_progress', 'to' => 'resolved',    'label' => 'Resolve',  'label_fr' => 'Résoudre',          'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin', 'customer-service']],
                    ['from' => 'resolved',    'to' => 'closed',      'label' => 'Close',    'label_fr' => 'Fermer',            'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin']],
                    ['from' => 'resolved',    'to' => 'in_progress', 'label' => 'Reopen',   'label_fr' => 'Réouvrir',          'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'support-admin', 'customer-service']],
                ],
            ],

            // ── 8. Projects / Task ────────────────────────────────────────────
            [
                'name'          => 'Tâche projet',
                'module'        => 'projects',
                'resource_type' => 'Task',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'todo',        'label' => 'To Do',      'label_fr' => 'À faire',        'color' => '#6B7280', 'icon' => 'pi pi-list',          'alert_note' => null,                                                         'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'in_progress', 'label' => 'In Progress','label_fr' => 'En cours',       'color' => '#F97316', 'icon' => 'pi pi-spin pi-cog',   'alert_note' => null,                                                         'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'in_review',   'label' => 'In Review',  'label_fr' => 'En révision',    'color' => '#8B5CF6', 'icon' => 'pi pi-eye',           'alert_note' => 'Soumettre pour relecture avant validation.',                  'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'done',        'label' => 'Done',       'label_fr' => 'Terminée',       'color' => '#10B981', 'icon' => 'pi pi-check',         'alert_note' => null,                                                         'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'todo',        'to' => 'in_progress', 'label' => 'Start',    'label_fr' => 'Démarrer',      'requires_approval' => false, 'allowed_roles' => []],
                    ['from' => 'in_progress', 'to' => 'in_review',   'label' => 'Review',   'label_fr' => 'Soumettre',     'requires_approval' => false, 'allowed_roles' => []],
                    ['from' => 'in_review',   'to' => 'done',        'label' => 'Approve',  'label_fr' => 'Valider',       'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'project-manager']],
                    ['from' => 'in_review',   'to' => 'in_progress', 'label' => 'Rework',   'label_fr' => 'Corriger',      'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'project-manager']],
                    ['from' => 'in_progress', 'to' => 'todo',        'label' => 'Pause',    'label_fr' => 'Mettre en pause','requires_approval' => false, 'allowed_roles' => []],
                ],
            ],

            // ── 9. CRM / Opportunity ──────────────────────────────────────────
            [
                'name'          => 'Opportunité CRM',
                'module'        => 'crm',
                'resource_type' => 'Opportunity',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'prospect',    'label' => 'Prospect',    'label_fr' => 'Prospect',      'color' => '#6B7280', 'icon' => 'pi pi-user',          'alert_note' => null,                                                        'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'qualified',   'label' => 'Qualified',   'label_fr' => 'Qualifié',      'color' => '#3B82F6', 'icon' => 'pi pi-star',          'alert_note' => 'Valider les critères BANT (Budget, Autorité, Besoin, Temps).','is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'proposal',    'label' => 'Proposal',    'label_fr' => 'Proposition',   'color' => '#F97316', 'icon' => 'pi pi-file-pdf',      'alert_note' => null,                                                        'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'negotiation', 'label' => 'Negotiation', 'label_fr' => 'Négociation',   'color' => '#8B5CF6', 'icon' => 'pi pi-comments',      'alert_note' => null,                                                        'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'won',         'label' => 'Won',         'label_fr' => 'Gagnée',        'color' => '#10B981', 'icon' => 'pi pi-trophy',        'alert_note' => null,                                                        'is_terminal' => true,  'allowed_roles' => []],
                    ['key' => 'lost',        'label' => 'Lost',        'label_fr' => 'Perdue',        'color' => '#EF4444', 'icon' => 'pi pi-times-circle',  'alert_note' => null,                                                        'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'prospect',    'to' => 'qualified',   'label' => 'Qualify',   'label_fr' => 'Qualifier',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                    ['from' => 'qualified',   'to' => 'proposal',    'label' => 'Propose',   'label_fr' => 'Proposer',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                    ['from' => 'proposal',    'to' => 'negotiation', 'label' => 'Negotiate', 'label_fr' => 'Négocier',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                    ['from' => 'negotiation', 'to' => 'won',         'label' => 'Close Won', 'label_fr' => 'Conclure',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                    ['from' => 'negotiation', 'to' => 'lost',        'label' => 'Close Lost','label_fr' => 'Clore perdu', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                    ['from' => 'proposal',    'to' => 'lost',        'label' => 'Close Lost','label_fr' => 'Clore perdu', 'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'sales-rep', 'sales-manager']],
                ],
            ],

            // ── 10. Ecommerce / Order ─────────────────────────────────────────
            [
                'name'          => 'Commande e-commerce',
                'module'        => 'ecommerce',
                'resource_type' => 'Order',
                'is_active'     => true,
                'steps'         => [
                    ['key' => 'pending',   'label' => 'Pending',   'label_fr' => 'En attente',    'color' => '#6B7280', 'icon' => 'pi pi-clock',         'alert_note' => 'Vérifier la disponibilité du stock.',             'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'confirmed', 'label' => 'Confirmed', 'label_fr' => 'Confirmée',     'color' => '#3B82F6', 'icon' => 'pi pi-check-circle',  'alert_note' => null,                                              'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'shipped',   'label' => 'Shipped',   'label_fr' => 'Expédiée',      'color' => '#F97316', 'icon' => 'pi pi-send',          'alert_note' => 'Ajouter le numéro de suivi.',                     'is_terminal' => false, 'allowed_roles' => []],
                    ['key' => 'delivered', 'label' => 'Delivered', 'label_fr' => 'Livrée',        'color' => '#10B981', 'icon' => 'pi pi-home',          'alert_note' => null,                                              'is_terminal' => true,  'allowed_roles' => []],
                    ['key' => 'refunded',  'label' => 'Refunded',  'label_fr' => 'Remboursée',    'color' => '#EF4444', 'icon' => 'pi pi-replay',        'alert_note' => null,                                              'is_terminal' => true,  'allowed_roles' => []],
                ],
                'transitions' => [
                    ['from' => 'pending',   'to' => 'confirmed', 'label' => 'Confirm',  'label_fr' => 'Confirmer',   'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'marketplace-admin', 'brand-owner']],
                    ['from' => 'confirmed', 'to' => 'shipped',   'label' => 'Ship',     'label_fr' => 'Expédier',    'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'marketplace-admin', 'brand-owner', 'logistics-manager']],
                    ['from' => 'shipped',   'to' => 'delivered', 'label' => 'Deliver',  'label_fr' => 'Livrer',      'requires_approval' => false, 'allowed_roles' => ['admin', 'manager', 'marketplace-admin', 'logistics-manager']],
                    ['from' => 'delivered', 'to' => 'refunded',  'label' => 'Refund',   'label_fr' => 'Rembourser',  'requires_approval' => true,  'allowed_roles' => ['admin', 'manager', 'marketplace-admin']],
                    ['from' => 'confirmed', 'to' => 'refunded',  'label' => 'Refund',   'label_fr' => 'Rembourser',  'requires_approval' => true,  'allowed_roles' => ['admin', 'manager', 'marketplace-admin']],
                ],
            ],
        ];
    }
}
