<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Modules\Achats\Models\Supplier;
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
        $admin = \App\Models\User::where('email', 'admin@lifemdg.com')->first();
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
    }
}
