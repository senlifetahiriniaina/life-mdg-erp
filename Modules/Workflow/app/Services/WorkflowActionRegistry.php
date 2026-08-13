<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Illuminate\Support\Str;
use Modules\Workflow\Services\Actions\AchatsInventoryActionHandler;
use Modules\Workflow\Services\Actions\InventoryAccountingActionHandler;
use Modules\Workflow\Services\Actions\NotificationActionHandler;

/**
 * WorkflowActionRegistry
 *
 * Central registry that maps action key prefixes (e.g. 'achats', 'inventory',
 * 'accounting', 'notify', 'approval') to their handler objects.
 *
 * Usage:
 *   $registry = app(WorkflowActionRegistry::class);
 *   $callable = $registry->resolve('accounting.book_purchase_invoice');
 *   $result   = $callable($params, $context);
 *
 * The WorkflowEngineService can optionally delegate to this registry instead
 * of its own inline match() block, allowing handlers to be added without
 * touching the engine.
 */
class WorkflowActionRegistry
{
    /**
     * @var array<string, object>  prefix → handler instance
     */
    private array $handlers = [];

    /**
     * @var array<string, array{description: string, params: array<string,string>}>
     */
    private array $actionMeta = [];

    public function __construct(
        private readonly AchatsInventoryActionHandler    $achatsInventoryHandler,
        private readonly InventoryAccountingActionHandler $inventoryAccountingHandler,
        private readonly NotificationActionHandler        $notificationHandler,
    ) {
        $this->registerDefaults();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Register a handler object under one or more action key prefixes.
     *
     * @param  string|list<string>  $actionPrefix  e.g. 'achats' or ['achats','inventory']
     * @param  object               $handler
     */
    public function register(string|array $actionPrefix, object $handler): void
    {
        foreach ((array) $actionPrefix as $prefix) {
            $this->handlers[$prefix] = $handler;
        }
    }

    /**
     * Register metadata for an action key (used by the visual builder UI).
     *
     * @param  array<string,string>  $params  ['param_name' => 'description', …]
     */
    public function registerMeta(string $actionKey, string $description, array $params = []): void
    {
        $this->actionMeta[$actionKey] = compact('description', 'params');
    }

    /**
     * Resolve an action key to a callable.
     *
     * The action key format is: "{prefix}.{method_snake_case}"
     * The method name is derived by camelCase-ing the part after the first dot.
     *
     * Examples:
     *   'accounting.book_purchase_invoice' → InventoryAccountingActionHandler::bookPurchaseInvoice
     *   'achats.auto_create_po'            → AchatsInventoryActionHandler::autoCreatePurchaseOrder
     *   'notify.email'                     → NotificationActionHandler::sendEmail
     *   'approval.request_multi_level'     → NotificationActionHandler::requestMultiLevelApproval
     *
     * @throws \RuntimeException  if the prefix is not registered or the method does not exist.
     */
    public function resolve(string $actionKey): callable
    {
        [$prefix, $methodRaw] = array_pad(explode('.', $actionKey, 2), 2, '');

        $handler = $this->handlers[$prefix] ?? null;

        if (!$handler) {
            throw new \RuntimeException("WorkflowActionRegistry: no handler registered for prefix '{$prefix}' (action key: '{$actionKey}').");
        }

        // snake_case → camelCase
        $method = Str::camel($methodRaw);

        // Some action keys use aliases (e.g. notify.email → sendEmail)
        $method = $this->resolveMethodAlias($prefix, $method);

        if (!method_exists($handler, $method)) {
            throw new \RuntimeException("WorkflowActionRegistry: handler " . get_class($handler) . " has no method '{$method}' (action key: '{$actionKey}').");
        }

        return fn(array $params, array $context) => $handler->$method($params, $context);
    }

    /**
     * Execute an action key directly (convenience wrapper around resolve()).
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function execute(string $actionKey, array $params = [], array $context = []): array
    {
        $callable = $this->resolve($actionKey);

        return $callable($params, $context);
    }

    /**
     * Return all registered action keys with their descriptions and parameter
     * definitions — consumed by the visual workflow builder.
     *
     * @return array<string, array{description: string, params: array<string,string>, prefix: string}>
     */
    public function getAvailableActions(): array
    {
        $actions = [];

        foreach ($this->handlers as $prefix => $handler) {
            $reflection = new \ReflectionClass($handler);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Skip inherited / magic methods
                if ($method->class !== get_class($handler)) {
                    continue;
                }

                $snakeMethod = Str::snake($method->getName());
                $actionKey   = "{$prefix}.{$snakeMethod}";

                // Use registered meta if available, otherwise derive from docblock
                $meta = $this->actionMeta[$actionKey] ?? [
                    'description' => $this->extractDocblockSummary($method),
                    'params'      => [],
                ];

                $actions[$actionKey] = array_merge($meta, ['prefix' => $prefix]);
            }
        }

        return $actions;
    }

