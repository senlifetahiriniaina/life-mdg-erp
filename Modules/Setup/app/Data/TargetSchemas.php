<?php

declare(strict_types=1);

namespace Modules\Setup\Data;

/**
 * TargetSchemas
 *
 * Static registry of WideHalo import target entities and their field definitions.
 * Used by AiMappingService to build prompts and by the controller to list available targets.
 */
class TargetSchemas
{
    /**
     * Return field definitions for the requested module + entity.
     *
     * Each field entry:
     *  - field    : WideHalo column name
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
            // CRM — Contacts
            // ----------------------------------------------------------------
            'contacts' => [
                ['field' => 'first_name', 'label' => 'Prénom',      'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'last_name',  'label' => 'Nom',         'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'email',      'label' => 'Email',        'type' => 'email',   'required' => false],
                ['field' => 'phone',      'label' => 'Téléphone',    'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'mobile',     'label' => 'Mobile',       'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'company',    'label' => 'Entreprise',   'type' => 'string',  'required' => false, 'max' => 150],
                ['field' => 'job_title',  'label' => 'Poste',        'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'address',    'label' => 'Adresse',      'type' => 'string',  'required' => false],
                ['field' => 'city',       'label' => 'Ville',        'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'country',    'label' => 'Pays',         'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'notes',      'label' => 'Notes',        'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // CRM — Customers / Accounts
            // ----------------------------------------------------------------
            'customers' => [
                ['field' => 'name',          'label' => 'Raison sociale',  'type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'type',          'label' => 'Type',            'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'email',         'label' => 'Email',           'type' => 'email',   'required' => false],
                ['field' => 'phone',         'label' => 'Téléphone',       'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'address',       'label' => 'Adresse',         'type' => 'string',  'required' => false],
                ['field' => 'city',          'label' => 'Ville',           'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'country',       'label' => 'Pays',            'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'tax_number',    'label' => 'N° fiscal / NIF', 'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'credit_limit',  'label' => 'Plafond crédit',  'type' => 'decimal', 'required' => false],
                ['field' => 'currency',      'label' => 'Devise',          'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'notes',         'label' => 'Notes',           'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // HR — Employees
            // ----------------------------------------------------------------
            'employees' => [
                ['field' => 'first_name',     'label' => 'Prénom',             'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'last_name',      'label' => 'Nom',                'type' => 'string',  'required' => true,  'max' => 100],
                ['field' => 'email',          'label' => 'Email professionnel','type' => 'email',   'required' => false],
                ['field' => 'phone',          'label' => 'Téléphone',          'type' => 'phone',   'required' => false, 'max' => 30],
                ['field' => 'date_of_birth',  'label' => 'Date de naissance',  'type' => 'date',    'required' => false],
                ['field' => 'hire_date',      'label' => 'Date d\'embauche',   'type' => 'date',    'required' => false],
                ['field' => 'job_title',      'label' => 'Intitulé du poste',  'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'department',     'label' => 'Département',        'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'gender',         'label' => 'Genre',              'type' => 'string',  'required' => false, 'max' => 10],
                ['field' => 'national_id',    'label' => 'N° CIN / passeport', 'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'salary',         'label' => 'Salaire brut',       'type' => 'decimal', 'required' => false],
                ['field' => 'currency',       'label' => 'Devise',             'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'address',        'label' => 'Adresse',            'type' => 'string',  'required' => false],
                ['field' => 'city',           'label' => 'Ville',              'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'country',        'label' => 'Pays',               'type' => 'string',  'required' => false, 'max' => 100],
            ],

            // ----------------------------------------------------------------
            // Inventory — Products
            // ----------------------------------------------------------------
            'products' => [
                ['field' => 'name',           'label' => 'Nom du produit',    'type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'sku',            'label' => 'Référence / SKU',   'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'barcode',        'label' => 'Code-barres',       'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'description',    'label' => 'Description',       'type' => 'string',  'required' => false],
                ['field' => 'category',       'label' => 'Catégorie',         'type' => 'string',  'required' => false, 'max' => 100],
                ['field' => 'unit',           'label' => 'Unité',             'type' => 'string',  'required' => false, 'max' => 20],
                ['field' => 'purchase_price', 'label' => 'Prix d\'achat',     'type' => 'decimal', 'required' => false],
                ['field' => 'sale_price',     'label' => 'Prix de vente',     'type' => 'decimal', 'required' => false],
                ['field' => 'tax_rate',       'label' => 'Taux TVA (%)',      'type' => 'decimal', 'required' => false],
                ['field' => 'stock_quantity', 'label' => 'Quantité en stock', 'type' => 'decimal', 'required' => false],
                ['field' => 'reorder_level',  'label' => 'Seuil de commande', 'type' => 'decimal', 'required' => false],
                ['field' => 'currency',       'label' => 'Devise',            'type' => 'string',  'required' => false, 'max' => 3],
            ],

            // ----------------------------------------------------------------
            // Achats — Suppliers / Fournisseurs
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
                ['field' => 'website',     'label' => 'Site web',        'type' => 'string',  'required' => false, 'max' => 255],
                ['field' => 'notes',       'label' => 'Notes',           'type' => 'string',  'required' => false],
            ],

            // ----------------------------------------------------------------
            // Accounting — Chart of Accounts (OHADA / SYSCOHADA compatible)
            // ----------------------------------------------------------------
            'accounts' => [
                ['field' => 'code',           'label' => 'Numéro de compte',  'type' => 'string',  'required' => true,  'max' => 20],
                ['field' => 'name',           'label' => 'Intitulé du compte','type' => 'string',  'required' => true,  'max' => 200],
                ['field' => 'type',           'label' => 'Type de compte',    'type' => 'string',  'required' => false, 'max' => 50],
                ['field' => 'parent_code',    'label' => 'Compte parent',     'type' => 'string',  'required' => false, 'max' => 20],
                ['field' => 'is_reconcilable','label' => 'Rapprochable',      'type' => 'boolean', 'required' => false],
                ['field' => 'currency',       'label' => 'Devise',            'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'description',    'label' => 'Description',       'type' => 'string',  'required' => false],
                ['field' => 'opening_balance','label' => 'Solde d\'ouverture','type' => 'decimal', 'required' => false],
            ],

            // ----------------------------------------------------------------
            // Accounting — Invoices / Factures
            // ----------------------------------------------------------------
            'invoices' => [
                ['field' => 'reference',      'label' => 'Numéro de facture', 'type' => 'string',  'required' => true,  'max' => 50],
                ['field' => 'customer_name',  'label' => 'Client',            'type' => 'string',  'required' => false, 'max' => 200],
                ['field' => 'customer_email', 'label' => 'Email client',      'type' => 'email',   'required' => false],
                ['field' => 'issue_date',     'label' => 'Date d\'émission',  'type' => 'date',    'required' => true],
                ['field' => 'due_date',       'label' => 'Date d\'échéance',  'type' => 'date',    'required' => false],
                ['field' => 'subtotal',       'label' => 'Sous-total HT',     'type' => 'decimal', 'required' => false],
                ['field' => 'tax_amount',     'label' => 'Montant TVA',       'type' => 'decimal', 'required' => false],
                ['field' => 'total',          'label' => 'Total TTC',         'type' => 'decimal', 'required' => true],
                ['field' => 'currency',       'label' => 'Devise',            'type' => 'string',  'required' => false, 'max' => 3],
                ['field' => 'status',         'label' => 'Statut',            'type' => 'string',  'required' => false, 'max' => 30],
                ['field' => 'notes',          'label' => 'Notes / libellé',   'type' => 'string',  'required' => false],
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
