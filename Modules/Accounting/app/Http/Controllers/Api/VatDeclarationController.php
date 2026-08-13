<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Accounting\Models\VatDeclaration;
use Modules\Accounting\Services\VatDeclarationService;

/**
 * @group Accounting
 *
 * Manage VatDeclaration resources in Accounting module.
 */
class VatDeclarationController extends Controller
{
    public function __construct(
        private readonly VatDeclarationService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(VatDeclaration::latest()->paginate(25));
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', 'integer', 'min:1', 'max:12'],
            'type' => ['required', 'in:monthly,quarterly'],
        ]);

        $declaration = $this->service->calculatePeriod(
            (int) $validated['year'],
            (int) $validated['period'],
            $validated['type'],
        );

        return response()->json($declaration, 201);
    }

    public function show(VatDeclaration $vatDeclaration): JsonResponse
    {
        return response()->json($vatDeclaration);
    }

    public function report(VatDeclaration $vatDeclaration): JsonResponse
    {
        $report = $this->service->generateDeclarationReport($vatDeclaration);

        return response()->json($report);
    }

    public function exportXml(VatDeclaration $vatDeclaration): Response
    {
        $xml = $this->service->exportDeclarationXml($vatDeclaration);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="vat-declaration-'.$vatDeclaration->id.'.xml"',
        ]);
    }

    public function submit(Request $request, VatDeclaration $vatDeclaration): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
        ]);

        if ($vatDeclaration->status !== 'draft') {
            return response()->json(['message' => 'Only draft declarations can be submitted.'], 422);
        }

        $this->service->markSubmitted($vatDeclaration, $validated['reference']);

        return response()->json($vatDeclaration->fresh());
    }
}
