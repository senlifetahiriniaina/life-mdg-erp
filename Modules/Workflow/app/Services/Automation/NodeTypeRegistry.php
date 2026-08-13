<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Automation;

use RuntimeException;

/**
 * Universal Node Type Registry — maps every trigger/action key to its metadata.
 *
 * Covers all 47 ERP modules.
 * Node definition shape:
 *   key           string   e.g. 'crm.opportunity.won'
 *   label         string   Human-readable name (French-first)
 *   module        string   Parent module name (CRM, HR, …)
 *   category      string   trigger|action|condition|transform
 *   icon          string   Emoji
 *   color         string   Hex colour
 *   description   string   One-line description in French
 *   input_schema  array    Expected input fields
 *   output_schema array    Fields this node emits to the next node
 */
class NodeTypeRegistry
{
    /** @var array<string, array<string,mixed>> key => definition */
    private array $registry = [];

    public function __construct()
    {
        $this->build();
    }

    // ── Public API ───────────────────────────────────────────────────────────────

    /** @return array<string, array<string,mixed>> */
    public function getAll(): array
    {
        return $this->registry;
    }

    /** @return array<string, array<string,mixed>> */
    public function getByModule(string $module): array
    {
        return array_filter(
            $this->registry,
            static fn (array $def) => strcasecmp($def['module'], $module) === 0,
        );
    }

    /** @return array<string, array<string,mixed>> */
    public function getByCategory(string $category): array
    {
        return array_filter(
            $this->registry,
            static fn (array $def) => $def['category'] === $category,
        );
    }

    /**
     * @return array<string,mixed>
     * @throws RuntimeException when key is unknown
     */
    public function resolve(string $key): array
    {
        if (!isset($this->registry[$key])) {
            throw new RuntimeException("Unknown node type key: '{$key}'");
        }
        return $this->registry[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->registry[$key]);
    }

    /** @return array<string, array<string,mixed>> All trigger-category nodes */
    public function getTriggers(): array
    {
        return $this->getByCategory('trigger');
    }

    /** @return array<string, array<string,mixed>> All action-category nodes */
    public function getActions(): array
    {
        return $this->getByCategory('action');
    }

    /** @return array<string,mixed>|null  Single trigger by key, or null */
    public function getTrigger(string $key): ?array
    {
        $node = $this->registry[$key] ?? null;
        if ($node && $node['category'] === 'trigger') {
            return $node;
        }
        return null;
    }

    /** @return array<string,mixed>|null  Single action by key, or null */
    public function getAction(string $key): ?array
    {
        $node = $this->registry[$key] ?? null;
        if ($node && $node['category'] === 'action') {
            return $node;
        }
        return null;
    }

    /** @return array<string, array<string,mixed>> Actions for a given module */
    public function getActionsForModule(string $module): array
    {
        return array_filter(
            $this->getActions(),
            static fn (array $def) => strcasecmp($def['module'], $module) === 0,
        );
    }

    /** @return array<string, array<string,mixed>> Triggers for a given module */
    public function getTriggersForModule(string $module): array
    {
        return array_filter(
            $this->getTriggers(),
            static fn (array $def) => strcasecmp($def['module'], $module) === 0,
        );
    }

    // ── Builder ──────────────────────────────────────────────────────────────────

    private function register(
        string $key,
        string $label,
        string $module,
        string $category,
        string $icon,
        string $color,
        string $description,
        array $inputSchema = [],
        array $outputSchema = [],
    ): void {
        $this->registry[$key] = [
            'key'           => $key,
            'label'         => $label,
            'module'        => $module,
            'category'      => $category,
            'icon'          => $icon,
            'color'         => $color,
            'description'   => $description,
            'input_schema'  => $inputSchema,
            'output_schema' => $outputSchema,
        ];
    }

    private function build(): void
    {
        $this->registerCrmNodes();
        $this->registerSalesNodes();
        $this->registerAchatsNodes();
        $this->registerInventoryNodes();
        $this->registerAccountingNodes();
        $this->registerHrNodes();
        $this->registerManufacturingNodes();
        $this->registerHelpdeskNodes();
        $this->registerEcommerceNodes();
        $this->registerPosNodes();
        $this->registerProjectsNodes();
        $this->registerLogisticsNodes();
        $this->registerCalendarNodes();
        $this->registerStrategyNodes();
        $this->registerPlmNodes();
        $this->registerQualityNodes();
        $this->registerDocumentsNodes();
        $this->registerWorkflowSystemNodes();
        $this->registerNotifyActionNodes();
        $this->registerDocumentActionNodes();
        $this->registerAiActionNodes();
        $this->registerHttpActionNodes();
        $this->registerDataTransformNodes();
        $this->registerDelayNodes();
        $this->registerConditionNodes();
        $this->registerTransformNodes();
        $this->registerAdditionalModuleNodes();
        $this->registerLoopAndSubFlowNodes();
        $this->registerExpressionAndCodeNodes();
    }

    // ── CRM ─────────────────────────────────────────────────────────────────────

    private function registerCrmNodes(): void
    {
        $color = '#10B981';
        $this->register('crm.contact.created', 'Contact créé (CRM)', 'CRM', 'trigger', '👤', $color,
            "Déclenché quand un nouveau contact est créé dans le CRM.",
            [],
            ['contact_id' => 'int', 'name' => 'string', 'email' => 'string', 'phone' => 'string', 'company' => 'string']
        );
        $this->register('crm.contact.updated', 'Contact mis à jour (CRM)', 'CRM', 'trigger', '✏️', $color,
            "Déclenché quand un contact existant est modifié.",
            [],
            ['contact_id' => 'int', 'changed_fields' => 'array', 'contact' => 'object']
        );
        $this->register('crm.opportunity.won', 'Opportunité gagnée (CRM)', 'CRM', 'trigger', '🎯', $color,
            'Déclenché quand une opportunité passe au statut "Gagnée".',
            [],
            ['opportunity_id' => 'int', 'name' => 'string', 'amount' => 'number', 'currency' => 'string', 'contact_id' => 'int', 'assigned_to' => 'int']
        );
        $this->register('crm.opportunity.lost', 'Opportunité perdue (CRM)', 'CRM', 'trigger', '❌', $color,
            'Déclenché quand une opportunité est perdue.',
            [],
            ['opportunity_id' => 'int', 'amount' => 'number', 'lost_reason' => 'string']
        );
        $this->register('crm.lead.qualified', 'Lead qualifié (CRM)', 'CRM', 'trigger', '⭐', $color,
            "Déclenché quand un lead passe le seuil de qualification.",
            [],
            ['lead_id' => 'int', 'score' => 'number', 'email' => 'string', 'source' => 'string']
        );
        $this->register('crm.create_contact', 'Créer un contact (CRM)', 'CRM', 'action', '👤', $color,
            'Crée un nouveau contact dans le CRM.',
            ['name' => 'string', 'email' => 'string', 'phone' => 'string?'],
            ['contact_id' => 'int', 'created' => 'boolean']
        );
        $this->register('crm.create_opportunity', 'Créer une opportunité (CRM)', 'CRM', 'action', '💼', $color,
            'Crée une nouvelle opportunité de vente.',
            ['name' => 'string', 'amount' => 'number', 'contact_id' => 'int'],
            ['opportunity_id' => 'int']
        );
        $this->register('crm.update_lead_score', 'Mettre à jour le score lead (CRM)', 'CRM', 'action', '📊', $color,
            'Met à jour le score de qualification d\'un lead.',
            ['lead_id' => 'int', 'score' => 'number'],
            ['updated' => 'boolean']
        );
    }

    // ── Sales ────────────────────────────────────────────────────────────────────

