<?php

namespace Modules\Analytics\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Analytics\Models\ForecastModel;

/**
 * Amorce 6 modèles de prévision de démonstration pour le tenant_id=1.
 *
 * Exécution :
 *   php artisan db:seed --class="Modules\Analytics\Database\Seeders\ForecastModelSeeder"
 */
class ForecastModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            // 1. Prévision de la demande — produit principal
            [
                'name'             => 'Prévision de la demande — Produit principal',
                'module'           => 'demand',
                'entity_type'      => 'product',
                'entity_id'        => 1,
                'algorithm'        => 'linear_regression',
                'horizon_days'     => 90,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => null,
            ],

            // 2. Prévision de trésorerie 90 jours — IA Claude
            [
                'name'             => 'Prévision de trésorerie 90 jours',
                'module'           => 'cashflow',
                'entity_type'      => 'account',
                'entity_id'        => null,
                'algorithm'        => 'ai_claude',
                'horizon_days'     => 90,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => [
                    'ohada_classes' => ['5'],
                    'currency'      => 'XOF',
                ],
            ],

            // 3. Prévision RH — effectifs requis
            [
                'name'             => 'Prévision RH — Effectifs requis',
                'module'           => 'hr',
                'entity_type'      => 'employee',
                'entity_id'        => null,
                'algorithm'        => 'moving_average',
                'horizon_days'     => 180,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => [
                    'periods'         => 4, // 4 semaines
                    'include_turnover' => true,
                ],
            ],

            // 4. Prévision de production — lissage exponentiel
            [
                'name'             => 'Prévision de production — Ordres de fabrication',
                'module'           => 'production',
                'entity_type'      => null,
                'entity_id'        => null,
                'algorithm'        => 'exponential_smoothing',
                'horizon_days'     => 60,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => [
                    'alpha' => 0.3, // lissage du niveau
                    'beta'  => 0.1, // lissage de la tendance
                    'gamma' => 0.2, // lissage de la saisonnalité
                ],
            ],

            // 5. Prévision des revenus — IA Claude (horizon annuel)
            [
                'name'             => 'Prévision des revenus annuels',
                'module'           => 'revenue',
                'entity_type'      => null,
                'entity_id'        => null,
                'algorithm'        => 'ai_claude',
                'horizon_days'     => 365,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => [
                    'include_seasonality' => true,
                    'african_events'      => true, // Ramadan, Tabaski, rentrée
                ],
            ],

            // 6. Prévision des stocks — régression linéaire 30 jours
            [
                'name'             => 'Prévision des niveaux de stock',
                'module'           => 'inventory',
                'entity_type'      => 'product',
                'entity_id'        => null,
                'algorithm'        => 'linear_regression',
                'horizon_days'     => 30,
                'confidence_level' => 0.0,
                'is_active'        => true,
                'config'           => [
                    'safety_stock_days' => 7,
                    'alert_threshold'   => 0.2, // alerte si stock < 20 % du min habituel
                ],
            ],
        ];

        foreach ($models as $data) {
            ForecastModel::firstOrCreate(
                [
                    'tenant_id' => 1,
                    'name'      => $data['name'],
                ],
                array_merge($data, [
                    'tenant_id'      => 1,
                    'next_retrain_at' => now()->addDays(7),
                ])
            );
        }

        $this->command?->info('ForecastModelSeeder : 6 modèles de démonstration créés pour tenant_id=1.');
    }
}
