<?php

namespace Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HRDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLeaveTypes();
    }

    private function seedLeaveTypes(): void
    {
        if (DB::table('hr_leave_types')->exists()) {
            return;
        }

        $now = now();

        DB::table('hr_leave_types')->insert([
            [
                'name' => 'Congé annuel',
                'code' => 'ANNUAL',
                'days_per_year' => 25,
                'is_paid' => true,
                'carry_forward' => true,
                'max_carry_forward_days' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Congé maladie',
                'code' => 'SICK',
                'days_per_year' => 10,
                'is_paid' => true,
                'carry_forward' => false,
                'max_carry_forward_days' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Congé maternité',
                'code' => 'MATERNITY',
                'days_per_year' => 84,
                'is_paid' => true,
                'carry_forward' => false,
                'max_carry_forward_days' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Congé paternité',
                'code' => 'PATERNITY',
                'days_per_year' => 11,
                'is_paid' => true,
                'carry_forward' => false,
                'max_carry_forward_days' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'RTT',
                'code' => 'RTT',
                'days_per_year' => 12,
                'is_paid' => true,
                'carry_forward' => false,
                'max_carry_forward_days' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Congé sans solde',
                'code' => 'UNPAID',
                'days_per_year' => 0,
                'is_paid' => false,
                'carry_forward' => false,
                'max_carry_forward_days' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
