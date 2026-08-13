<?php

declare(strict_types=1);

namespace Modules\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflow\Models\Automation\AutomationFlowTemplate;

/**
 * Seeds 12 pre-built automation flow templates covering key cross-module workflows.
 *
 * Each template stores a complete flow_definition JSON with:
 *   - name, description, icon, color, trigger_type, trigger_config, tags
 *   - nodes[]   — array of node definitions with _template_id for connection mapping
 *   - connections[] — edges referencing source_template_id / target_template_id
 *   - variables[]   — flow-level variables
 */
class AutomationFlowTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Remove old built-in templates on each seed run
        AutomationFlowTemplate::where('is_builtin', true)->delete();

        foreach ($this->templates() as $tpl) {
            AutomationFlowTemplate::create($tpl);
        }
    }

    // ── Template Definitions ─────────────────────────────────────────────────────

    /** @return array<int, array<string,mixed>> */
    private function templates(): array
    {
        return [
            $this->template01_opportuniteGagneeCommandeProduction(),
            $this->template02_factureRecueOhadaApprobation(),
            $this->template03_stockCritiqueBonCommandeAuto(),
            $this->template04_nouvelEmployePaieItAcces(),
            $this->template05_ticketSlaEscaladeSmsManager(),
            $this->template06_commandeEcommerceExpeditionFacture(),
            $this->template07_paiementRecuRapprochementSolde(),
            $this->template08_congeApprouveCalendrierPaie(),
            $this->template09_devisExpireRelanceClient(),
            $this->template10_anomalieQualiteBlockageAlert(),
            $this->template11_contratExpire30jRenouvellement(),
            $this->template12_rapportBiHebdoEmailDirection(),
        ];
    }

    // ── Template 1 ───────────────────────────────────────────────────────────────

    private function template01_opportuniteGagneeCommandeProduction(): array
    {
        return [
            'name'        => 'Opportunité gagnée → Commande + Production',
            'description' => 'Quand une opportunité CRM passe à "Gagnée", crée automatiquement une commande client et un ordre de fabrication.',
            'category'    => 'crm-sales',
            'icon'        => '🎯',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Opportunité gagnée → Commande + Production',
                'description'  => 'Pipeline CRM → Sales → Manufacturing automatisé',
                'icon'         => '🎯',
                'color'        => '#10B981',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'crm.opportunity.won'],
                'tags'         => ['crm', 'sales', 'manufacturing'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'crm.opportunity.won',    'label' => 'Opportunité gagnée', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'sales.create_order',     'label' => 'Créer commande client', 'position_x' => 250, 'position_y' => 200, 'config' => ['customer_id' => '{{contact_id}}', 'items' => []], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'condition','node_key' => 'condition.amount_threshold', 'label' => 'Montant > 500k XOF?', 'position_x' => 500, 'position_y' => 200, 'config' => ['amount_field' => 'amount', 'threshold' => 500000, 'currency' => 'XOF'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'manufacturing.create_order', 'label' => 'Créer ordre de fabrication', 'position_x' => 750, 'position_y' => 100, 'config' => ['qty' => 1, 'planned_date' => '{{planned_date}}'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.in_app',           'label' => 'Notifier le commercial', 'position_x' => 750, 'position_y' => 300, 'config' => ['title' => 'Commande créée', 'body' => 'Commande créée pour {{name}}'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n6', 'node_type' => 'action',  'node_key' => 'notify.email',            'label' => 'Email confirmation client', 'position_x' => 1000, 'position_y' => 200, 'config' => ['to' => '{{email}}', 'subject' => 'Confirmation de commande', 'body' => 'Votre commande a été enregistrée.'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'if_true',  'condition_expr' => 'output.above_threshold == true'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'if_false', 'condition_expr' => 'output.above_threshold == false'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                    ['source_template_id' => 'n5', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                ],
                'variables' => [
                    ['name' => 'MIN_AMOUNT_XOF', 'value_type' => 'number', 'default_value' => '500000', 'description' => 'Seuil minimum pour créer un OF'],
                ],
            ],
        ];
    }

    // ── Template 2 ───────────────────────────────────────────────────────────────

    private function template02_factureRecueOhadaApprobation(): array
    {
        return [
            'name'        => 'Facture reçue → Booking OHADA + Approbation',
            'description' => 'Comptabilise automatiquement une facture fournisseur selon le plan OHADA et lance l\'approbation.',
            'category'    => 'accounting',
            'icon'        => '🧾',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Facture reçue → Booking OHADA + Approbation',
                'icon'         => '🧾',
                'color'        => '#EF4444',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'achats.invoice.received'],
                'tags'         => ['achats', 'accounting', 'ohada'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger',  'node_key' => 'achats.invoice.received',      'label' => 'Facture fournisseur reçue', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',   'node_key' => 'achats.book_invoice',           'label' => 'Comptabiliser (OHADA)', 'position_x' => 250, 'position_y' => 200, 'config' => [], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'condition','node_key' => 'condition.amount_threshold',    'label' => 'Montant > 100k XOF?', 'position_x' => 500, 'position_y' => 200, 'config' => ['amount_field' => 'amount', 'threshold' => 100000], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',   'node_key' => 'notify.in_app',                 'label' => 'Demander approbation manager', 'position_x' => 750, 'position_y' => 100, 'config' => ['title' => 'Approbation requise', 'body' => 'Facture de {{amount}} XOF en attente d\'approbation.'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n5', 'node_type' => 'action',   'node_key' => 'accounting.post_invoice',       'label' => 'Poster en comptabilité', 'position_x' => 750, 'position_y' => 300, 'config' => [], 'error_handling' => 'retry'],
                    ['_template_id' => 'n6', 'node_type' => 'action',   'node_key' => 'notify.email',                  'label' => 'Notifier fournisseur', 'position_x' => 1000, 'position_y' => 200, 'config' => ['to' => '{{supplier_email}}', 'subject' => 'Facture reçue', 'body' => 'Votre facture a été enregistrée.'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'if_true', 'condition_expr' => 'output.above_threshold == true'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'if_false'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                    ['source_template_id' => 'n5', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 3 ───────────────────────────────────────────────────────────────

    private function template03_stockCritiqueBonCommandeAuto(): array
    {
        return [
            'name'        => 'Stock critique → Bon de commande auto',
            'description' => 'Génère automatiquement un bon de commande fournisseur quand le stock passe sous le seuil de réapprovisionnement.',
            'category'    => 'inventory',
            'icon'        => '📉',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Stock critique → Bon de commande auto',
                'icon'         => '📉',
                'color'        => '#06B6D4',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'inventory.stock_below_reorder'],
                'tags'         => ['inventory', 'achats'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'inventory.stock_below_reorder', 'label' => 'Stock sous seuil', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'achats.create_po',              'label' => 'Créer BC fournisseur', 'position_x' => 250, 'position_y' => 200, 'config' => ['items' => [['product_id' => '{{product_id}}', 'qty' => 50]]], 'error_handling' => 'notify'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'notify.in_app',                 'label' => 'Notifier responsable achats', 'position_x' => 500, 'position_y' => 200, 'config' => ['title' => 'BC auto créé', 'body' => 'BC créé pour {{product_name}} (stock: {{current_qty}})'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.email',                  'label' => 'Email fournisseur principal', 'position_x' => 750, 'position_y' => 200, 'config' => ['subject' => 'Commande urgente — {{product_name}}', 'body' => 'Merci de confirmer la disponibilité.'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                ],
                'variables' => [
                    ['name' => 'DEFAULT_REORDER_QTY', 'value_type' => 'number', 'default_value' => '50', 'description' => 'Quantité de réappro par défaut'],
                ],
            ],
        ];
    }

    // ── Template 4 ───────────────────────────────────────────────────────────────

    private function template04_nouvelEmployePaieItAcces(): array
    {
        return [
            'name'        => 'Nouveau employé → Paie + IT + Accès',
            'description' => 'Onboarding complet : enrôlement paie, provisioning IT et accès ERP pour chaque nouvel employé.',
            'category'    => 'hr',
            'icon'        => '👨‍💼',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Nouveau employé → Paie + IT + Accès',
                'icon'         => '👨‍💼',
                'color'        => '#EC4899',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'hr.employee.created'],
                'tags'         => ['hr', 'payroll', 'it', 'onboarding'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'hr.employee.created',       'label' => 'Nouvel employé créé',     'position_x' => 0,   'position_y' => 300, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'hr.run_payroll',            'label' => 'Enrôler en paie',          'position_x' => 250, 'position_y' => 200, 'config' => ['period' => 'current'], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'hr.provision_it_access',   'label' => 'Provisionner accès IT',    'position_x' => 250, 'position_y' => 400, 'config' => ['role' => 'employee'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'calendar.create_event',    'label' => 'Planifier onboarding RH',  'position_x' => 500, 'position_y' => 200, 'config' => ['title' => 'Onboarding {{name}}', 'duration_hours' => 4], 'error_handling' => 'skip'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.email',             'label' => 'Email bienvenue',          'position_x' => 500, 'position_y' => 400, 'config' => ['to' => '{{email}}', 'subject' => 'Bienvenue chez WideHalo !', 'body' => 'Votre compte est prêt. Identifiant: {{email}}'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n6', 'node_type' => 'action',  'node_key' => 'notify.in_app',            'label' => 'Notifier manager',         'position_x' => 750, 'position_y' => 300, 'config' => ['title' => 'Nouveau collaborateur', 'body' => '{{name}} a rejoint {{department}}'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n1', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                    ['source_template_id' => 'n5', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 5 ───────────────────────────────────────────────────────────────

    private function template05_ticketSlaEscaladeSmsManager(): array
    {
        return [
            'name'        => 'Ticket SLA dépassé → Escalade + SMS manager',
            'description' => 'Escalade automatiquement et envoie un SMS au manager quand un ticket dépasse son délai SLA.',
            'category'    => 'helpdesk',
            'icon'        => '🚨',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Ticket SLA dépassé → Escalade + SMS manager',
                'icon'         => '🚨',
                'color'        => '#6366F1',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'helpdesk.sla.breach'],
                'tags'         => ['helpdesk', 'sla', 'escalation'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'helpdesk.sla.breach',     'label' => 'SLA dépassé', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'helpdesk.escalate_ticket','label' => 'Escalader vers Niveau 2', 'position_x' => 250, 'position_y' => 200, 'config' => ['level' => 2, 'reason' => 'SLA breach'], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'notify.sms',              'label' => 'SMS au manager', 'position_x' => 500, 'position_y' => 200, 'config' => ['message' => 'URGENT: Ticket #{{ticket_id}} SLA dépassé de {{overdue_hours}}h. Escalade niveau 2.'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.in_app',           'label' => 'Notif in-app équipe', 'position_x' => 500, 'position_y' => 350, 'config' => ['title' => 'SLA breach', 'body' => 'Ticket #{{ticket_id}} escaladé - client {{customer_id}} impacté'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'helpdesk.assign_ticket',  'label' => 'Assigner au manager', 'position_x' => 750, 'position_y' => 200, 'config' => ['agent_id' => 0], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 6 ───────────────────────────────────────────────────────────────

    private function template06_commandeEcommerceExpeditionFacture(): array
    {
        return [
            'name'        => 'Commande e-commerce → Expédition + Facture',
            'description' => 'Crée automatiquement l\'expédition et la facture comptable pour chaque commande e-commerce confirmée.',
            'category'    => 'ecommerce',
            'icon'        => '🛍️',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Commande e-commerce → Expédition + Facture',
                'icon'         => '🛍️',
                'color'        => '#14B8A6',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'ecommerce.order.placed'],
                'tags'         => ['ecommerce', 'logistics', 'accounting'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'ecommerce.order.placed',   'label' => 'Commande passée', 'position_x' => 0,   'position_y' => 250, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'logistics.create_shipment','label' => 'Créer expédition', 'position_x' => 250, 'position_y' => 150, 'config' => ['carrier' => 'DHL'], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'ecommerce.create_invoice', 'label' => 'Générer facture', 'position_x' => 250, 'position_y' => 350, 'config' => [], 'error_handling' => 'retry'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'accounting.post_invoice',  'label' => 'Poster en compta OHADA', 'position_x' => 500, 'position_y' => 350, 'config' => [], 'error_handling' => 'notify'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.email',             'label' => 'Confirmation client', 'position_x' => 750, 'position_y' => 250, 'config' => ['to' => '{{customer_email}}', 'subject' => 'Votre commande est en route !'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n1', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 7 ───────────────────────────────────────────────────────────────

    private function template07_paiementRecuRapprochementSolde(): array
    {
        return [
            'name'        => 'Paiement reçu → Rapprochement + Solde client',
            'description' => 'Lance le rapprochement bancaire et met à jour le solde CRM client à chaque paiement reçu.',
            'category'    => 'accounting',
            'icon'        => '💰',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Paiement reçu → Rapprochement + Solde client',
                'icon'         => '💰',
                'color'        => '#EF4444',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'accounting.payment.received'],
                'tags'         => ['accounting', 'crm'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'accounting.payment.received', 'label' => 'Paiement reçu', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'accounting.reconcile',        'label' => 'Rapprocher paiement', 'position_x' => 250, 'position_y' => 200, 'config' => [], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'crm.update_lead_score',       'label' => 'Mettre à jour score CRM', 'position_x' => 500, 'position_y' => 200, 'config' => ['score_delta' => 10], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.in_app',               'label' => 'Notifier comptable', 'position_x' => 750, 'position_y' => 200, 'config' => ['title' => 'Paiement rapproché', 'body' => 'Paiement de {{amount}} XOF rapproché automatiquement.'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 8 ───────────────────────────────────────────────────────────────

    private function template08_congeApprouveCalendrierPaie(): array
    {
        return [
            'name'        => 'Congé approuvé → Calendrier + Paie',
            'description' => 'Bloque le créneau dans le calendrier et notifie le service paie à chaque approbation de congé.',
            'category'    => 'hr',
            'icon'        => '🌴',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Congé approuvé → Calendrier + Paie',
                'icon'         => '🌴',
                'color'        => '#EC4899',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'hr.leave.approved'],
                'tags'         => ['hr', 'calendar', 'payroll'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'hr.leave.approved',       'label' => 'Congé approuvé', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'calendar.block_time',     'label' => 'Bloquer calendrier', 'position_x' => 250, 'position_y' => 150, 'config' => ['reason' => 'Congé approuvé'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'notify.in_app',           'label' => 'Notifier service paie', 'position_x' => 250, 'position_y' => 350, 'config' => ['title' => 'Congé à traiter', 'body' => '{{days}} jours de congé pour {{employee_id}} à déduire en paie.'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.email',            'label' => 'Confirmation employé', 'position_x' => 500, 'position_y' => 200, 'config' => ['subject' => 'Congé approuvé ✅', 'body' => 'Votre congé du {{start_date}} au {{end_date}} a été approuvé.'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n1', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 9 ───────────────────────────────────────────────────────────────

    private function template09_devisExpireRelanceClient(): array
    {
        return [
            'name'        => 'Devis expiré → Relance client automatique',
            'description' => 'Relance automatiquement le client par email et in-app quand un devis expire sans réponse.',
            'category'    => 'sales',
            'icon'        => '📄',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Devis expiré → Relance client automatique',
                'icon'         => '📄',
                'color'        => '#F59E0B',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'sales.quote.expired'],
                'tags'         => ['sales', 'crm', 'email'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'sales.quote.expired',     'label' => 'Devis expiré', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'ai.suggest',              'label' => 'Suggestion relance IA', 'position_x' => 250, 'position_y' => 200, 'config' => ['module' => 'Sales', 'action' => 'quote_followup'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'notify.email',            'label' => 'Email relance client', 'position_x' => 500, 'position_y' => 150, 'config' => ['subject' => 'Votre devis arrive à expiration', 'body' => 'Nous souhaiterions discuter de votre projet. Répondez à cet email pour renouveler votre devis.'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'crm.create_opportunity',  'label' => 'Créer opportunité suivi', 'position_x' => 500, 'position_y' => 300, 'config' => ['name' => 'Relance devis {{quote_id}}', 'amount' => '{{amount}}'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.in_app',           'label' => 'Notifier commercial', 'position_x' => 750, 'position_y' => 200, 'config' => ['title' => 'Devis expiré', 'body' => 'Relance automatique envoyée pour devis #{{quote_id}}'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 10 ──────────────────────────────────────────────────────────────

    private function template10_anomalieQualiteBlockageAlert(): array
    {
        return [
            'name'        => 'Anomalie qualité → Blocage production + Alerte',
            'description' => 'Bloque l\'ordre de fabrication et alerte les équipes qualité et production en cas d\'anomalie détectée.',
            'category'    => 'quality',
            'icon'        => '⚠️',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Anomalie qualité → Blocage production + Alerte',
                'icon'         => '⚠️',
                'color'        => '#059669',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'quality.inspection.failed'],
                'tags'         => ['quality', 'manufacturing'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'quality.inspection.failed',    'label' => 'Inspection échouée', 'position_x' => 0,   'position_y' => 250, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'manufacturing.block_order',    'label' => 'Bloquer l\'OF', 'position_x' => 250, 'position_y' => 250, 'config' => ['reason' => 'Inspection qualité échouée: {{failure_reason}}'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'quality.flag_product',         'label' => 'Signaler produit NC', 'position_x' => 500, 'position_y' => 150, 'config' => ['reason' => '{{failure_reason}}'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.in_app',                'label' => 'Alerte responsable qualité', 'position_x' => 500, 'position_y' => 350, 'config' => ['title' => '🚨 Anomalie qualité', 'body' => 'Défaut détecté sur OF #{{mo_id}}: {{failure_reason}}'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.sms',                   'label' => 'SMS directeur production', 'position_x' => 750, 'position_y' => 250, 'config' => ['message' => 'ALERTE: Anomalie qualité OF #{{mo_id}}. Production bloquée.'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n6', 'node_type' => 'action',  'node_key' => 'ai.analyze',                   'label' => 'Analyse IA cause racine', 'position_x' => 750, 'position_y' => 400, 'config' => ['prompt' => 'Analyse la cause racine de ce défaut qualité et propose des actions correctives:'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n5', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                ],
                'variables' => [],
            ],
        ];
    }

    // ── Template 11 ──────────────────────────────────────────────────────────────

    private function template11_contratExpire30jRenouvellement(): array
    {
        return [
            'name'        => 'Contrat expire 30j → Workflow renouvellement',
            'description' => 'Lance automatiquement le workflow de renouvellement et notifie les parties 30 jours avant l\'expiration d\'un contrat.',
            'category'    => 'contracts',
            'icon'        => '📋',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Contrat expire 30j → Workflow renouvellement',
                'icon'         => '📋',
                'color'        => '#6366F1',
                'trigger_type' => 'module_event',
                'trigger_config' => ['event_key' => 'contracts.contract.expiring'],
                'tags'         => ['hr', 'contracts', 'documents'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'contracts.contract.expiring', 'label' => 'Contrat expire dans 30j', 'position_x' => 0,   'position_y' => 200, 'config' => [], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'documents.generate_pdf',      'label' => 'Générer avenant renouvellement', 'position_x' => 250, 'position_y' => 200, 'config' => ['template' => 'contract_renewal'], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'documents.request_signature', 'label' => 'Demande signature RH', 'position_x' => 500, 'position_y' => 150, 'config' => ['message' => 'Merci de signer le renouvellement'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'notify.email',                'label' => 'Notifier employé/partenaire', 'position_x' => 500, 'position_y' => 300, 'config' => ['subject' => 'Renouvellement de contrat', 'body' => 'Votre contrat expire le {{expiry_date}}. Veuillez contacter votre responsable RH.'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'calendar.create_event',      'label' => 'Rappel calendrier RH', 'position_x' => 750, 'position_y' => 200, 'config' => ['title' => 'Expiration contrat: {{party_id}}', 'duration_hours' => 1], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                ],
                'variables' => [
                    ['name' => 'NOTICE_DAYS', 'value_type' => 'number', 'default_value' => '30', 'description' => 'Jours avant expiration pour déclencher le flux'],
                ],
            ],
        ];
    }

    // ── Template 12 ──────────────────────────────────────────────────────────────

    private function template12_rapportBiHebdoEmailDirection(): array
    {
        return [
            'name'        => 'Rapport BI hebdo → Email direction',
            'description' => 'Génère et envoie chaque lundi un rapport BI complet à la direction, avec analyse IA des tendances.',
            'category'    => 'bi',
            'icon'        => '📊',
            'is_builtin'  => true,
            'flow_definition' => [
                'name'         => 'Rapport BI hebdo → Email direction',
                'icon'         => '📊',
                'color'        => '#1D4ED8',
                'trigger_type' => 'schedule',
                'trigger_config' => ['cron' => '0 7 * * 1', 'timezone' => 'Africa/Abidjan'],  // every Monday at 07:00
                'tags'         => ['bi', 'reporting', 'direction', 'schedule'],
                'nodes' => [
                    ['_template_id' => 'n1', 'node_type' => 'trigger', 'node_key' => 'workflow.schedule',      'label' => 'Chaque lundi 07h00', 'position_x' => 0,   'position_y' => 200, 'config' => ['cron' => '0 7 * * 1', 'timezone' => 'Africa/Abidjan'], 'error_handling' => 'stop'],
                    ['_template_id' => 'n2', 'node_type' => 'action',  'node_key' => 'bi.generate_report',    'label' => 'Générer rapport BI semaine', 'position_x' => 250, 'position_y' => 200, 'config' => ['report_type' => 'weekly_executive', 'modules' => ['Accounting', 'CRM', 'Sales', 'HR', 'Inventory']], 'error_handling' => 'retry'],
                    ['_template_id' => 'n3', 'node_type' => 'action',  'node_key' => 'ai.analyze',            'label' => 'Analyse IA des tendances', 'position_x' => 500, 'position_y' => 200, 'config' => ['prompt' => 'Analyse les indicateurs clés de cette semaine et identifie les tendances importantes pour la direction:'], 'error_handling' => 'skip'],
                    ['_template_id' => 'n4', 'node_type' => 'action',  'node_key' => 'documents.generate_pdf','label' => 'Générer PDF rapport', 'position_x' => 750, 'position_y' => 200, 'config' => ['template' => 'executive_report', 'filename' => 'rapport-hebdo'], 'error_handling' => 'retry'],
                    ['_template_id' => 'n5', 'node_type' => 'action',  'node_key' => 'notify.email',          'label' => 'Email direction', 'position_x' => 1000, 'position_y' => 200, 'config' => ['subject' => '📊 Rapport hebdomadaire ERP — semaine {{week}}', 'body' => 'Veuillez trouver ci-joint le rapport de la semaine.\n\nAnalyse IA: {{output.analysis}}'], 'error_handling' => 'notify'],
                    ['_template_id' => 'n6', 'node_type' => 'action',  'node_key' => 'strategy.get_ratios',   'label' => 'Récupérer ratios stratégiques', 'position_x' => 250, 'position_y' => 350, 'config' => ['module' => 'all'], 'error_handling' => 'skip'],
                ],
                'connections' => [
                    ['source_template_id' => 'n1', 'target_template_id' => 'n2', 'condition_type' => 'always'],
                    ['source_template_id' => 'n1', 'target_template_id' => 'n6', 'condition_type' => 'always'],
                    ['source_template_id' => 'n2', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                    ['source_template_id' => 'n3', 'target_template_id' => 'n4', 'condition_type' => 'always'],
                    ['source_template_id' => 'n4', 'target_template_id' => 'n5', 'condition_type' => 'always'],
                    ['source_template_id' => 'n6', 'target_template_id' => 'n3', 'condition_type' => 'always'],
                ],
                'variables' => [
                    ['name' => 'DIRECTION_EMAIL', 'value_type' => 'string', 'default_value' => 'direction@company.com', 'description' => 'Adresse email direction'],
                    ['name' => 'REPORT_LOCALE',   'value_type' => 'string', 'default_value' => 'fr',                    'description' => 'Langue du rapport'],
                ],
            ],
        ];
    }
}
