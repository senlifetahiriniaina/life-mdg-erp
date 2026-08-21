<?php

declare(strict_types=1);

namespace Modules\Setup\Data;

/**
 * TargetSchemas
 *
 * Static registry of Life MDG import target entities and their field definitions.
 * Used by AiMappingService to build prompts and by the controller to list available targets.
 *
 * Chantier 32.10 (deep 14-layer audit): this registry was found to have two
 * compounding, previously-undocumented bugs, both fixed here.
 *
 * 1. SECURITY (critical) — the field list below is what gets *offered* to the
 *    caller for mapping, but the actual write destination
 *    (ImportExecutorService::resolveTargetTable()) used to trust a
 *    completely client-controlled `target_table` string with zero allowlist
 *    — any authenticated Setup-module user could set `target_table` to ANY
 *    real table in the whole database (`users`, `core_secrets`,
 *    `security_encryption_keys`, ...) and `target_field` to any column name,
 *    and the import pipeline would happily `DB::table($targetTable)->insert()`
 *    into it. Fixed by adding TABLES below — a real, hardcoded
 *    module+entity → table allowlist that `ImportExecutorService` now
 *    consults exclusively; the client-supplied `target_table` value is no
 *    longer trusted for resolution at all (see that class's own docblock).
 *
 * 2. DATA INTEGRITY — most of the field names below never matched the real
 *    target table's actual columns (confirmed via `Schema::getColumnListing()`
 *    against every table this class targets, not by reading the code):
 *    e.g. 'customer_name'/'issue_date'/'reference' on Accounting/invoices vs
 *    the real `acc_invoices.partner_name`/`invoice_date`/`number`; 'company'/
 *    'address'/'city'/'country' on CRM/contacts, none of which exist as
 *    columns on `crm_contacts` at all. Every one of these was silently
 *    dropped by nothing (this pipeline never filtered to real columns before
 *    this chantier — see ImportExecutorService::insertBatch()), meaning a
 *    real onboarding import through the real, live `ImportDataFlow.vue`
 *    wizard UI would either insert with missing columns or fatal outright
 *    (`acc_invoices`/`crm_accounts`/`hr_employees`/etc. never even resolved
 *    to a real table name at all — see finding 1). Field names below are now
 *    the *real* column names on the *real* target table; anything with no
 *    real column (FK-only concepts like a supplier/account "parent" that
 *    would require a lookup, e.g. Accounting/accounts' old 'parent_code', or
 *    a column that plain doesn't exist, e.g. CRM/contacts' old 'company') was
 *    removed rather than silently offered and dropped — same "don't offer a
 *    target field that quietly discards the user's data" reasoning already
 *    applied to the sibling `AiDataImportService::ENTITY_SCHEMAS` pipeline in
 *    this same chantier.
 */
class TargetSchemas
{
    /**
     * Real module+entity → destination table map, and the real per-table
     * tenant-scoping column (or null when the table has none — e.g.
     * `acc_invoices`/`acc_chart_of_accounts`, this app's shared-ledger
     * design; documented at length in CLAUDE.md).
     *
     * This is the ONLY source of truth `ImportExecutorService` consults to
     * pick a destination table — never the client-supplied
     * `FieldMapping.target_table` value (see finding 1 above).
     *
     * The tenant column choice matches each table's own REAL scoping column
     * as read by its own controller (confirmed via grep, not assumed):
     *  - crm_contacts / crm_accounts: `company_id` (ContactController /
     *    the Chantier 19 CRM-followup fix — `crm_accounts.tenant_id` is a
     *    documented phantom column, `crm_contacts.tenant_id` likewise).
     *  - achats_suppliers: `company_id` (SupplierController::companyId()).
     *  - hr_employees / inventory_products: `tenant_id` — kept as-is rather
     *    than "fixed" to `company_id`, because neither HR nor
     *    Inventory\ProductController actually scope by `company_id` yet
     *    (both are pre-existing, already-documented gaps in those other
     *    modules — see CLAUDE.md Chantier 19 Lot 2/4-5). Writing
     *    `company_id` here would be inventing a scoping contract those
     *    modules don't honor; `tenant_id` at least matches what
     *    `ProductController::store()` itself writes today, so an import and
     *    a manual create land in the same (already broken) place rather
     *    than two different broken places.
     *  - acc_invoices / acc_chart_of_accounts: null — no tenant/company
     *    column exists on either table (this app's shared-ledger design).
     *
     * @var array<string, array{table: string, tenant_column: ?string}>
     */
    private const TABLES = [
        'crm.contacts'        => ['table' => 'crm_contacts',       'tenant_column' => 'company_id'],
        'crm.customers'       => ['table' => 'crm_accounts',       'tenant_column' => 'company_id'],
        'hr.employees'        => ['table' => 'hr_employees',       'tenant_column' => 'tenant_id'],
        'inventory.products'  => ['table' => 'inventory_products', 'tenant_column' => 'tenant_id'],
        'achats.suppliers'    => ['table' => 'achats_suppliers',   'tenant_column' => 'company_id'],
        'accounting.accounts' => ['table' => 'acc_chart_of_accounts', 'tenant_column' => null],
        'accounting.invoices' => ['table' => 'acc_invoices',       'tenant_column' => null],
    ];

