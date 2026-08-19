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

        $this->seedInventoryDefaults();
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
        $heure = Unit::firstOrCreate(['name' => 'Heure'], ['symbol' => 'h', 'type' => 'time']);

        $matieresPremieres = Category::firstOrCreate(['name' => 'Matières premières']);
        $produitsFinis = Category::firstOrCreate(['name' => 'Produits finis']);
        $marchandises = Category::firstOrCreate(['name' => 'Marchandises']);
        $services = Category::firstOrCreate(['name' => 'Services']);

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