    private function registerSalesNodes(): void
    {
        $color = '#F59E0B';
        $this->register('sales.order.created', 'Commande créée (Sales)', 'Sales', 'trigger', '🛒', $color,
            'Déclenché quand une nouvelle commande client est enregistrée.',
            [],
            ['order_id' => 'int', 'amount' => 'number', 'currency' => 'string', 'customer_id' => 'int', 'items' => 'array']
        );
        $this->register('sales.order.confirmed', 'Commande confirmée (Sales)', 'Sales', 'trigger', '✅', $color,
            'Déclenché à la confirmation d\'une commande.',
            [],
            ['order_id' => 'int', 'amount' => 'number', 'customer_id' => 'int']
        );
        $this->register('sales.order.shipped', 'Commande expédiée (Sales)', 'Sales', 'trigger', '📦', $color,
            'Déclenché quand une commande passe au statut expédié.',
            [],
            ['order_id' => 'int', 'tracking_number' => 'string', 'carrier' => 'string']
        );
        $this->register('sales.quote.approved', 'Devis approuvé (Sales)', 'Sales', 'trigger', '📄', $color,
            'Déclenché quand un devis est approuvé par le client.',
            [],
            ['quote_id' => 'int', 'amount' => 'number', 'customer_id' => 'int', 'opportunity_id' => 'int?']
        );
        $this->register('sales.quote.expired', 'Devis expiré (Sales)', 'Sales', 'trigger', '⏰', $color,
            'Déclenché quand un devis dépasse sa date de validité sans réponse.',
            [],
            ['quote_id' => 'int', 'amount' => 'number', 'customer_id' => 'int', 'expired_at' => 'datetime']
        );
        $this->register('sales.create_order', 'Créer une commande (Sales)', 'Sales', 'action', '🛒', $color,
            'Crée une commande client dans le module Sales.',
            ['customer_id' => 'int', 'items' => 'array', 'currency' => 'string?'],
            ['order_id' => 'int', 'total' => 'number']
        );
        $this->register('sales.confirm_order', 'Confirmer une commande (Sales)', 'Sales', 'action', '✅', $color,
            'Confirme une commande existante.',
            ['order_id' => 'int'],
            ['confirmed' => 'boolean', 'order_id' => 'int']
        );
        $this->register('sales.create_quotation', 'Créer un devis (Sales)', 'Sales', 'action', '📋', $color,
            'Génère un devis à partir d\'une opportunité CRM.',
            ['opportunity_id' => 'int', 'items' => 'array'],
            ['quote_id' => 'int', 'pdf_url' => 'string?']
        );
    }

    // ── Achats (Purchasing) ──────────────────────────────────────────────────────

    private function registerAchatsNodes(): void
    {
        $color = '#8B5CF6';
        $this->register('achats.po.created', 'Bon de commande créé (Achats)', 'Achats', 'trigger', '📝', $color,
            'Déclenché à la création d\'un bon de commande fournisseur.',
            [],
            ['po_id' => 'int', 'supplier_id' => 'int', 'amount' => 'number', 'currency' => 'string', 'items' => 'array']
        );
        $this->register('achats.po.approved', 'Bon de commande approuvé (Achats)', 'Achats', 'trigger', '✅', $color,
            'Déclenché quand un BC fournisseur est approuvé.',
            [],
            ['po_id' => 'int', 'supplier_id' => 'int', 'amount' => 'number', 'approved_by' => 'int']
        );
        $this->register('achats.po.received', 'Réception fournisseur (Achats)', 'Achats', 'trigger', '📥', $color,
            'Déclenché à la réception physique des marchandises.',
            [],
            ['po_id' => 'int', 'received_items' => 'array', 'warehouse_id' => 'int']
        );
        $this->register('achats.invoice.received', 'Facture fournisseur reçue (Achats)', 'Achats', 'trigger', '🧾', $color,
            'Déclenché à la réception d\'une facture fournisseur.',
            [],
            ['invoice_id' => 'int', 'po_id' => 'int?', 'amount' => 'number', 'due_date' => 'date', 'supplier_id' => 'int']
        );
        $this->register('achats.invoice.overdue', 'Facture fournisseur en retard (Achats)', 'Achats', 'trigger', '⚠️', $color,
            'Déclenché quand une facture fournisseur dépasse son échéance.',
            [],
            ['invoice_id' => 'int', 'amount' => 'number', 'days_overdue' => 'int', 'supplier_id' => 'int']
        );
        $this->register('achats.create_po', 'Créer un BC fournisseur (Achats)', 'Achats', 'action', '📝', $color,
            'Génère automatiquement un bon de commande fournisseur.',
            ['supplier_id' => 'int', 'items' => 'array', 'currency' => 'string?'],
            ['po_id' => 'int', 'total' => 'number']
        );
        $this->register('achats.approve_po', 'Approuver un BC (Achats)', 'Achats', 'action', '✅', $color,
            'Approuve un bon de commande existant.',
            ['po_id' => 'int', 'approver_id' => 'int?'],
            ['approved' => 'boolean']
        );
        $this->register('achats.book_invoice', 'Comptabiliser facture fournisseur (Achats)', 'Achats', 'action', '📚', $color,
            'Enregistre la facture fournisseur dans la comptabilité OHADA.',
            ['invoice_id' => 'int'],
            ['journal_entry_id' => 'int', 'booked' => 'boolean']
        );
    }

    // ── Inventory ────────────────────────────────────────────────────────────────

    private function registerInventoryNodes(): void
    {
        $color = '#06B6D4';
        $this->register('inventory.stock_below_reorder', 'Stock sous seuil (Inventaire)', 'Inventory', 'trigger', '📉', $color,
            'Déclenché quand le stock d\'un article passe sous le point de réappro.',
            [],
            ['product_id' => 'int', 'product_name' => 'string', 'current_qty' => 'number', 'reorder_point' => 'number', 'unit' => 'string']
        );
        $this->register('inventory.stock_out', 'Rupture de stock (Inventaire)', 'Inventory', 'trigger', '🚨', $color,
            'Déclenché quand le stock atteint zéro.',
            [],
            ['product_id' => 'int', 'product_name' => 'string', 'warehouse_id' => 'int']
        );
        $this->register('inventory.product.received', 'Entrée stock (Inventaire)', 'Inventory', 'trigger', '📦', $color,
            'Déclenché lors d\'une réception de marchandise en entrepôt.',
            [],
            ['product_id' => 'int', 'qty_received' => 'number', 'warehouse_id' => 'int', 'po_id' => 'int?']
        );
        $this->register('inventory.transfer.done', 'Transfert stock terminé (Inventaire)', 'Inventory', 'trigger', '🔄', $color,
            'Déclenché à la fin d\'un transfert inter-entrepôts.',
            [],
            ['transfer_id' => 'int', 'from_warehouse' => 'int', 'to_warehouse' => 'int', 'items' => 'array']
        );
        $this->register('inventory.receive_stock', 'Réceptionner du stock (Inventaire)', 'Inventory', 'action', '📥', $color,
            'Enregistre une entrée de stock pour un produit.',
            ['product_id' => 'int', 'qty' => 'number', 'warehouse_id' => 'int'],
            ['movement_id' => 'int', 'new_qty' => 'number']
        );
        $this->register('inventory.create_transfer', 'Créer un transfert (Inventaire)', 'Inventory', 'action', '🔄', $color,
            'Initie un transfert de stock entre deux entrepôts.',
            ['product_id' => 'int', 'qty' => 'number', 'from_warehouse' => 'int', 'to_warehouse' => 'int'],
            ['transfer_id' => 'int']
        );
        $this->register('inventory.adjust_stock', 'Ajustement de stock (Inventaire)', 'Inventory', 'action', '⚖️', $color,
            'Ajuste manuellement le niveau de stock d\'un produit.',
            ['product_id' => 'int', 'adjustment' => 'number', 'reason' => 'string'],
            ['adjusted' => 'boolean', 'new_qty' => 'number']
        );
    }

    // ── Accounting ───────────────────────────────────────────────────────────────