    /**
     * Resolve the real destination table for a module+entity pair, or null
     * if the pair is not a recognised import target — callers MUST refuse
     * to write anywhere when this returns null rather than guess a table
     * name (see ImportExecutorService::resolveTargetTable()).
     */
    public static function table(string $module, string $entity): ?string
    {
        return self::TABLES[self::key($module, $entity)]['table'] ?? null;
    }

    /**
     * Resolve the real tenant-scoping column for a module+entity pair, or
     * null when the target table has none (or the pair is unrecognised).
     */
    public static function tenantColumn(string $module, string $entity): ?string
    {
        return self::TABLES[self::key($module, $entity)]['tenant_column'] ?? null;
    }

    private static function key(string $module, string $entity): string
    {
        return strtolower($module) . '.' . strtolower($entity);
    }

    /**
     * Return field definitions for the requested module + entity.
     *
     * Each field entry:
     *  - field    : real Life MDG column name on the destination table
     *  - label    : Human-readable label (French preferred for francophone markets)
     *  - type     : 'string'|'email'|'phone'|'date'|'decimal'|'integer'|'boolean'
     *  - required : whether the field is mandatory
     *  - max      : (optional) max string length
     *
     * @return array<int, array{field: string, label: string, type: string, required: bool, max?: int}>
     */
    public static function getSchema(string $module, string $entity): array
    {
        $schemas = self::all();
        $key     = strtolower($entity);

        return $schemas[$key] ?? [];
    }

