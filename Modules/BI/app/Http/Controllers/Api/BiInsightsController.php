<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

/**
 * @group Controllers - Bi Insights
 *
 * Manage Bi Insights resources.
 */
class BiInsightsController extends Controller
{
    public function index(): JsonResponse
    {
        $insights = [
            [
                'id' => 1,
                'trend' => 'up',
                'title' => 'Chiffre d\'affaires en hausse',
                'text' => 'Le CA du mois en cours est en hausse de 12 % par rapport au mois précédent, principalement grâce aux ventes du canal e-commerce.',
                'module' => 'Accounting',
                'value' => '+12%',
                'severity' => 'success',
            ],
            [
                'id' => 2,
                'trend' => 'down',
                'title' => 'Temps de résolution des tickets en augmentation',
                'text' => 'Le délai moyen de résolution des tickets support a augmenté de 8 % cette semaine. Envisagez d\'affecter des ressources supplémentaires.',
                'module' => 'Helpdesk',
                'value' => '+8%',
                'severity' => 'warning',
            ],
            [
                'id' => 3,
                'trend' => 'neutral',
                'title' => 'Stock stable sur les produits clés',
                'text' => 'Les niveaux de stock des 10 produits les plus vendus restent stables. Aucun risque de rupture détecté dans les 30 prochains jours.',
                'module' => 'Inventory',
                'value' => '→ stable',
                'severity' => 'info',
            ],
        ];

        return response()->json(['data' => $insights]);
    }
}
