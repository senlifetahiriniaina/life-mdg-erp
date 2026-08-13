<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder;
use Modules\BI\Database\Seeders\BIDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\CRM\Database\Seeders\CRMDatabaseSeeder;
use Modules\Documents\Database\Seeders\DocumentsDatabaseSeeder;
use Modules\Ecommerce\Database\Seeders\EcommerceDatabaseSeeder;
use Modules\Email\Database\Seeders\EmailDatabaseSeeder;
use Modules\Helpdesk\Database\Seeders\HelpdeskDatabaseSeeder;
use Modules\HR\Database\Seeders\HRDatabaseSeeder;
use Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder;
use Modules\Manufacturing\Database\Seeders\ManufacturingDatabaseSeeder;
use Modules\POS\Database\Seeders\POSDatabaseSeeder;
use Modules\Projects\Database\Seeders\ProjectsDatabaseSeeder;
use Modules\WhatsApp\Database\Seeders\WhatsAppDatabaseSeeder;

/**
 * Seeds all per-tenant reference data after a new tenant's database is provisioned.
 * Each module seeder is idempotent (guards with ->exists() checks).
 */
class TenantDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreDatabaseSeeder::class,
            CRMDatabaseSeeder::class,
            HRDatabaseSeeder::class,
            HelpdeskDatabaseSeeder::class,
            AccountingDatabaseSeeder::class,
            InventoryDatabaseSeeder::class,
            ManufacturingDatabaseSeeder::class,
            DocumentsDatabaseSeeder::class,
            ProjectsDatabaseSeeder::class,
            EmailDatabaseSeeder::class,
            POSDatabaseSeeder::class,
            EcommerceDatabaseSeeder::class,
            BIDatabaseSeeder::class,
            WhatsAppDatabaseSeeder::class,
        ]);
    }
}