    /**
     * Return the full map of all known entities.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            // ----------------------------------------------------------------
            // CRM — Contacts (real table: crm_contacts)
            // ----------------------------------------------------------------
            'contacts' => [
                ['field' => 'first_name', 'label' => 'Prénom',      'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'last_name',  'label' => 'Nom',         'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'email',      'label' => 'Email',        'type' => 'email',   'required' => false],
                ['field' => 'phone',      'label' => 'Téléphone',    'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'mobile',     'label' => 'Mobile',       'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'job_title',  'label' => 'Poste',        'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'notes',      'label' => 'Notes',        'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // CRM — Customers / Accounts (real table: crm_accounts)
            // ----------------------------------------------------------------
            'customers' => [
                ['field' => 'name',            'label' => 'Raison sociale',  'type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'type',            'label' => 'Type',            'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'email',           'label' => 'Email',           'type' => 'email',   'required' => false],
                ['field' => 'phone',           'label' => 'Téléphone',       'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'billing_address', 'label' => 'Adresse',         'type' => 'string',  'required' => false],
                ['field' => 'billing_city',    'label' => 'Ville',           'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'billing_country', 'label' => 'Pays',            'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'currency',        'label' => 'Devise',          'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'description',     'label' => 'Notes',           'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // HR — Employees (real table: hr_employees)
            // ----------------------------------------------------------------
            'employees' => [
                ['field' => 'first_name',     'label' => 'Prénom',             'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'last_name',      'label' => 'Nom',                'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'email',          'label' => 'Email professionnel','type' => 'email',   'required' => false],
                ['field' => 'phone',          'label' => 'Téléphone',          'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'date_of_birth',  'label' => 'Date de naissance',  'type' => 'date',    'required' => false],
                ['field' => 'hire_date',      'label' => 'Date d\'embauche',   'type' => 'date',    'required' => false],
                ['field' => 'job_title',      'label' => 'Intitulé du poste',  'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'gender',         'label' => 'Genre',              'type' => 'string',  'required' => false, 'max' => 10],
                ['field' => 'national_id',    'label' => 'N° CIN / passeport', 'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'address',        'label' => 'Adresse',            'type' => 'string',  'required' => false],
                ['field' => 'country_code',   'label' => 'Pays (code ISO2)',   'type' => 'string',  'required' => false, 'max' => 2],
            ],

            // ----------------------------------------------------------------
            // Inventory — Products (real table: inventory_products)
            // ----------------------------------------------------------------
            'products' => [
                ['field' => 'name',           'label' => 'Nom du produit',    'type' => 'string',  'required' => true,  'max' => 200],
                // 'required' = true: `inventory_products.sku` is NOT NULL
                // with no default (confirmed via Schema::getColumnListing(),
                // matching the real ProductController::store()'s own
                // required validation) — every row missing it was a
                // guaranteed per-row insert failure, previously mislabelled
                // optional here (same fix applied to the sibling
                // AiDataImportService::ENTITY_SCHEMAS['products']).
                ['field' => 'sku',            'label' => 'Référence / SKU',   'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'barcode',        'label' => 'Code-barres',       'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'description',    'label' => 'Description',       'type' => 'string',  'required' => false],
                ['field' => 'category',       'label' => 'Catégorie',         'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'unit',           'label' => 'Unité',             'type' => 'string',  'required' => false, 'max' => 20],
                ['field' => 'cost_price',     'label' => 'Prix d\'achat',     'type' => 'decimal', 'required' => false],
                ['field' => 'sale_price',     'label' => 'Prix de vente',     'type' => 'decimal', 'required' => false],
                ['field' => 'reorder_level',  'label' => 'Seuil de commande', 'type' => 'decimal', 'required' => false],
                ['field' => 'currency',       'label' => 'Devise',            'type' => 'string',  'required' => false, 'max' => 3],
            ],

            // ----------------------------------------------------------------
            // Achats — Suppliers / Fournisseurs (real table: achats_suppliers)
            // ----------------------------------------------------------------
            'suppliers' => [
                ['field' => 'name',        'label' => 'Raison sociale',  'type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'email',       'label' => 'Email',           'type' => 'email',   'required' => false],
                ['field' => 'phone',       'label' => 'Téléphone',       'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'address',     'label' => 'Adresse',         'type' => 'string',  'required' => false],
                ['field' => 'city',        'label' => 'Ville',           'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'country',     'label' => 'Pays',            'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'tax_number',  'label' => 'N° fiscal / NIF', 'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'payment_terms', 'label' => 'Conditions de paiement', 'type' => 'string', 'required' => false, 'max' => 100],
                ['field' => 'currency',    'label' => 'Devise',          'type' => 'string',  'required' => false, 'max' => 3],
            ],

            // ----------------------------------------------------------------
            // Accounting — Chart of Accounts, OHADA / SYSCOHADA compatible
            // (real table: acc_chart_of_accounts)
            // ----------------------------------------------------------------
            'accounts' => [
                ['field' => 'code',           'label' => 'Numéro de compte',  'type' => 'string',  'required' => true,  'max' => 20],
                ['field' => 'name',           'label' => 'Intitulé du compte','type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'type',           'label' => 'Type de compte',    'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'description',    'label' => 'Description',       'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // Accounting — Invoices / Factures (real table: acc_invoices)
            // ----------------------------------------------------------------
            'invoices' => [
                ['field' => 'number',         'label' => 'Numéro de facture', 'type' => 'string',  'required' => true,  'max' => 50],
                ['field' => 'partner_name',   'label' => 'Client',            'type' => 'string',  'required' => false, 'max' => 200],
                ['field' => 'invoice_date',   'label' => 'Date d\'émission',  'type' => 'date',    'required' => true],
                ['field' => 'due_date',       'label' => 'Date d\'échéance',  'type' => 'date',    'required' => false],
                ['field' => 'subtotal',       'label' => 'Sous-total HT',     'type' => 'decimal', 'required' => false],
                ['field' => 'tax_amount',     'label' => 'Montant TVA',       'type' => 'decimal', 'required' => false],
                ['field' => 'total',          'label' => 'Total TTC',         'type' => 'decimal', 'required' => true],
                ['field' => 'currency',       'label' => 'Devise',            'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'status',         'label' => 'Statut',            'type' => 'string',  'required' => false, 'max' => 30],
            ],
        ];
    }

    /**
     * List all available module/entity combinations.
     *
     * @return array<int, array{module: string, entity: string, field_count: int}>
     */
    public static function availableTargets(): array
    {
        return [
            ['module' => 'CRM',        'entity' => 'contacts',  'field_count' => count(self::getSchema('CRM', 'contacts'))],
            ['module' => 'CRM',        'entity' => 'customers', 'field_count' => count(self::getSchema('CRM', 'customers'))],
            ['module' => 'HR',         'entity' => 'employees', 'field_count' => count(self::getSchema('HR', 'employees'))],
            ['module' => 'Inventory',  'entity' => 'products',  'field_count' => count(self::getSchema('Inventory', 'products'))],
            ['module' => 'Achats',     'entity' => 'suppliers', 'field_count' => count(self::getSchema('Achats', 'suppliers'))],
            ['module' => 'Accounting', 'entity' => 'accounts',  'field_count' => count(self::getSchema('Accounting', 'accounts'))],
            ['module' => 'Accounting', 'entity' => 'invoices',  'field_count' => count(self::getSchema('Accounting', 'invoices'))],
        ];
    }
}