    private function registerAccountingNodes(): void
    {
        $color = '#EF4444';
        $this->register('accounting.invoice.posted', 'Facture validée (Comptabilité)', 'Accounting', 'trigger', '🧾', $color,
            'Déclenché quand une facture client est validée et postée en comptabilité.',
            [],
            ['invoice_id' => 'int', 'amount' => 'number', 'currency' => 'string', 'customer_id' => 'int', 'ohada_account' => 'string', 'due_date' => 'date']
        );
        $this->register('accounting.payment.received', 'Paiement reçu (Comptabilité)', 'Accounting', 'trigger', '💰', $color,
            'Déclenché à la réception d\'un paiement client.',
            [],
            ['payment_id' => 'int', 'amount' => 'number', 'currency' => 'string', 'customer_id' => 'int', 'invoice_id' => 'int?', 'method' => 'string']
        );
        $this->register('accounting.reconciliation.done', 'Rapprochement bancaire (Comptabilité)', 'Accounting', 'trigger', '🏦', $color,
            'Déclenché quand un rapprochement bancaire est finalisé.',
            [],
            ['reconciliation_id' => 'int', 'balance' => 'number', 'period' => 'string']
        );
        $this->register('accounting.period.closed', 'Clôture de période (Comptabilité)', 'Accounting', 'trigger', '📅', $color,
            'Déclenché à la clôture d\'une période comptable.',
            [],
            ['period' => 'string', 'fiscal_year' => 'string', 'closed_by' => 'int']
        );
        $this->register('accounting.post_invoice', 'Valider une facture (Comptabilité)', 'Accounting', 'action', '🧾', $color,
            'Valide et poste une facture en comptabilité OHADA.',
            ['invoice_id' => 'int'],
            ['posted' => 'boolean', 'journal_entry_id' => 'int', 'ohada_account' => 'string']
        );
        $this->register('accounting.create_journal_entry', 'Écriture comptable (Comptabilité)', 'Accounting', 'action', '📓', $color,
            'Crée une écriture dans le journal OHADA.',
            ['debit_account' => 'string', 'credit_account' => 'string', 'amount' => 'number', 'description' => 'string'],
            ['entry_id' => 'int', 'created' => 'boolean']
        );
        $this->register('accounting.reconcile', 'Rapprocher (Comptabilité)', 'Accounting', 'action', '🏦', $color,
            'Lance un rapprochement automatique bancaire.',
            ['bank_account_id' => 'int', 'period' => 'string'],
            ['matched_count' => 'int', 'unmatched_count' => 'int']
        );
    }

    // ── HR ───────────────────────────────────────────────────────────────────────

    private function registerHrNodes(): void
    {
        $color = '#EC4899';
        $this->register('hr.employee.created', 'Employé créé (RH)', 'HR', 'trigger', '👨‍💼', $color,
            'Déclenché quand un nouveau dossier employé est ouvert.',
            [],
            ['employee_id' => 'int', 'name' => 'string', 'department' => 'string', 'position' => 'string', 'start_date' => 'date']
        );
        $this->register('hr.employee.offboarded', 'Départ employé (RH)', 'HR', 'trigger', '👋', $color,
            'Déclenché lors du départ d\'un employé (démission/licenciement).',
            [],
            ['employee_id' => 'int', 'name' => 'string', 'last_day' => 'date', 'reason' => 'string']
        );
        $this->register('hr.leave.approved', 'Congé approuvé (RH)', 'HR', 'trigger', '🌴', $color,
            'Déclenché quand une demande de congé est approuvée.',
            [],
            ['leave_id' => 'int', 'employee_id' => 'int', 'start_date' => 'date', 'end_date' => 'date', 'days' => 'number', 'type' => 'string']
        );
        $this->register('hr.leave.rejected', 'Congé refusé (RH)', 'HR', 'trigger', '🚫', $color,
            'Déclenché quand une demande de congé est rejetée.',
            [],
            ['leave_id' => 'int', 'employee_id' => 'int', 'reason' => 'string']
        );
        $this->register('hr.payroll.run', 'Paie exécutée (RH)', 'HR', 'trigger', '💵', $color,
            'Déclenché à chaque exécution de la paie mensuelle.',
            [],
            ['payroll_id' => 'int', 'period' => 'string', 'employee_count' => 'int', 'total_gross' => 'number', 'currency' => 'string']
        );
        $this->register('hr.contract.expiring', 'Contrat expirant (RH)', 'HR', 'trigger', '📋', $color,
            'Déclenché 30 jours avant l\'expiration d\'un contrat employé.',
            [],
            ['employee_id' => 'int', 'contract_id' => 'int', 'expiry_date' => 'date', 'days_left' => 'int']
        );
        $this->register('hr.create_employee', 'Créer un employé (RH)', 'HR', 'action', '👨‍💼', $color,
            'Crée un dossier employé dans le module RH.',
            ['name' => 'string', 'email' => 'string', 'department_id' => 'int', 'position' => 'string', 'start_date' => 'date'],
            ['employee_id' => 'int', 'created' => 'boolean']
        );
        $this->register('hr.approve_leave', 'Approuver un congé (RH)', 'HR', 'action', '✅', $color,
            'Approuve automatiquement une demande de congé.',
            ['leave_id' => 'int'],
            ['approved' => 'boolean']
        );
        $this->register('hr.run_payroll', 'Lancer la paie (RH)', 'HR', 'action', '💵', $color,
            'Exécute le calcul de la paie pour une période donnée.',
            ['period' => 'string', 'department_id' => 'int?'],
            ['payroll_id' => 'int', 'employees_processed' => 'int']
        );
        $this->register('hr.provision_it_access', 'Provisioner accès IT (RH)', 'HR', 'action', '🖥️', $color,
            'Crée les accès informatiques pour un nouvel employé.',
            ['employee_id' => 'int', 'role' => 'string'],
            ['user_id' => 'int', 'credentials_sent' => 'boolean']
        );
    }

    // ── Manufacturing ────────────────────────────────────────────────────────────

    private function registerManufacturingNodes(): void
    {
        $color = '#F97316';
        $this->register('manufacturing.order.created', 'OF créé (Fabrication)', 'Manufacturing', 'trigger', '🏭', $color,
            'Déclenché à la création d\'un ordre de fabrication.',
            [],
            ['mo_id' => 'int', 'product_id' => 'int', 'qty' => 'number', 'planned_date' => 'date', 'bom_id' => 'int?']
        );
        $this->register('manufacturing.order.started', 'OF démarré (Fabrication)', 'Manufacturing', 'trigger', '▶️', $color,
            'Déclenché quand la production d\'un OF commence.',
            [],
            ['mo_id' => 'int', 'started_at' => 'datetime', 'operator_id' => 'int']
        );
        $this->register('manufacturing.order.completed', 'OF terminé (Fabrication)', 'Manufacturing', 'trigger', '🎉', $color,
            'Déclenché à la fin d\'un ordre de fabrication.',
            [],
            ['mo_id' => 'int', 'product_id' => 'int', 'qty_produced' => 'number', 'duration_hours' => 'number', 'defects' => 'int']
        );
        $this->register('manufacturing.defect.detected', 'Défaut détecté (Fabrication)', 'Manufacturing', 'trigger', '⚠️', $color,
            'Déclenché quand un défaut qualité est identifié sur une ligne de production.',
            [],
            ['mo_id' => 'int', 'defect_type' => 'string', 'qty_defective' => 'number', 'severity' => 'string']
        );
        $this->register('manufacturing.create_order', 'Créer un OF (Fabrication)', 'Manufacturing', 'action', '🏭', $color,
            'Crée un ordre de fabrication dans le module Manufacturing.',
            ['product_id' => 'int', 'qty' => 'number', 'planned_date' => 'date', 'bom_id' => 'int?'],
            ['mo_id' => 'int', 'created' => 'boolean']
        );
        $this->register('manufacturing.block_order', 'Bloquer un OF (Fabrication)', 'Manufacturing', 'action', '🚫', $color,
            'Bloque un ordre de fabrication en cas d\'anomalie.',
            ['mo_id' => 'int', 'reason' => 'string'],
            ['blocked' => 'boolean']
        );
        $this->register('manufacturing.update_status', 'Mettre à jour statut OF (Fabrication)', 'Manufacturing', 'action', '🔄', $color,
            'Change le statut d\'un ordre de fabrication.',
            ['mo_id' => 'int', 'status' => 'string'],
            ['updated' => 'boolean', 'mo_id' => 'int']
        );
    }

    // ── Helpdesk ─────────────────────────────────────────────────────────────────

