<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

/**
 * WorkflowJsonSchema
 * ------------------
 * Provides a JSON Schema (RFC 7159 / draft-07 compatible) for workflow definitions,
 * a runtime validator, and pre-built example workflow definitions.
 */
class WorkflowJsonSchema
{
    // ─── Available triggers (keyed by internal key) ───────────────────────────

    public const TRIGGERS = [
        'crm.opportunity.won'               => ['module' => 'CRM',          'label' => 'Opportunité gagnée'],
        'crm.contact.created'               => ['module' => 'CRM',          'label' => 'Contact créé'],
        'sales.order.created'               => ['module' => 'Ventes',       'label' => 'Commande créée'],
        'sales.quotation.sent'              => ['module' => 'Ventes',       'label' => 'Devis envoyé'],
        'achats.invoice_received'           => ['module' => 'Achats',       'label' => 'Facture fournisseur reçue'],
        'accounting.invoice.approved'       => ['module' => 'Comptabilité', 'label' => 'Facture approuvée'],
        'accounting.payment_received'       => ['module' => 'Comptabilité', 'label' => 'Paiement reçu'],
        'inventory.stock_below_reorder_point' => ['module' => 'Inventaire', 'label' => 'Stock sous seuil de réappro'],
        'inventory.delivery_received'       => ['module' => 'Inventaire',   'label' => 'Livraison reçue'],
        'hr.leave_approved'                 => ['module' => 'RH',           'label' => 'Congé approuvé'],
        'hr.employee.hired'                 => ['module' => 'RH',           'label' => 'Embauche d\'un employé'],
        'hr.contract_expiry_check'          => ['module' => 'RH',           'label' => 'Vérification expiration contrat (quotidien)'],
        'projects.task.completed'           => ['module' => 'Projets',      'label' => 'Tâche de projet terminée'],
        'generic.condition_check'           => ['module' => 'Système',      'label' => 'Vérification de condition (générique)'],
    ];

    // ─── Available actions (keyed by internal key) ────────────────────────────

    public const ACTIONS = [
        'approval.request_multi_level'          => ['module' => 'Approbation',   'label' => 'Demande d\'approbation multi-niveaux',    'params_schema' => ['levels' => 'integer', 'approvers' => 'array']],
        'approval.request_single_level'         => ['module' => 'Approbation',   'label' => 'Demande d\'approbation simple',           'params_schema' => ['levels' => 'integer']],
        'notify.in_app'                         => ['module' => 'Notifications', 'label' => 'Notification in-app',                    'params_schema' => ['role' => 'string', 'message' => 'string']],
        'notify.send_email'                     => ['module' => 'Notifications', 'label' => 'Envoyer un email',                       'params_schema' => ['to' => 'string', 'template' => 'string']],
        'notify.send_sms'                       => ['module' => 'Notifications', 'label' => 'Envoyer un SMS',                        'params_schema' => ['to' => 'string', 'message' => 'string']],
        'achats.auto_create_po'                 => ['module' => 'Achats',        'label' => 'Créer un bon de commande automatique',   'params_schema' => ['supplier_id' => 'integer']],
        'achats.block_supplier'                 => ['module' => 'Achats',        'label' => 'Bloquer le fournisseur',                 'params_schema' => ['reason' => 'string']],
        'sales.create_order_from_opportunity'   => ['module' => 'Ventes',        'label' => 'Créer une commande depuis l\'opportunité', 'params_schema' => []],
        'accounting.book_purchase_invoice'      => ['module' => 'Comptabilité',  'label' => 'Comptabiliser la facture d\'achat',       'params_schema' => []],
        'accounting.generate_payment_schedule'  => ['module' => 'Comptabilité',  'label' => 'Générer un échéancier de paiement',      'params_schema' => ['installments' => 'integer']],
        'accounting.flag_for_approval'          => ['module' => 'Comptabilité',  'label' => 'Marquer pour approbation',               'params_schema' => ['levels' => 'integer', 'approvers' => 'array']],
        'payroll.adjust_for_leave'              => ['module' => 'RH',            'label' => 'Ajuster la paie pour congé',             'params_schema' => []],
        'hr.notify_contract_expiry'             => ['module' => 'RH',            'label' => 'Alerter l\'équipe RH (expiration contrat)', 'params_schema' => []],
        'inventory.update_stock'                => ['module' => 'Inventaire',    'label' => 'Mettre à jour le stock',                 'params_schema' => []],
        'reporting.generate_report'             => ['module' => 'Rapports',      'label' => 'Générer un rapport',                     'params_schema' => ['type' => 'string']],
        'projects.create_task'                  => ['module' => 'Projets',       'label' => 'Créer une tâche de projet',              'params_schema' => ['title' => 'string', 'assignee' => 'string']],
        'crm.close_opportunity'                 => ['module' => 'CRM',           'label' => 'Clôturer l\'opportunité',                'params_schema' => ['status' => 'string']],
        'helpdesk.escalate_ticket'              => ['module' => 'Support',       'label' => 'Escalader le ticket',                   'params_schema' => ['level' => 'integer']],
    ];