    /**
     * Check whether a given action key is registered.
     */
    public function has(string $actionKey): bool
    {
        try {
            $this->resolve($actionKey);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Register all built-in action handlers for Phase 39.
     */
    private function registerDefaults(): void
    {
        // Achats ↔ Inventory
        $this->register(['achats', 'inventory'], $this->achatsInventoryHandler);

        // Inventory → Accounting
        $this->register('accounting', $this->inventoryAccountingHandler);

        // Notifications & Approvals
        $this->register(['notify', 'approval'], $this->notificationHandler);

        // ── Action metadata (for visual builder) ─────────────────────────────

        $this->registerMeta(
            'inventory.receive_purchase_order',
            'Réceptionner un bon de commande en stock',
            ['po_id' => 'ID du bon de commande', 'warehouse_id' => 'Entrepôt cible'],
        );

        $this->registerMeta(
            'inventory.reserve_for_purchase',
            'Réserver une quantité pour un achat en cours',
            ['product_id' => 'Produit', 'quantity' => 'Quantité', 'po_id' => 'BC associé'],
        );

        $this->registerMeta(
            'achats.auto_create_po',
            'Créer automatiquement un BC quand le stock passe sous le seuil',
            ['product_id' => 'Produit', 'preferred_supplier_id' => 'Fournisseur préféré'],
        );

        $this->registerMeta(
            'achats.request_quotation',
            'Envoyer une demande de devis à un ou plusieurs fournisseurs',
            ['supplier_ids' => 'IDs des fournisseurs', 'product_id' => 'Produit'],
        );

        $this->registerMeta(
            'accounting.book_purchase_invoice',
            'Comptabiliser une facture fournisseur (OHADA Cl.6/Cl.4)',
            ['invoice_id' => 'ID facture', 'amount' => 'Montant HT', 'currency' => 'Devise (défaut XOF)'],
        );

        $this->registerMeta(
            'accounting.book_stock_variation',
            'Enregistrer la variation de stock en comptabilité (OHADA Cl.3/Cl.6)',
            ['po_id' => 'BC reçu', 'amount' => 'Valeur totale du stock entré'],
        );

        $this->registerMeta(
            'accounting.generate_payment_schedule',
            'Générer un échéancier de paiement pour une facture importante',
            ['invoice_id' => 'ID facture', 'installments' => 'Nombre de versements'],
        );

        $this->registerMeta(
            'accounting.flag_for_approval',
            'Soumettre une facture à approbation OHADA (seuils 100K / 500K XOF)',
            ['invoice_id' => 'ID facture', 'amount' => 'Montant'],
        );

        $this->registerMeta(
            'notify.email',
            'Envoyer un e-mail à un rôle ou une adresse spécifique',
            ['to' => 'Destinataire (rôle ou e-mail)', 'subject_template' => 'Sujet', 'body_template' => 'Corps'],
        );

        $this->registerMeta(
            'notify.in_app',
            'Créer une notification in-app pour un ou plusieurs utilisateurs',
            ['to' => 'Utilisateur (rôle ou ID)', 'title_template' => 'Titre', 'body_template' => 'Corps'],
        );

        $this->registerMeta(
            'notify.sms',
            'Envoyer un SMS via un opérateur africain (Orange, MTN, Airtel…)',
            ['to' => 'Numéro ou rôle', 'body_template' => 'Message (max 160 car.)', 'gateway' => 'Opérateur'],
        );

        $this->registerMeta(
            'approval.request_multi_level',
            'Lancer un circuit d\'approbation multi-niveaux',
            ['levels' => '1 ou 3', 'approvers' => 'Liste ordonnée des rôles approbateurs'],
        );
    }

    /**
     * Map short action method names to their actual handler method names.
     */
    private function resolveMethodAlias(string $prefix, string $camelMethod): string
    {
        $aliases = [
            // notify.email → sendEmail
            'notify.email'  => 'sendEmail',
            'notify.inApp'  => 'sendInAppNotification',
            'notify.sms'    => 'sendSms',
            // approval.requestMultiLevel → requestMultiLevelApproval
            'approval.requestMultiLevel' => 'requestMultiLevelApproval',
        ];

        $key = "{$prefix}.{$camelMethod}";

        return $aliases[$key] ?? $camelMethod;
    }

    /**
     * Extract the first non-empty line of a method's docblock as a summary.
     */
    private function extractDocblockSummary(\ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();

        if (!$doc) {
            return '';
        }

        $lines = explode("\n", $doc);

        foreach ($lines as $line) {
            $line = trim(ltrim($line, " \t/*"));

            if ($line !== '' && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return '';
    }
}