    private function registerHelpdeskNodes(): void
    {
        $color = '#6366F1';
        $this->register('helpdesk.ticket.created', 'Ticket créé (Support)', 'Helpdesk', 'trigger', '🎫', $color,
            'Déclenché à la création d\'un nouveau ticket support.',
            [],
            ['ticket_id' => 'int', 'subject' => 'string', 'priority' => 'string', 'customer_id' => 'int', 'channel' => 'string']
        );
        $this->register('helpdesk.ticket.escalated', 'Ticket escaladé (Support)', 'Helpdesk', 'trigger', '🔺', $color,
            'Déclenché quand un ticket est escaladé vers un niveau supérieur.',
            [],
            ['ticket_id' => 'int', 'from_level' => 'int', 'to_level' => 'int', 'escalated_by' => 'int']
        );
        $this->register('helpdesk.ticket.resolved', 'Ticket résolu (Support)', 'Helpdesk', 'trigger', '✅', $color,
            'Déclenché quand un ticket est marqué comme résolu.',
            [],
            ['ticket_id' => 'int', 'resolution_time_hours' => 'number', 'csat_score' => 'number?', 'agent_id' => 'int']
        );
        $this->register('helpdesk.sla.breach', 'SLA dépassé (Support)', 'Helpdesk', 'trigger', '🚨', $color,
            'Déclenché quand un ticket dépasse son délai SLA.',
            [],
            ['ticket_id' => 'int', 'sla_hours' => 'number', 'overdue_hours' => 'number', 'customer_id' => 'int']
        );
        $this->register('helpdesk.escalate_ticket', 'Escalader un ticket (Support)', 'Helpdesk', 'action', '🔺', $color,
            'Escalade un ticket au niveau supérieur.',
            ['ticket_id' => 'int', 'level' => 'int', 'reason' => 'string?'],
            ['escalated' => 'boolean', 'new_level' => 'int']
        );
        $this->register('helpdesk.assign_ticket', 'Assigner un ticket (Support)', 'Helpdesk', 'action', '👤', $color,
            'Assigne un ticket à un agent spécifique.',
            ['ticket_id' => 'int', 'agent_id' => 'int'],
            ['assigned' => 'boolean']
        );
    }

    // ── E-Commerce ───────────────────────────────────────────────────────────────

    private function registerEcommerceNodes(): void
    {
        $color = '#14B8A6';
        $this->register('ecommerce.order.placed', 'Commande passée (E-commerce)', 'Ecommerce', 'trigger', '🛍️', $color,
            'Déclenché quand un client passe une commande sur la boutique.',
            [],
            ['order_id' => 'int', 'amount' => 'number', 'currency' => 'string', 'customer_id' => 'int', 'items' => 'array', 'payment_method' => 'string']
        );
        $this->register('ecommerce.order.cancelled', 'Commande annulée (E-commerce)', 'Ecommerce', 'trigger', '❌', $color,
            'Déclenché lors de l\'annulation d\'une commande en ligne.',
            [],
            ['order_id' => 'int', 'amount' => 'number', 'reason' => 'string', 'customer_id' => 'int']
        );
        $this->register('ecommerce.payment.failed', 'Paiement échoué (E-commerce)', 'Ecommerce', 'trigger', '💳', $color,
            'Déclenché quand un paiement en ligne échoue.',
            [],
            ['order_id' => 'int', 'amount' => 'number', 'payment_method' => 'string', 'error_code' => 'string']
        );
        $this->register('ecommerce.review.posted', 'Avis client posté (E-commerce)', 'Ecommerce', 'trigger', '⭐', $color,
            'Déclenché quand un client publie un avis produit.',
            [],
            ['review_id' => 'int', 'product_id' => 'int', 'rating' => 'int', 'customer_id' => 'int']
        );
        $this->register('ecommerce.create_invoice', 'Créer facture e-commerce (E-commerce)', 'Ecommerce', 'action', '🧾', $color,
            'Génère une facture pour une commande e-commerce.',
            ['order_id' => 'int'],
            ['invoice_id' => 'int', 'pdf_url' => 'string?']
        );
        $this->register('ecommerce.update_order_status', 'Mettre à jour statut commande (E-commerce)', 'Ecommerce', 'action', '🔄', $color,
            'Change le statut d\'une commande e-commerce.',
            ['order_id' => 'int', 'status' => 'string'],
            ['updated' => 'boolean']
        );
    }

    // ── POS ──────────────────────────────────────────────────────────────────────

    private function registerPosNodes(): void
    {
        $color = '#84CC16';
        $this->register('pos.session.opened', 'Session POS ouverte', 'POS', 'trigger', '🏪', $color,
            'Déclenché à l\'ouverture d\'une session de caisse.',
            [],
            ['session_id' => 'int', 'cashier_id' => 'int', 'terminal_id' => 'int', 'opening_balance' => 'number']
        );
        $this->register('pos.session.closed', 'Session POS fermée', 'POS', 'trigger', '🔒', $color,
            'Déclenché à la clôture d\'une session de caisse.',
            [],
            ['session_id' => 'int', 'total_sales' => 'number', 'total_transactions' => 'int', 'closing_balance' => 'number']
        );
        $this->register('pos.sale.completed', 'Vente POS complétée', 'POS', 'trigger', '🧾', $color,
            'Déclenché à chaque vente enregistrée en caisse.',
            [],
            ['sale_id' => 'int', 'amount' => 'number', 'items' => 'array', 'payment_method' => 'string', 'cashier_id' => 'int']
        );
        $this->register('pos.open_session', 'Ouvrir session caisse (POS)', 'POS', 'action', '🏪', $color,
            'Ouvre une nouvelle session de caisse.',
            ['terminal_id' => 'int', 'cashier_id' => 'int', 'opening_balance' => 'number'],
            ['session_id' => 'int']
        );
        $this->register('pos.close_session', 'Fermer session caisse (POS)', 'POS', 'action', '🔒', $color,
            'Ferme la session de caisse en cours.',
            ['session_id' => 'int'],
            ['closed' => 'boolean', 'total_sales' => 'number']
        );
        $this->register('pos.process_payment', 'Traiter un paiement (POS)', 'POS', 'action', '💳', $color,
            'Traite un paiement (espèces, mobile money, carte).',
            ['session_id' => 'int', 'amount' => 'number', 'method' => 'string'],
            ['payment_id' => 'int', 'success' => 'boolean']
        );
    }

    // ── Projects ─────────────────────────────────────────────────────────────────

    private function registerProjectsNodes(): void
    {
        $color = '#0EA5E9';
        $this->register('projects.task.completed', 'Tâche complétée (Projets)', 'Projects', 'trigger', '✅', $color,
            'Déclenché quand une tâche projet est marquée comme terminée.',
            [],
            ['task_id' => 'int', 'project_id' => 'int', 'assigned_to' => 'int', 'completed_at' => 'datetime']
        );
        $this->register('projects.milestone.reached', 'Jalon atteint (Projets)', 'Projects', 'trigger', '🏁', $color,
            'Déclenché quand un jalon de projet est atteint.',
            [],
            ['milestone_id' => 'int', 'project_id' => 'int', 'name' => 'string', 'reached_at' => 'datetime']
        );
        $this->register('projects.deadline.overdue', 'Délai dépassé (Projets)', 'Projects', 'trigger', '⏰', $color,
            'Déclenché quand une tâche ou un projet dépasse son délai.',
            [],
            ['task_id' => 'int?', 'project_id' => 'int', 'days_overdue' => 'int', 'responsible' => 'int']
        );
        $this->register('projects.create_task', 'Créer une tâche (Projets)', 'Projects', 'action', '📌', $color,
            'Crée une tâche dans un projet.',
            ['project_id' => 'int', 'title' => 'string', 'assigned_to' => 'int?', 'due_date' => 'date?'],
            ['task_id' => 'int']
        );
        $this->register('projects.update_status', 'Mettre à jour statut projet (Projets)', 'Projects', 'action', '🔄', $color,
            'Change le statut d\'un projet.',
            ['project_id' => 'int', 'status' => 'string'],
            ['updated' => 'boolean']
        );
    }

    // ── Logistics ────────────────────────────────────────────────────────────────

    private function registerLogisticsNodes(): void
    {
        $color = '#78716C';
        $this->register('logistics.shipment.dispatched', 'Expédition lancée (Logistique)', 'Logistics', 'trigger', '🚚', $color,
            'Déclenché quand un colis est remis au transporteur.',
            [],
            ['shipment_id' => 'int', 'order_id' => 'int', 'carrier' => 'string', 'tracking_number' => 'string', 'estimated_delivery' => 'date']
        );
        $this->register('logistics.shipment.delivered', 'Livraison effectuée (Logistique)', 'Logistics', 'trigger', '📬', $color,
            'Déclenché quand la livraison est confirmée.',
            [],
            ['shipment_id' => 'int', 'order_id' => 'int', 'delivered_at' => 'datetime', 'signature' => 'string?']
        );
        $this->register('logistics.shipment.delayed', 'Expédition retardée (Logistique)', 'Logistics', 'trigger', '⏳', $color,
            'Déclenché quand un transporteur signale un retard.',
            [],
            ['shipment_id' => 'int', 'order_id' => 'int', 'new_estimated_delivery' => 'date', 'delay_reason' => 'string']
        );
        $this->register('logistics.create_shipment', 'Créer une expédition (Logistique)', 'Logistics', 'action', '🚚', $color,
            'Crée une expédition pour une commande.',
            ['order_id' => 'int', 'carrier' => 'string', 'address_id' => 'int'],
            ['shipment_id' => 'int', 'tracking_number' => 'string']
        );
        $this->register('logistics.update_tracking', 'Mettre à jour tracking (Logistique)', 'Logistics', 'action', '📍', $color,
            'Met à jour le statut de suivi d\'une expédition.',
            ['shipment_id' => 'int', 'status' => 'string', 'location' => 'string?'],
            ['updated' => 'boolean']
        );
    }

