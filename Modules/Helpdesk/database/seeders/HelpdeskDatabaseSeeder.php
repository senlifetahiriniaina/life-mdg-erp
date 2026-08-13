<?php

namespace Modules\Helpdesk\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HelpdeskDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultTeam();
        $this->seedDefaultSLAPolicies();
    }

    private function seedDefaultTeam(): void
    {
        if (DB::table('hd_teams')->exists()) {
            return;
        }

        DB::table('hd_teams')->insert([
            'name' => 'Support',
            'email' => null,
            'auto_assignment' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedDefaultSLAPolicies(): void
    {
        if (DB::table('hd_sla_policies')->exists()) {
            return;
        }

        $businessHours = json_encode([
            'start' => '09:00',
            'end' => '18:00',
            'days' => [1, 2, 3, 4, 5], // Mon-Fri
        ]);

        $now = now();

        DB::table('hd_sla_policies')->insert([
            [
                'name' => 'Urgent — 1h/4h',
                'response_time_hours' => 1,
                'resolution_time_hours' => 4,
                'business_hours' => null,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Standard — 4h/24h',
                'response_time_hours' => 4,
                'resolution_time_hours' => 24,
                'business_hours' => $businessHours,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Normal — 8h/72h',
                'response_time_hours' => 8,
                'resolution_time_hours' => 72,
                'business_hours' => $businessHours,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