    // ─── Operators ────────────────────────────────────────────────────────────

    public const OPERATORS = [
        'equal'                  => '= (égal à)',
        'not_equal'              => '≠ (différent de)',
        'greater_than'           => '> (supérieur à)',
        'less_than'              => '< (inférieur à)',
        'greater_than_or_equal'  => '≥ (supérieur ou égal à)',
        'less_than_or_equal'     => '≤ (inférieur ou égal à)',
        'contains'               => 'contient',
        'not_contains'           => 'ne contient pas',
        'starts_with'            => 'commence par',
        'ends_with'              => 'se termine par',
        'is_empty'               => 'est vide',
        'is_not_empty'           => 'n\'est pas vide',
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Return JSON Schema draft-07 for workflow definitions.
     */
    public function getSchema(): array
    {
        return [
            '$schema'     => 'http://json-schema.org/draft-07/schema#',
            '$id'         => 'https://widehalo.com/schemas/workflow-definition.json',
            'title'       => 'Définition de Workflow WideHalo',
            'description' => 'Schéma de définition d\'un workflow d\'automatisation.',
            'type'        => 'object',
            'required'    => ['name', 'trigger'],
            'properties'  => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Nom du workflow (affiché dans la liste).',
                    'minLength'   => 2,
                    'maxLength'   => 200,
                ],
                'trigger' => [
                    'type'        => 'object',
                    'description' => 'Événement déclencheur du workflow.',
                    'required'    => ['key'],
                    'properties'  => [
                        'key'    => ['type' => 'string', 'description' => 'Clé interne du déclencheur.', 'enum' => array_keys(self::TRIGGERS)],
                        'module' => ['type' => 'string', 'description' => 'Module source.'],
                    ],
                ],
                'conditions' => [
                    'type'        => 'array',
                    'description' => 'Conditions à évaluer avant d\'exécuter les actions.',
                    'items'       => $this->conditionSchema(),
                ],
                'condition_logic' => [
                    'type'        => 'string',
                    'description' => 'Opérateur logique entre les conditions.',
                    'enum'        => ['ET', 'OU'],
                    'default'     => 'ET',
                ],
                'actions' => [
                    'type'        => 'array',
                    'description' => 'Actions à exécuter si les conditions sont remplies.',
                    'items'       => $this->actionSchema(),
                    'minItems'    => 1,
                ],
                'else_actions' => [
                    'type'        => 'array',
                    'description' => 'Actions à exécuter si les conditions ne sont PAS remplies (SINON).',
                    'items'       => $this->actionSchema(),
                ],
                'metadata' => [
                    'type'       => 'object',
                    'properties' => [
                        'description' => ['type' => 'string', 'maxLength' => 1000],
                        'tags'        => ['type' => 'array', 'items' => ['type' => 'string']],
                        'version'     => ['type' => 'string'],
                        'author'      => ['type' => 'string'],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * Validate a workflow definition against the schema.
     *
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = "Le champ 'name' est requis.";
        } elseif (strlen((string) $data['name']) < 2) {
            $errors[] = "Le champ 'name' doit contenir au moins 2 caractères.";
        }

        if (empty($data['trigger'])) {
            $errors[] = "Le champ 'trigger' est requis.";
        } elseif (empty($data['trigger']['key'])) {
            $errors[] = "Le champ 'trigger.key' est requis.";
        } elseif (! isset(self::TRIGGERS[$data['trigger']['key']])) {
            $errors[] = "Déclencheur inconnu: '{$data['trigger']['key']}'.";
        }

        if (! empty($data['actions'])) {
            foreach ($data['actions'] as $i => $action) {
                if (empty($action['key'])) {
                    $errors[] = "L'action #{$i} doit avoir une clé ('key').";
                } elseif (! isset(self::ACTIONS[$action['key']])) {
                    $errors[] = "Action inconnue: '{$action['key']}' (action #{$i}).";
                }
            }
        } else {
            $errors[] = "Au moins une action est requise dans 'actions'.";
        }

        if (! empty($data['conditions'])) {
            foreach ($data['conditions'] as $i => $cond) {
                if (empty($cond['field'])) {
                    $errors[] = "La condition #{$i} doit avoir un champ ('field').";
                }
                if (empty($cond['operator'])) {
                    $errors[] = "La condition #{$i} doit avoir un opérateur ('operator').";
                } elseif (! isset(self::OPERATORS[$cond['operator']])) {
                    $errors[] = "Opérateur inconnu: '{$cond['operator']}' (condition #{$i}).";
                }
                if (! array_key_exists('value', $cond)) {
                    $errors[] = "La condition #{$i} doit avoir une valeur ('value').";
                }
            }
        }

        if (isset($data['condition_logic']) && ! in_array($data['condition_logic'], ['ET', 'OU'], true)) {
            $errors[] = "La logique de condition doit être 'ET' ou 'OU'.";
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Return 5 annotated example workflow definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExamples(): array
    {
        return [
            [
                'name'        => 'Opportunité gagnée → Créer commande',
                'trigger'     => ['key' => 'crm.opportunity.won', 'module' => 'CRM'],
                'conditions'  => [
                    ['field' => 'amount', 'operator' => 'greater_than', 'value' => 0],
                ],
                'actions'     => [
                    ['key' => 'sales.create_order_from_opportunity', 'params' => []],
                ],
                'metadata'    => [
                    'description' => 'Crée automatiquement une commande lorsqu\'une opportunité CRM est gagnée.',
                    'tags'        => ['crm', 'ventes', 'automatisation'],
                ],
            ],
            [
                'name'        => 'Facture > 500K XOF → Approbation 3 niveaux',
                'trigger'     => ['key' => 'achats.invoice_received', 'module' => 'Achats'],
                'conditions'  => [
                    ['field' => 'amount', 'operator' => 'greater_than', 'value' => 500000, 'unit' => 'XOF'],
                ],
                'actions'     => [
                    ['key' => 'accounting.flag_for_approval', 'params' => ['levels' => 3, 'approvers' => ['finance_manager', 'cfo', 'ceo']], 'on_failure' => 'skip'],
                    ['key' => 'accounting.generate_payment_schedule', 'params' => ['installments' => 3]],
                ],
                'metadata'    => [
                    'description' => 'Approbation obligatoire pour factures > 500K XOF (seuil OHADA).',
                    'tags'        => ['finance', 'ohada', 'approbation'],
                ],
            ],
            [
                'name'        => 'Stock critique → Réappro automatique',
                'trigger'     => ['key' => 'inventory.stock_below_reorder_point', 'module' => 'Inventaire'],
                'conditions'  => [
                    ['field' => 'auto_reorder', 'operator' => 'equal', 'value' => true],
                ],
                'actions'     => [
                    ['key' => 'achats.auto_create_po', 'params' => []],
                    ['key' => 'notify.in_app', 'params' => ['role' => 'inventory_manager']],
                ],
                'metadata'    => [
                    'description' => 'Déclenche un bon de commande automatique quand le stock passe sous le seuil de réappro.',
                    'tags'        => ['inventaire', 'réappro', 'automatisation'],
                ],
            ],
            [
                'name'            => 'Congé non payé → Déduction paie',
                'trigger'         => ['key' => 'hr.leave_approved', 'module' => 'RH'],
                'conditions'      => [
                    ['field' => 'leave_type', 'operator' => 'equal', 'value' => 'non_paye'],
                ],
                'condition_logic' => 'ET',
                'actions'         => [
                    ['key' => 'payroll.adjust_for_leave', 'params' => []],
                ],
                'else_actions'    => [
                    ['key' => 'notify.in_app', 'params' => ['role' => 'hr_manager', 'message' => 'Congé approuvé (payé)']],
                ],
                'metadata'        => [
                    'description' => 'Déduit automatiquement les congés non payés de la paie mensuelle.',
                    'tags'        => ['rh', 'paie', 'congé'],
                ],
            ],
            [
                'name'        => 'Contrat expire dans 30j → Alerte RH',
                'trigger'     => ['key' => 'hr.contract_expiry_check', 'module' => 'RH'],
                'conditions'  => [
                    ['field' => 'days_until_expiry', 'operator' => 'less_than_or_equal', 'value' => 30],
                ],
                'actions'     => [
                    ['key' => 'hr.notify_contract_expiry', 'params' => []],
                    ['key' => 'notify.send_email', 'params' => ['to' => 'hr_manager', 'template' => 'contract_expiry']],
                ],
                'metadata'    => [
                    'description' => 'Alerte quotidienne pour les contrats expirant dans moins de 30 jours.',
                    'tags'        => ['rh', 'contrat', 'alerte'],
                ],
            ],
        ];
    }

    // ─── Private schema fragments ─────────────────────────────────────────────

    private function conditionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['field', 'operator', 'value'],
            'properties' => [
                'field'    => ['type' => 'string', 'description' => 'Champ à évaluer (ex: amount, leave_type).'],
                'operator' => ['type' => 'string', 'enum' => array_keys(self::OPERATORS)],
                'value'    => ['description' => 'Valeur de comparaison.'],
                'unit'     => ['type' => 'string', 'description' => 'Unité optionnelle (ex: XOF, EUR).'],
            ],
            'additionalProperties' => false,
        ];
    }

    private function actionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['key'],
            'properties' => [
                'key'        => ['type' => 'string', 'enum' => array_keys(self::ACTIONS), 'description' => 'Clé de l\'action à exécuter.'],
                'params'     => ['type' => 'object', 'description' => 'Paramètres de l\'action.'],
                'on_failure' => ['type' => 'string', 'enum' => ['stop', 'skip', 'retry'], 'default' => 'stop'],
            ],
            'additionalProperties' => false,
        ];
    }
}
