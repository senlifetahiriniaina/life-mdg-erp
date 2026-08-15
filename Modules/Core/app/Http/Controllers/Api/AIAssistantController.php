<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Core\Services\AI\AIService;

/**
 * @group Core - AIAssistant
 *
 * Interact with the WideHalo AI assistant.
 */
class AIAssistantController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
        $this->middleware('auth:sanctum');
    }

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:2000',
            'context' => 'nullable|array',
            'module' => 'nullable|string',
        ]);

        $locale = $request->user()->locale ?? app()->getLocale();

        $answer = $this->aiService->ask(
            $request->question,
            $request->context ?? [],
            $request->module,
            $locale
        );

        return response()->json(['answer' => $answer]);
    }

    public function analyzeData(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'type' => 'nullable|string|in:default,analyst,accountant,hr',
        ]);

        $locale = $request->user()->locale ?? app()->getLocale();
        $insight = $this->aiService->analyzeData($request->data, $request->type ?? 'analyst', $locale);

        return response()->json(['insight' => $insight]);
    }

    public function generateDocument(Request $request): JsonResponse
    {
        $request->validate([
            'template' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $locale = $request->user()->locale ?? app()->getLocale();
        $content = $this->aiService->generateDocument($request->template, $request->variables ?? [], $locale);

        return response()->json(['content' => $content]);
    }
}
