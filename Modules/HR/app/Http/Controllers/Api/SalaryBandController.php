<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\SalaryBand;

/**
 * @group HR - Compensation Planning
 */
class SalaryBandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalaryBand::class);

        $bands = SalaryBand::query()
            ->when($request->currency, fn ($q, $v) => $q->where('currency', $v))
            ->orderBy('level')
            ->get();

        return response()->json($bands);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SalaryBand::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'level' => 'required|string|max:50',
            'min_salary' => 'required|numeric|min:0',
            'mid_salary' => 'required|numeric|min:0',
            'max_salary' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
        ]);

        $band = SalaryBand::create($validated);

        return response()->json($band, 201);
    }

    public function show(SalaryBand $salaryBand): JsonResponse
    {
        $this->authorize('view', $salaryBand);

        return response()->json($salaryBand);
    }

    public function update(Request $request, SalaryBand $salaryBand): JsonResponse
    {
        $this->authorize('update', $salaryBand);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'level' => 'sometimes|string|max:50',
            'min_salary' => 'sometimes|numeric|min:0',
            'mid_salary' => 'sometimes|numeric|min:0',
            'max_salary' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
        ]);

        $salaryBand->update($validated);

        return response()->json($salaryBand);
    }

    public function destroy(SalaryBand $salaryBand): JsonResponse
    {
        $this->authorize('delete', $salaryBand);

        $salaryBand->delete();

        return response()->json(null, 204);
    }

    /**
     * Simulate a raise percentage on a salary band.
     */
    public function simulateRaise(Request $request, SalaryBand $salaryBand): JsonResponse
    {
        $this->authorize('simulateRaise', $salaryBand);

        $validated = $request->validate([
            'raise_pct' => 'required|numeric|min:0|max:100',
        ]);

        $result = $salaryBand->simulateRaise((float) $validated['raise_pct']);

        return response()->json([
            'band' => $salaryBand,
            'simulated' => $result,
            'raise_pct' => $validated['raise_pct'],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * AI equity analysis across all bands.
     */
    public function equityAnalysis(): JsonResponse
    {
        $this->authorize('viewAny', SalaryBand::class);

        /** @var AIService $ai */
        $ai = app('ai');
        $bands = SalaryBand::orderBy('level')->get(['title', 'level', 'min_salary', 'mid_salary', 'max_salary', 'currency']);

        $summary = $bands->map(fn ($b) => "{$b->level} {$b->title}: min={$b->min_salary}, mid={$b->mid_salary}, max={$b->max_salary} {$b->currency}")->implode('; ');

        // Chantier 8.3: two bugs fixed together — (1) AIService::ask() requires
        // array $context, this call passed a plain string (a fatal TypeError
        // waiting to happen the moment this method, never routed until now, was
        // ever hit); (2) no try/catch, unlike every other app('ai')->ask() call
        // site (see AiChatController), breaking the app-wide "AI features must
        // degrade gracefully, never error" principle documented in CLAUDE.md.
        try {
            $suggestion = $ai->ask(
                'Analyse these salary bands for equity issues, compression, or outliers. Provide concise recommendations.',
                ['salary_bands' => $summary],
                'HR',
                'en'
            );
        } catch (\Throwable) {
            $suggestion = 'AI equity analysis is currently unavailable.';
        }

        return response()->json(['analysis' => $suggestion, 'bands' => $bands]);
    }
}