    // ── Calendar ─────────────────────────────────────────────────────────────────

    private function registerCalendarNodes(): void
    {
        $color = '#A855F7';
        $this->register('calendar.event.created', 'Événement créé (Calendrier)', 'Calendar', 'trigger', '📅', $color,
            'Déclenché quand un nouvel événement calendrier est créé.',
            [],
            ['event_id' => 'int', 'title' => 'string', 'start' => 'datetime', 'end' => 'datetime', 'attendees' => 'array', 'module_source' => 'string?']
        );
        $this->register('calendar.reminder.due', 'Rappel calendrier (Calendrier)', 'Calendar', 'trigger', '⏰', $color,
            'Déclenché quand un rappel d\'événement arrive à échéance.',
            [],
            ['event_id' => 'int', 'title' => 'string', 'starts_in_minutes' => 'int', 'attendees' => 'array']
        );
        $this->register('calendar.create_event', 'Créer un événement (Calendrier)', 'Calendar', 'action', '📅', $color,
            'Crée un événement dans le calendrier (Google/Outlook/Apple sync).',
            ['title' => 'string', 'start' => 'datetime', 'end' => 'datetime', 'attendees' => 'array?', 'module_source' => 'string?'],
            ['event_id' => 'int', 'synced' => 'boolean']
        );
        $this->register('calendar.block_time', 'Bloquer du temps (Calendrier)', 'Calendar', 'action', '🚫', $color,
            'Bloque un créneau dans le calendrier d\'un employé.',
            ['user_id' => 'int', 'start' => 'datetime', 'end' => 'datetime', 'reason' => 'string'],
            ['event_id' => 'int']
        );
    }

    // ── Strategy ─────────────────────────────────────────────────────────────────

    private function registerStrategyNodes(): void
    {
        $color = '#DC2626';
        $this->register('strategy.alert.triggered', 'Alerte stratégique (Stratégie)', 'Strategy', 'trigger', '🚨', $color,
            'Déclenché quand un ratio KPI déclenche une alerte stratégique.',
            [],
            ['alert_id' => 'int', 'ratio_key' => 'string', 'current_value' => 'number', 'threshold' => 'number', 'severity' => 'string']
        );
        $this->register('strategy.ratio.below_threshold', 'Ratio sous seuil (Stratégie)', 'Strategy', 'trigger', '📉', $color,
            'Déclenché quand un KPI ratio passe sous son seuil critique.',
            [],
            ['ratio_key' => 'string', 'current_value' => 'number', 'threshold' => 'number', 'module' => 'string']
        );
        $this->register('strategy.get_ratios', 'Obtenir les ratios (Stratégie)', 'Strategy', 'action', '📊', $color,
            'Récupère les valeurs actuelles des KPI pour un module.',
            ['module' => 'string'],
            ['ratios' => 'array']
        );
        $this->register('strategy.create_alert', 'Créer une alerte stratégique (Stratégie)', 'Strategy', 'action', '🚨', $color,
            'Crée une alerte dans le tableau de bord stratégique.',
            ['ratio_key' => 'string', 'message' => 'string', 'severity' => 'string'],
            ['alert_id' => 'int']
        );
    }

    // ── PLM ──────────────────────────────────────────────────────────────────────

    private function registerPlmNodes(): void
    {
        $color = '#7C3AED';
        $this->register('plm.version.approved', 'Version produit approuvée (PLM)', 'PLM', 'trigger', '✅', $color,
            'Déclenché quand une version de fiche produit est approuvée.',
            [],
            ['version_id' => 'int', 'product_id' => 'int', 'version_number' => 'string', 'approved_by' => 'int']
        );
        $this->register('plm.version.archived', 'Version produit archivée (PLM)', 'PLM', 'trigger', '📁', $color,
            'Déclenché quand une version produit est archivée.',
            [],
            ['version_id' => 'int', 'product_id' => 'int', 'archived_by' => 'int']
        );
        $this->register('plm.create_version', 'Créer une version produit (PLM)', 'PLM', 'action', '📝', $color,
            'Crée une nouvelle version d\'une fiche produit.',
            ['product_id' => 'int', 'changes' => 'string'],
            ['version_id' => 'int']
        );
    }

    // ── Quality ──────────────────────────────────────────────────────────────────

    private function registerQualityNodes(): void
    {
        $color = '#059669';
        $this->register('quality.inspection.failed', 'Inspection qualité échouée (Qualité)', 'Quality', 'trigger', '❌', $color,
            'Déclenché quand un contrôle qualité ne passe pas.',
            [],
            ['inspection_id' => 'int', 'product_id' => 'int', 'mo_id' => 'int?', 'failure_reason' => 'string', 'severity' => 'string']
        );
        $this->register('quality.certificate.expiring', 'Certificat expirant (Qualité)', 'Quality', 'trigger', '📜', $color,
            'Déclenché 30 jours avant l\'expiration d\'un certificat ISO/qualité.',
            [],
            ['certificate_id' => 'int', 'standard' => 'string', 'expiry_date' => 'date', 'supplier_id' => 'int?', 'days_left' => 'int']
        );
        $this->register('quality.create_inspection', 'Créer une inspection (Qualité)', 'Quality', 'action', '🔍', $color,
            'Crée un contrôle qualité pour un lot ou un OF.',
            ['product_id' => 'int', 'mo_id' => 'int?', 'checklist_id' => 'int?'],
            ['inspection_id' => 'int']
        );
        $this->register('quality.flag_product', 'Signaler un produit non conforme (Qualité)', 'Quality', 'action', '🚩', $color,
            'Bloque un produit pour non-conformité.',
            ['product_id' => 'int', 'reason' => 'string', 'lot_id' => 'int?'],
            ['flagged' => 'boolean']
        );
    }

    // ── Documents ────────────────────────────────────────────────────────────────

    private function registerDocumentsNodes(): void
    {
        $color = '#64748B';
        $this->register('documents.signed', 'Document signé (Documents)', 'Documents', 'trigger', '✍️', $color,
            'Déclenché quand un document est signé électroniquement.',
            [],
            ['document_id' => 'int', 'title' => 'string', 'signed_by' => 'int', 'signed_at' => 'datetime']
        );
        $this->register('documents.expired', 'Document expiré (Documents)', 'Documents', 'trigger', '📅', $color,
            'Déclenché quand un document dépasse sa date de validité.',
            [],
            ['document_id' => 'int', 'title' => 'string', 'expired_at' => 'datetime', 'owner_id' => 'int']
        );
        $this->register('documents.request_signature', 'Demander une signature (Documents)', 'Documents', 'action', '✍️', $color,
            'Envoie une demande de signature électronique.',
            ['document_id' => 'int', 'signer_id' => 'int', 'message' => 'string?'],
            ['request_id' => 'int', 'sent' => 'boolean']
        );
    }

    // ── Workflow System Nodes ────────────────────────────────────────────────────

    private function registerWorkflowSystemNodes(): void
    {
        $color = '#374151';
        $this->register('workflow.schedule', 'Déclencheur planifié (Cron)', 'Workflow', 'trigger', '⏰', $color,
            'Déclenche le flux selon une expression cron.',
            ['cron' => 'string', 'timezone' => 'string?'],
            ['triggered_at' => 'datetime', 'next_run' => 'datetime']
        );
        $this->register('workflow.webhook', 'Webhook entrant', 'Workflow', 'trigger', '🔗', $color,
            'Déclenche le flux via un webhook HTTP entrant.',
            ['uuid' => 'string', 'secret' => 'string?'],
            ['payload' => 'object', 'headers' => 'object', 'method' => 'string']
        );
        $this->register('workflow.manual', 'Déclencheur manuel', 'Workflow', 'trigger', '▶️', $color,
            'Déclenche le flux manuellement depuis l\'interface.',
            ['user_id' => 'int?'],
            ['triggered_by' => 'int', 'triggered_at' => 'datetime']
        );
    }

