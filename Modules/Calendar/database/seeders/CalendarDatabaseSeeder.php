<?php

declare(strict_types=1);

namespace Modules\Calendar\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Calendar module reference data:
 *  - Default system calendar for the admin user (id=1)
 *  - Calendar color palette constants (stored as config)
 */
class CalendarDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemCalendar();

        $this->command?->info('✓ Calendar: calendrier système seedé.');
    }

    private function seedSystemCalendar(): void
    {
        // Skip if any calendar already exists
        if (DB::table('calendar_calendars')->exists()) {
            return;
        }

        $now = now();

        // Default personal calendar for admin (user_id=1)
        DB::table('calendar_calendars')->insert([
            [
                'user_id'    => 1,
                'tenant_id'  => null,
                'name'       => 'Mon Calendrier',
                'color'      => '#3B82F6',
                'type'       => 'personal',
                'source'     => 'local',
                'is_primary' => true,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id'    => 1,
                'tenant_id'  => null,
                'name'       => 'Entreprise',
                'color'      => '#10B981',
                'type'       => 'shared',
                'source'     => 'local',
                'is_primary' => false,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id'    => 1,
                'tenant_id'  => null,
                'name'       => 'Événements ERP',
                'color'      => '#F59E0B',
                'type'       => 'module',
                'source'     => 'local',
                'is_primary' => false,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
