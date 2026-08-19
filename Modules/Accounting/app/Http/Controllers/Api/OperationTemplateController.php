<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Models\OperationTemplate;

/** Chantier 15 — read-only catalogue used to populate the template picker. */
class OperationTemplateController extends Controller
{
    /** GET /accounting/operation-templates */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OperationTemplate::query()->active()->orderBy('nature')->orderBy('label')->get(),
        ]);
    }
}