    // ── Notify Action Nodes ──────────────────────────────────────────────────────

    private function registerNotifyActionNodes(): void
    {
        $color = '#2563EB';
        $this->register('notify.email', 'Envoyer un e-mail', 'Messaging', 'action', '📧', $color,
            'Envoie un e-mail à un destinataire.',
            ['to' => 'string', 'subject' => 'string', 'body' => 'string', 'cc' => 'string?'],
            ['message_id' => 'string', 'sent' => 'boolean']
        );
        $this->register('notify.sms', 'Envoyer un SMS', 'SMS', 'action', '📱', $color,
            'Envoie un SMS via les opérateurs africains (Orange, MTN, Airtel, Wave).',
            ['to' => 'string', 'message' => 'string', 'operator' => 'string?'],
            ['sms_id' => 'string', 'sent' => 'boolean']
        );
        $this->register('notify.in_app', 'Notification in-app', 'RealTime', 'action', '🔔', $color,
            'Envoie une notification temps-réel dans l\'application.',
            ['user_id' => 'int', 'title' => 'string', 'body' => 'string', 'action_url' => 'string?'],
            ['notification_id' => 'int', 'sent' => 'boolean']
        );
        $this->register('notify.whatsapp', 'Message WhatsApp', 'WhatsApp', 'action', '💬', $color,
            'Envoie un message via l\'API WhatsApp Business.',
            ['to' => 'string', 'template' => 'string', 'params' => 'array?'],
            ['message_id' => 'string', 'sent' => 'boolean']
        );
    }

    // ── Document Action Nodes ────────────────────────────────────────────────────

    private function registerDocumentActionNodes(): void
    {
        $color = '#64748B';
        $this->register('documents.generate_pdf', 'Générer un PDF', 'Documents', 'action', '📄', $color,
            'Génère un PDF à partir d\'un modèle (devis, facture, bon de commande…).',
            ['template' => 'string', 'data' => 'object', 'filename' => 'string?'],
            ['document_id' => 'int', 'pdf_url' => 'string']
        );
        $this->register('documents.send', 'Envoyer un document', 'Documents', 'action', '📤', $color,
            'Envoie un document généré par e-mail ou WhatsApp.',
            ['document_id' => 'int', 'recipient_email' => 'string', 'message' => 'string?'],
            ['sent' => 'boolean']
        );
    }

    // ── AI Action Nodes ──────────────────────────────────────────────────────────

    private function registerAiActionNodes(): void
    {
        $color = '#7C3AED';
        $this->register('ai.analyze', 'Analyser avec Claude AI', 'AI', 'ai_action', '🤖', $color,
            'Analyse des données ou du texte avec Claude (Anthropic API).',
            ['prompt' => 'string', 'data' => 'object?', 'locale' => 'string?'],
            ['analysis' => 'string', 'structured' => 'object?']
        );
        $this->register('ai.classify', 'Classifier avec Claude AI', 'AI', 'ai_action', '🏷️', $color,
            'Classifie une entrée en catégories avec Claude.',
            ['text' => 'string', 'categories' => 'array', 'locale' => 'string?'],
            ['category' => 'string', 'confidence' => 'number']
        );
        $this->register('ai.suggest', 'Suggestions Claude AI', 'AI', 'ai_action', '💡', $color,
            'Génère des suggestions contextuelles avec Claude.',
            ['context' => 'object', 'module' => 'string', 'action' => 'string', 'locale' => 'string?'],
            ['suggestions' => 'array', 'next_actions' => 'array']
        );
    }

    // ── HTTP Action Nodes ────────────────────────────────────────────────────────

    private function registerHttpActionNodes(): void
    {
        $color = '#0891B2';
        $this->register('http.post', 'HTTP POST (webhook sortant)', 'Integration', 'action', '🌐', $color,
            'Effectue une requête HTTP POST vers une URL externe.',
            ['url' => 'string', 'payload' => 'object', 'headers' => 'object?', 'timeout' => 'int?'],
            ['status_code' => 'int', 'response' => 'object', 'success' => 'boolean']
        );
        $this->register('http.get', 'HTTP GET (appel API)', 'Integration', 'action', '🌐', $color,
            'Effectue une requête HTTP GET vers une API externe.',
            ['url' => 'string', 'params' => 'object?', 'headers' => 'object?'],
            ['status_code' => 'int', 'response' => 'object', 'success' => 'boolean']
        );
    }

    // ── Data Transform Nodes ─────────────────────────────────────────────────────

    private function registerDataTransformNodes(): void
    {
        $color = '#0284C7';
        $this->register('data.transform', 'Transformer les données', 'Workflow', 'transform', '🔀', $color,
            'Applique des transformations JSONPath/template aux données du flux.',
            ['template' => 'string', 'input' => 'object?'],
            ['output' => 'object']
        );
    }

    // ── Delay Nodes ──────────────────────────────────────────────────────────────

    private function registerDelayNodes(): void
    {
        $this->register('delay.wait', 'Attendre (délai)', 'Workflow', 'delay', '⏳', '#6B7280',
            'Suspend l\'exécution du flux pour une durée définie.',
            ['duration' => 'int', 'unit' => 'string'],   // unit: minutes|hours|days
            ['waited_seconds' => 'int', 'resumed_at' => 'datetime']
        );
    }

    // ── Condition Nodes ──────────────────────────────────────────────────────────

    private function registerConditionNodes(): void
    {
        $color = '#D97706';
        $this->register('condition.if_else', 'Si / Sinon', 'Workflow', 'condition', '↔️', $color,
            'Évalue une expression et route vers la branche vraie ou fausse.',
            ['expression' => 'string'],
            ['result' => 'boolean', 'branch' => 'string']
        );
        $this->register('condition.switch', 'Aiguillage multi-cas', 'Workflow', 'condition', '🔀', $color,
            'Route vers différentes branches selon la valeur d\'un champ.',
            ['field' => 'string', 'cases' => 'array'],
            ['matched_case' => 'string']
        );
        $this->register('condition.amount_threshold', 'Seuil de montant (OHADA)', 'Workflow', 'condition', '💰', $color,
            'Évalue un montant par rapport à des seuils OHADA en XOF.',
            ['amount_field' => 'string', 'threshold' => 'number', 'currency' => 'string?'],
            ['above_threshold' => 'boolean', 'amount' => 'number', 'currency' => 'string']
        );
    }

    // ── Transform Nodes ──────────────────────────────────────────────────────────

    private function registerTransformNodes(): void
    {
        $color = '#0284C7';
        $this->register('transform.map_fields', 'Remapper les champs', 'Workflow', 'transform', '🗺️', $color,
            'Renomme/regroupe les clés JSON d\'un objet.',
            ['mapping' => 'object'],
            ['output' => 'object']
        );
        $this->register('transform.filter_array', 'Filtrer un tableau', 'Workflow', 'transform', '🔍', $color,
            'Filtre les éléments d\'un tableau selon une condition.',
            ['array_field' => 'string', 'condition' => 'string'],
            ['filtered' => 'array', 'count' => 'int']
        );
        $this->register('transform.format_date', 'Formater une date', 'Workflow', 'transform', '📅', $color,
            'Formate une date en tenant compte du calendrier fiscal africain.',
            ['date_field' => 'string', 'format' => 'string', 'timezone' => 'string?'],
            ['formatted_date' => 'string']
        );
        $this->register('transform.currency_convert', 'Convertir une devise', 'Workflow', 'transform', '💱', $color,
            'Convertit un montant entre devises (XOF/EUR/USD/CNY/etc.).',
            ['amount_field' => 'string', 'from_currency' => 'string', 'to_currency' => 'string'],
            ['converted_amount' => 'number', 'rate' => 'number', 'to_currency' => 'string']
        );
    }

    // ── Additional Module Nodes (stub coverage for remaining modules) ─────────────

