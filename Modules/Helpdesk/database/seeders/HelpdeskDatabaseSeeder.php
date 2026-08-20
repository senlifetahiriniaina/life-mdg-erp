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

        // hd_sla_policies carries both a legacy `*_hours` column pair (from
        // an early patch migration) and the columns the real, live model
        // (Modules\Helpdesk\Models\SlaPolicy — read by SlaService::apply()
        // on every ticket-creation hook) actually uses,
        // response_time_minutes/resolution_time_minutes. This seeder used
        // to write only the `*_hours` columns via a raw DB::table()
        // insert, silently leaving *_minutes at its schema default
        // (60/480) regardless of the intended tier — every policy this
        // seeder ever created would have applied the wrong SLA deadline
        // the moment a ticket was created. Fixed to write both column
        // pairs consistently. (This path is currently only reachable via
        // the already-documented-broken TenantDefaultSeeder/
        // ProvisionTenantJob chain — see CLAUDE.md — so this was dormant,
        // not yet observably exploited; fixed anyway rather than left as a
        // landmine for whenever that chain is repaired.)
        DB::table('hd_sla_policies')->insert([
            [
                'name' => 'Urgent — 1h/4h',
                'response_time_hours' => 1,
                'resolution_time_hours' => 4,
                'response_time_minutes' => 60,
                'resolution_time_minutes' => 240,
                'business_hours' => null,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Standard — 4h/24h',
                'response_time_hours' => 4,
                'resolution_time_hours' => 24,
                'response_time_minutes' => 240,
                'resolution_time_minutes' => 1440,
                'business_hours' => $businessHours,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Normal — 8h/72h',
                'response_time_hours' => 8,
                'resolution_time_hours' => 72,
                'response_time_minutes' => 480,
                'resolution_time_minutes' => 4320,
                'business_hours' => $businessHours,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
