<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * @group Accounting
 *
 * Manage SmartCategorization resources in Accounting module.
 */
class SmartCategorizationController extends Controller
{
    public function categorize(Request $request): JsonResponse
    {
        $request->validate(['description' => 'required|string', 'amount' => 'required|numeric']);
        $description = $request->input('description');
        // Règles heuristiques
        $category = match (true) {
            str_contains(strtolower($description), 'loyer') || str_contains(strtolower($description), 'bail') => 'Loyer',
            str_contains(strtolower($description), 'sncf') || str_contains(strtolower($description), 'billet') => 'Transport',
            str_contains(strtolower($description), 'restaurant') || str_contains(strtolower($description), 'repas') => 'Repas',
            str_contains(strtolower($description), 'amazon') || str_contains(strtolower($description), 'fnac') => 'Fournitures',
            str_contains(strtolower($description), 'edf') || str_contains(strtolower($description), 'energie') => 'Énergie',
            str_contains(strtolower($description), 'assurance') => 'Assurance',
            str_contains(strtolower($description), 'salaire') || str_contains(strtolower($description), 'paie') => 'Salaires',
            default => 'Autres charges',
        };

        return response()->json(['category' => $category, 'confidence' => 0.85]);
    }
}