    private function registerAdditionalModuleNodes(): void
    {
        // BI / Analytics
        $this->register('bi.report.generated', 'Rapport BI généré', 'BI', 'trigger', '📊', '#1D4ED8',
            'Déclenché quand un rapport BI est généré.',
            [], ['report_id' => 'int', 'type' => 'string', 'period' => 'string']
        );
        $this->register('bi.generate_report', 'Générer un rapport BI', 'BI', 'action', '📊', '#1D4ED8',
            'Génère un rapport BI pour une période donnée.',
            ['report_type' => 'string', 'period' => 'string', 'modules' => 'array?'],
            ['report_id' => 'int', 'pdf_url' => 'string?']
        );
        // Analytics
        $this->register('analytics.anomaly.detected', 'Anomalie détectée (Analytics)', 'Analytics', 'trigger', '🔎', '#7C3AED',
            'Déclenché quand une anomalie statistique est détectée.',
            [], ['anomaly_type' => 'string', 'module' => 'string', 'score' => 'number']
        );
        // Reporting
        $this->register('reporting.schedule_report', 'Planifier un rapport (Reporting)', 'Reporting', 'action', '📋', '#374151',
            'Planifie l\'envoi automatique d\'un rapport.',
            ['report_id' => 'int', 'recipients' => 'array', 'cron' => 'string'],
            ['scheduled' => 'boolean']
        );
        // Timesheets
        $this->register('timesheets.overtime.detected', 'Heures supp détectées (Timesheets)', 'Timesheets', 'trigger', '⏱️', '#F59E0B',
            'Déclenché quand des heures supplémentaires sont détectées.',
            [], ['employee_id' => 'int', 'overtime_hours' => 'number', 'week' => 'string']
        );
        $this->register('timesheets.log_time', 'Enregistrer des heures (Timesheets)', 'Timesheets', 'action', '⏱️', '#F59E0B',
            'Enregistre des heures travaillées pour un employé.',
            ['employee_id' => 'int', 'hours' => 'number', 'project_id' => 'int?', 'date' => 'date'],
            ['entry_id' => 'int']
        );
        // Contracts
        $this->register('contracts.contract.expiring', 'Contrat expirant (Contrats)', 'Contracts', 'trigger', '📋', '#6366F1',
            'Déclenché 30 jours avant expiration d\'un contrat.',
            [], ['contract_id' => 'int', 'type' => 'string', 'expiry_date' => 'date', 'party_id' => 'int']
        );
        $this->register('contracts.renew', 'Renouveler un contrat (Contrats)', 'Contracts', 'action', '🔄', '#6366F1',
            'Lance le workflow de renouvellement d\'un contrat.',
            ['contract_id' => 'int', 'new_end_date' => 'date'],
            ['renewed' => 'boolean', 'new_contract_id' => 'int?']
        );
        // Assets
        $this->register('assets.asset.maintenance_due', 'Maintenance actif due (Actifs)', 'Assets', 'trigger', '🔧', '#78716C',
            'Déclenché quand un actif atteint son prochain jalon de maintenance.',
            [], ['asset_id' => 'int', 'asset_name' => 'string', 'maintenance_type' => 'string', 'due_date' => 'date']
        );
        $this->register('assets.schedule_maintenance', 'Planifier maintenance (Actifs)', 'Assets', 'action', '🔧', '#78716C',
            'Planifie une intervention de maintenance pour un actif.',
            ['asset_id' => 'int', 'technician_id' => 'int?', 'scheduled_date' => 'date'],
            ['maintenance_id' => 'int']
        );
        // Settings / Core
        $this->register('settings.user.created', 'Utilisateur créé (Paramètres)', 'Settings', 'trigger', '👥', '#374151',
            'Déclenché lors de la création d\'un nouveau compte utilisateur.',
            [], ['user_id' => 'int', 'email' => 'string', 'role' => 'string']
        );
        // MobileSync
        $this->register('mobilesync.sync.completed', 'Synchronisation mobile terminée', 'MobileSync', 'trigger', '📱', '#0EA5E9',
            'Déclenché quand un appareil mobile termine sa synchronisation.',
            [], ['device_id' => 'string', 'user_id' => 'int', 'records_synced' => 'int']
        );
        // AuditLog
        $this->register('auditlog.action.logged', 'Action auditée (Audit)', 'AuditLog', 'trigger', '🔍', '#374151',
            'Déclenché quand une action critique est journalisée dans l\'audit.',
            [], ['action' => 'string', 'user_id' => 'int', 'module' => 'string', 'record_id' => 'int?']
        );
        // Discussion/Messaging
        $this->register('discussion.message.received', 'Message reçu (Discussion)', 'Discussion', 'trigger', '💬', '#10B981',
            'Déclenché lors de la réception d\'un nouveau message interne.',
            [], ['message_id' => 'int', 'from_user_id' => 'int', 'channel_id' => 'int', 'content_preview' => 'string']
        );
        // Email marketing
        $this->register('email.campaign.sent', 'Campagne email envoyée (Email)', 'Email', 'trigger', '📧', '#2563EB',
            'Déclenché quand une campagne email est envoyée.',
            [], ['campaign_id' => 'int', 'recipients_count' => 'int', 'open_rate' => 'number?']
        );
        $this->register('email.send_campaign', 'Envoyer une campagne (Email)', 'Email', 'action', '📧', '#2563EB',
            'Envoie une campagne email à une liste de contacts.',
            ['campaign_id' => 'int', 'list_id' => 'int'],
            ['sent' => 'boolean', 'recipients_count' => 'int']
        );
        // MarketingAutomation
        $this->register('marketing.lead.scored', 'Lead scoré (Marketing)', 'MarketingAutomation', 'trigger', '📈', '#EC4899',
            'Déclenché quand le score d\'un lead franchit un seuil.',
            [], ['lead_id' => 'int', 'new_score' => 'number', 'threshold' => 'number']
        );
        // CustomerService
        $this->register('customerservice.feedback.negative', 'Feedback négatif (SAV)', 'CustomerService', 'trigger', '😡', '#EF4444',
            'Déclenché quand un retour client est négatif (score ≤ 2).',
            [], ['feedback_id' => 'int', 'customer_id' => 'int', 'score' => 'int', 'comment' => 'string']
        );
        // Security
        $this->register('security.intrusion.detected', 'Intrusion détectée (Sécurité)', 'Security', 'trigger', '🔐', '#DC2626',
            'Déclenché quand le module Sécurité détecte une intrusion.',
            [], ['event_type' => 'string', 'ip' => 'string', 'user_id' => 'int?', 'severity' => 'string']
        );
        // Setup (onboarding)
        $this->register('setup.import.completed', 'Import données terminé (Setup)', 'Setup', 'trigger', '📊', '#0EA5E9',
            'Déclenché quand un import de données (CSV/Excel/PDF) est terminé.',
            [], ['import_id' => 'int', 'module' => 'string', 'records_imported' => 'int', 'errors' => 'int']
        );
        // Planning
        $this->register('planning.schedule.conflict', 'Conflit de planning (Planning)', 'Planning', 'trigger', '⚠️', '#F59E0B',
            'Déclenché quand un conflit de planning est détecté.',
            [], ['resource_id' => 'int', 'resource_type' => 'string', 'conflict_date' => 'date']
        );
        // API / Integration
        $this->register('api.rate_limit.exceeded', 'Limite API dépassée (API)', 'API', 'trigger', '🚦', '#EF4444',
            'Déclenché quand un client API dépasse son quota.',
            [], ['client_id' => 'string', 'endpoint' => 'string', 'limit' => 'int']
        );


        // ── Phase-52 missing actions (SMS, Payroll standalone, CS, Notes, SmartTable, Reporting, Shared) ──
        $this->register('sms.send_alert', 'Envoyer SMS alerte', 'SMS', 'action', '📱', '#16A34A',
            'Envoie un SMS d\'alerte à un destinataire.',
            ['phone' => 'string', 'message' => 'string', 'provider' => 'string?'],
            ['status' => 'string', 'recipient' => 'string']
        );
        $this->register('sms.send_notification', 'Envoyer SMS notification', 'SMS', 'action', '📱', '#16A34A',
            'Envoie une notification SMS.',
            ['phone' => 'string', 'message' => 'string'],
            ['status' => 'string']
        );
        $this->register('sms.campaign.sent', 'Campagne SMS envoyée', 'SMS', 'trigger', '📱', '#16A34A',
            'Déclenché quand une campagne SMS est envoyée.',
            [], ['campaign_id' => 'int', 'recipients_count' => 'int']
        );
        $this->register('payroll.run.generated', 'Paie générée (Payroll)', 'Payroll', 'trigger', '💰', '#B45309',
            'Déclenché quand une fiche de paie est générée pour un employé.',
            [], ['run_id' => 'int', 'period' => 'string', 'employee_count' => 'int']
        );
        $this->register('payroll.generate_run', 'Générer une paie (Payroll)', 'Payroll', 'action', '💰', '#B45309',
            'Lance la génération des fiches de paie pour une période.',
            ['period' => 'string', 'tenant_id' => 'int?'],
            ['run_id' => 'int?', 'period' => 'string']
        );
        $this->register('payroll.approve_payslip', 'Approuver une fiche de paie', 'Payroll', 'action', '✅', '#B45309',
            'Approuve une fiche de paie individuelle.',
            ['payslip_id' => 'int', 'approved_by' => 'int?'],
            ['status' => 'string']
        );
        $this->register('customerservice.escalate_ticket', 'Escalader un ticket (SAV)', 'CustomerService', 'action', '📞', '#DC2626',
            'Escalade un ticket vers un niveau supérieur.',
            ['ticket_id' => 'int', 'escalate_to' => 'string'],
            ['status' => 'string', 'escalated_to' => 'string']
        );
        $this->register('customerservice.auto_reply', 'Réponse automatique ticket (SAV)', 'CustomerService', 'action', '🤖', '#DC2626',
            'Envoie une réponse automatique à un ticket client.',
            ['ticket_id' => 'int', 'template' => 'string'],
            ['status' => 'string']
        );
        $this->register('notes.note.created', 'Note créée (Notes)', 'Notes', 'trigger', '📝', '#64748B',
            'Déclenché quand une nouvelle note est créée.',
            [], ['note_id' => 'int', 'user_id' => 'int', 'entity_type' => 'string?']
        );
        $this->register('notes.create_note', 'Créer une note (Notes)', 'Notes', 'action', '📝', '#64748B',
            'Crée une note automatiquement liée à une entité ERP.',
            ['title' => 'string', 'content' => 'string?', 'entity_type' => 'string?', 'entity_id' => 'int?'],
            ['note_id' => 'int?']
        );
        $this->register('smarttable.row.inserted', 'Ligne SmartTable insérée', 'SmartTable', 'trigger', '🗃️', '#0891B2',
            'Déclenché quand une nouvelle ligne est ajoutée à une SmartTable.',
            [], ['table_id' => 'int', 'row_id' => 'int', 'row_data' => 'array']
        );
        $this->register('smarttable.add_row', 'Ajouter une ligne SmartTable', 'SmartTable', 'action', '🗃️', '#0891B2',
            'Ajoute une ligne dans une SmartTable spécifiée.',
            ['table_id' => 'int', 'data' => 'array'],
            ['row_id' => 'int?']
        );
        $this->register('reporting.generate_report', 'Générer un rapport (Reporting)', 'Reporting', 'action', '📊', '#374151',
            'Génère un rapport selon un modèle défini.',
            ['report_id' => 'int', 'format' => 'string'],
            ['status' => 'string', 'report_id' => 'int?']
        );
        $this->register('reporting.send_report', 'Envoyer un rapport (Reporting)', 'Reporting', 'action', '📊', '#374151',
            'Envoie un rapport généré à un destinataire.',
            ['report_id' => 'int', 'email' => 'string'],
            ['status' => 'string']
        );
        $this->register('auditlog.record_event', 'Journaliser un événement (Audit)', 'AuditLog', 'action', '🔍', '#374151',
            'Ajoute manuellement une entrée dans le journal d\'audit.',
            ['module' => 'string', 'action' => 'string', 'entity_type' => 'string?'],
            ['status' => 'string']
        );
        $this->register('settings.update_setting', 'Mettre à jour un paramètre', 'Settings', 'action', '⚙️', '#6B7280',
            'Modifie un paramètre de configuration du tenant.',
            ['key' => 'string', 'value' => 'mixed'],
            ['status' => 'string']
        );
        $this->register('shared.notify_team', 'Notifier l\'équipe (Shared)', 'Shared', 'action', '📣', '#6B7280',
            'Envoie une notification à tous les membres d\'un rôle donné.',
            ['channel' => 'string', 'message' => 'string', 'role' => 'string?'],
            ['status' => 'string']
        );
        // ── Compatibility aliases (English keys used in tests/external integrations) ──
        $color = '#6B7280';
        $this->register('notification.send', 'Send Notification', 'Messaging', 'action', '🔔', $color,
            'Send an in-app or push notification.', ['user_id' => 'int', 'message' => 'string'], []
        );
        $this->register('http.request', 'HTTP Request', 'Integration', 'action', '🌐', $color,
            'Make an outbound HTTP request.', ['url' => 'string', 'method' => 'string'], ['response' => 'mixed']
        );
        $this->register('workflow.delay', 'Delay', 'Workflow', 'action', '⏱️', $color,
            'Pause workflow execution for a specified duration.', ['seconds' => 'int'], []
        );
        $this->register('log.record', 'Log Record', 'Workflow', 'action', '📝', $color,
            'Write an entry to the workflow execution log.', ['message' => 'string'], []
        );
        $this->register('approval.request', 'Request Approval', 'Workflow', 'action', '✅', $color,
            'Request approval from a user or role.', ['role' => 'string'], ['approved' => 'bool']
        );
    }

