<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Unit;
use Modules\Setup\Models\CompanyProfile;
use Modules\Shared\Models\Currency;

/**
 * Chantier 12 — minimal, Madagascar-flavored bootstrap defaults so a fresh
 * install isn't a blank slate: a default company, customer, and supplier to
 * anchor bulk Excel/CSV imports against (see Modules/Setup's AiDataImportService),
 * instead of every module's dropdowns/relations starting genuinely empty.
 *
 * Deliberately separate from DemoSeeder, which ships illustrative
 * French/EUR sample data (CRM opportunities, HR employees, ...) meant to
 * showcase the app — this seeder is the real starting point for a tenant
 * that's about to import its own pre-existing data, not a demo.
 */
class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'PRINCIPAL'],
            [
                'name'      => 'Ma Société',
                'currency'  => 'MGA',
                'timezone'  => 'Indian/Antananarivo',
                'is_active' => true,
            ]
        );

        // Setup module's onboarding-wizard profile (distinct model/table from
        // App\Models\Company — see CLAUDE.md) carries the country/currency
        // fields SmartDefaultsService's per-country lookups are meant to be
        // anchored against. Tenant-keyed off the bootstrap admin's own id,
        // matching the convention DatabaseSeeder already uses for
        // tenant_modules.
        $admin = \App\Models\User::where('email', 'admin@life-mdg.com')->first();
        if ($admin) {
            CompanyProfile::firstOrCreate(
                ['tenant_id' => (string) $admin->id],
                [
                    'company_name'          => 'Ma Société',
                    'country_code'          => 'MG',
                    'currency_code'         => 'MGA',
                    'timezone'              => 'Indian/Antananarivo',
                    'fiscal_year_start'     => 1,
                    'onboarding_completed'  => true,
                    // SARL non assujettie à la TVA (chiffre d'affaires sous le
                    // seuil d'immatriculation) — pas de numéro de TVA, et les
                    // lignes de facture doivent rester à un taux de taxe nul
                    // par défaut (déjà le comportement de InvoiceService/
                    // Form.vue : tax_rate non fourni = 0). L'IR (impôt sur les
                    // bénéfices) reste dû, voir le compte 444 du plan comptable.
                    'vat_number'            => null,
                    'vat_exempt'            => true,
                ]
            );
        }

        Customer::firstOrCreate(
            ['name' => 'Client par défaut'],
            [
                'company_id' => $company->id,
                'email'      => null,
                'phone'      => null,
            ]
        );

        Supplier::firstOrCreate(
            ['code' => 'FOUR-DEFAUT'],
            [
                'name'       => 'Fournisseur par défaut',
                'country'    => 'MG',
                'currency'   => 'MGA',
                'is_active'  => true,
            ]
        );

        $this->seedCurrencies();
        $this->seedInventoryDefaults();
    }

    /**
     * Chantier 17 — `shared_currencies` (backing `Modules\Shared\Models\Currency`
     * and its `CurrencyController::convert()`) had a real table and a real
     * conversion endpoint but was never actually seeded anywhere in the app
     * (confirmed: zero rows on a fresh install) — every currency lookup or
     * conversion silently 404'd/failed. Fixed here rather than left as a
     * documented gap, since the new sourcing-benchmark feature below needs
     * real MGA/USD/EUR/CNY conversion to compare foreign supplier prices
     * against the local catalogue (Africa First + Asia First: the same 11
     * countries `SmartDefaultsService::COUNTRIES` already supports, plus
     * USD/EUR/CNY for the benchmarking feature's China/Europe sourcing).
     *
     * Rates are illustrative starting defaults (units of currency per 1 USD),
     * NOT a live feed — there is no FX API wired into this app. An admin
     * should update `exchange_rate_to_usd` periodically; documented in
     * CLAUDE.md rather than silently presented as authoritative.
     */
    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',        'name_fr' => 'Dollar américain', 'symbol' => '$',   'symbol_native' => '$',   'decimals' => 2, 'is_cfa' => false, 'region' => 'Global', 'rate' => 1],
            ['code' => 'EUR', 'name' => 'Euro',              'name_fr' => 'Euro',              'symbol' => '€',   'symbol_native' => '€',   'decimals' => 2, 'is_cfa' => false, 'region' => 'Europe', 'rate' => 0.92],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',       'name_fr' => 'Yuan chinois',      'symbol' => '¥',   'symbol_native' => '元',  'decimals' => 2, 'is_cfa' => false, 'region' => 'Asia',   'rate' => 7.2],
            ['code' => 'MGA', 'name' => 'Malagasy Ariary',    'name_fr' => 'Ariary malgache',   'symbol' => 'Ar',  'symbol_native' => 'Ar',  'decimals' => 0, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 4500],
            ['code' => 'XOF', 'name' => 'West African CFA Franc', 'name_fr' => 'Franc CFA (UEMOA)', 'symbol' => 'CFA', 'symbol_native' => 'CFA', 'decimals' => 0, 'is_cfa' => true, 'region' => 'Africa', 'rate' => 600],
            ['code' => 'XAF', 'name' => 'Central African CFA Franc', 'name_fr' => 'Franc CFA (CEMAC)', 'symbol' => 'FCFA', 'symbol_native' => 'FCFA', 'decimals' => 0, 'is_cfa' => true, 'region' => 'Africa', 'rate' => 600],
            ['code' => 'MAD', 'name' => 'Moroccan Dirham',    'name_fr' => 'Dirham marocain',   'symbol' => 'DH',  'symbol_native' => 'د.م.', 'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 10],
            ['code' => 'NGN', 'name' => 'Nigerian Naira',     'name_fr' => 'Naira nigérian',    'symbol' => '₦',   'symbol_native' => '₦',   'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 1500],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi',      'name_fr' => 'Cedi ghanéen',      'symbol' => 'GH₵', 'symbol_native' => 'GH₵', 'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 15],
            ['code' => 'KES', 'name' => 'Kenyan Shilling',    'name_fr' => 'Shilling kényan',   'symbol' => 'KSh', 'symbol_native' => 'KSh', 'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 130],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'name_fr' => 'Shilling tanzanien', 'symbol' => 'TSh', 'symbol_native' => 'TSh', 'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 2500],
            ['code' => 'INR', 'name' => 'Indian Rupee',       'name_fr' => 'Roupie indienne',   'symbol' => '₹',   'symbol_native' => '₹',   'decimals' => 2, 'is_cfa' => false, 'region' => 'Asia',   'rate' => 83],
            ['code' => 'EGP', 'name' => 'Egyptian Pound',     'name_fr' => 'Livre égyptienne',  'symbol' => 'E£',  'symbol_native' => 'ج.م', 'decimals' => 2, 'is_cfa' => false, 'region' => 'Africa', 'rate' => 49],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                [
                    'name'                     => $currency['name'],
                    'name_fr'                  => $currency['name_fr'],
                    'symbol'                   => $currency['symbol'],
                    'symbol_native'            => $currency['symbol_native'],
                    'decimals'                 => $currency['decimals'],
                    'is_cfa'                   => $currency['is_cfa'],
                    'is_active'                => true,
                    'exchange_rate_to_usd'     => $currency['rate'],
                    'exchange_rate_updated_at' => now(),
                    'region'                   => $currency['region'],
                ]
            );
        }
    }

    /**
     * Units/categories/products so a fresh install's Inventory dropdowns
     * aren't empty and a bulk Excel/CSV import has real categories to map
     * "matière première"/"produit fini"/"marchandise" rows onto — this
     * module has no dedicated raw-material/finished-good flag of its own
     * (confirmed: `Product::$fillable`'s `type` only distinguishes
     * storable/consumable/service, a stock-tracking concept, not a
     * production-stage one), so the distinction here is by Category, the
     * same convention the rest of this module already uses.
     */
    private function seedInventoryDefaults(): void
    {
        $piece = Unit::firstOrCreate(['name' => 'Pièce'], ['symbol' => 'pc', 'type' => 'unit']);
        $kg = Unit::firstOrCreate(['name' => 'Kilogramme'], ['symbol' => 'kg', 'type' => 'weight']);
        Unit::firstOrCreate(['name' => 'Litre'], ['symbol' => 'L', 'type' => 'volume']);
        Unit::firstOrCreate(['name' => 'Mètre'], ['symbol' => 'm', 'type' => 'length']);
        // Chantier 17 — le cuir et certains tissus enduits se vendent au m²,
        // pas au mètre linéaire.
        Unit::firstOrCreate(['name' => 'Mètre carré'], ['symbol' => 'm²', 'type' => 'area']);
        $heure = Unit::firstOrCreate(['name' => 'Heure'], ['symbol' => 'h', 'type' => 'time']);

        $matieresPremieres = Category::firstOrCreate(['name' => 'Matières premières']);
        $produitsFinis = Category::firstOrCreate(['name' => 'Produits finis']);
        $marchandises = Category::firstOrCreate(['name' => 'Marchandises']);
        $services = Category::firstOrCreate(['name' => 'Services']);
        // Chantier 17 — domaine vestimentaire : les accessoires (boutons,
        // fermetures, fil, étiquettes...) et les vêtements semi-finis
        // (production spécifique en cours) sont des catégories de stock
        // distinctes des matières premières brutes et des produits finis,
        // avec leur propre routage comptable (312/335 — voir
        // AccountingDatabaseSeeder).
        $accessoires = Category::firstOrCreate(['name' => 'Accessoires']);
        $semiFinis = Category::firstOrCreate(['name' => 'Vêtements semi-finis']);

        // Chart-of-accounts routing per category — codes only (see the
        // migration's docblock for why not a FK), so this seeder stays
        // independent of whether AccountingDatabaseSeeder has run yet.
        $accountMappings = [
            $matieresPremieres->id => ['default_stock_account_code' => '310', 'default_purchase_account_code' => '601', 'default_sale_account_code' => null, 'default_variance_account_code' => '6031'],
            $accessoires->id       => ['default_stock_account_code' => '312', 'default_purchase_account_code' => '602', 'default_sale_account_code' => null, 'default_variance_account_code' => '6032'],
            $semiFinis->id         => ['default_stock_account_code' => '335', 'default_purchase_account_code' => null,  'default_sale_account_code' => null, 'default_variance_account_code' => '6035'],
            $produitsFinis->id     => ['default_stock_account_code' => '355', 'default_purchase_account_code' => null,  'default_sale_account_code' => '701', 'default_variance_account_code' => '7135'],
            $marchandises->id      => ['default_stock_account_code' => '370', 'default_purchase_account_code' => '607', 'default_sale_account_code' => '707', 'default_variance_account_code' => '6037'],
            $services->id          => ['default_stock_account_code' => null,  'default_purchase_account_code' => null,  'default_sale_account_code' => '706', 'default_variance_account_code' => null],
        ];
        foreach ($accountMappings as $categoryId => $codes) {
            Category::whereKey($categoryId)->update($codes);
        }

        Product::firstOrCreate(
            ['sku' => 'MP-DEFAUT'],
            [
                'name'        => 'Matière première par défaut',
                'category_id' => $matieresPremieres->id,
                'unit_id'     => $kg->id,
                'type'        => 'storable',
                'currency'    => 'MGA',
                'is_active'   => true,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'PF-DEFAUT'],
            [
                'name'        => 'Produit fini par défaut',
                'category_id' => $produitsFinis->id,
                'unit_id'     => $piece->id,
                'type'        => 'storable',
                'currency'    => 'MGA',
                'is_active'   => true,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'MD-DEFAUT'],
            [
                'name'        => 'Marchandise par défaut',
                'category_id' => $marchandises->id,
                'unit_id'     => $piece->id,
                'type'        => 'storable',
                'currency'    => 'MGA',
                'is_active'   => true,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'SV-DEFAUT'],
            [
                'name'        => 'Service par défaut',
                'category_id' => $services->id,
                'unit_id'     => $heure->id,
                'type'        => 'service',
                'currency'    => 'MGA',
                'is_active'   => true,
            ]
        );
    }
}
