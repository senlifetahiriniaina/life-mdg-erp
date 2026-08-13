<?php

namespace Modules\CRM\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CRMDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultPipelines();
    }

    private function seedDefaultPipelines(): void
    {
        if (DB::table('crm_pipelines')->exists()) {
            return;
        }

        DB::table('crm_pipelines')->insert([
            [
                'name' => 'Vente',
                'is_default' => true,
                'stages' => json_encode([
                    ['name' => 'Nouveau', 'probability' => 10, 'order' => 1],
                    ['name' => 'Contacté', 'probability' => 20, 'order' => 2],
                    ['name' => 'Qualifié', 'probability' => 40, 'order' => 3],
                    ['name' => 'Proposition envoyée', 'probability' => 60, 'order' => 4],
                    ['name' => 'Négociation', 'probability' => 80, 'order' => 5],
                    ['name' => 'Gagné', 'probability' => 100, 'order' => 6],
                    ['name' => 'Perdu', 'probability' => 0, 'order' => 7],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Support Commercial',
                'is_default' => false,
                'stages' => json_encode([
                    ['name' => 'Demande reçue', 'probability' => 10, 'order' => 1],
                    ['name' => "En cours d'analyse", 'probability' => 30, 'order' => 2],
                    ['name' => 'Proposition', 'probability' => 60, 'order' => 3],
                    ['name' => 'Conclu', 'probability' => 100, 'order' => 4],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