    // ── GAP #24 — Loop / Iterator / Sub-flow nodes ────────────────────────────

    private function registerLoopAndSubFlowNodes(): void
    {
        $color = '#7C3AED'; // purple — control-flow nodes

        // Iterate over an array; body nodes receive {item, index, total}
        $this->register('loop.loop_items', 'Boucle sur liste (ForEach)', 'Workflow', 'control', '🔁', $color,
            'Itère sur chaque élément d\'un tableau. Chaque itération injecte item + index dans le contexte.',
            [
                'array_path'     => 'string',  // dot-notation path to array in context
                'item_var'       => 'string?',  // alias for item in child context (default: "item")
                'index_var'      => 'string?',  // alias for index (default: "index")
            ],
            [
                'results'        => 'array',    // collected outputs from all iterations
                'iteration_count'=> 'int',
            ]
        );

        // Repeat while a condition holds; max_iterations acts as safety circuit-breaker
        $this->register('loop.while_loop', 'Boucle conditionnelle (While)', 'Workflow', 'control', '🔄', $color,
            'Répète les nœuds enfants tant qu\'une condition est vraie. max_iterations protège contre les boucles infinies.',
            [
                'condition'      => 'string',   // evaluable condition expression
                'max_iterations' => 'int?',     // default: 50
            ],
            [
                'iterations_run' => 'int',
                'stopped_by'     => 'string',   // "condition_false" | "max_iterations"
                'results'        => 'array',
            ]
        );

        // Delegate to another published flow; collect its output
        $this->register('flow.sub_flow', 'Sous-flux (Sub-flow)', 'Workflow', 'control', '🔀', $color,
            'Exécute un autre flux publié comme sous-routine et injecte son résultat dans le contexte courant.',
            [
                'sub_flow_id'    => 'int',      // PK of the referenced AutomationFlow (must be published)
                'input_mapping'  => 'array?',   // { targetContextKey: sourceContextPath }
                'output_var'     => 'string?',  // key under which sub-flow output is stored (default: "sub_flow_output")
            ],
            [
                'sub_flow_output'=> 'array',
                'sub_flow_id'    => 'int',
                'execution_id'   => 'int',
            ]
        );
    }

    // ── GAP #23 — Expression / Code nodes ────────────────────────────────────

    private function registerExpressionAndCodeNodes(): void
    {
        $color = '#0F766E'; // teal — compute nodes

        // Safe JS-subset expression evaluated server-side
        $this->register('code.expression', 'Expression sécurisée', 'Workflow', 'transform', '🧮', $color,
            'Évalue une expression (arithmétique, chaîne, ternaire) sans eval(). Accès aux champs via $context.field.',
            [
                'expression'     => 'string',   // e.g. "$context.amount * 1.18"
                'result_var'     => 'string?',   // where to store result (default: "expression_result")
            ],
            [
                'expression_result' => 'mixed',
                'error'             => 'string?',
            ]
        );

        // Sandboxed Python script node
        $this->register('code.code_node', 'Script Python sécurisé', 'Workflow', 'transform', '🐍', $color,
            'Exécute un script Python dans un sandbox (pas de filesystem, pas réseau, timeout 5s, 64MB). Résultat via output dict.',
            [
                'language'       => 'string',   // always "python_safe" for now
                'code'           => 'string',   // Python script; access context via ctx dict; set output["key"] = value
                'timeout_seconds'=> 'int?',     // default: 5, max: 10
            ],
            [
                'output'         => 'array',    // key-value dict set by the script
                'logs'           => 'array',    // print() output lines
                'error'          => 'string?',
                'timed_out'      => 'bool',
            ]
        );
    }
}
