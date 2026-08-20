<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

class MessagingAiAssistController extends Controller
{
    public function __construct(private readonly AiContextualAssistantService $assistant)
    {
    }

    public function assist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale' => ['sometimes', 'string', 'max:8'],
        ]);

        $guidance = $this->assistant->getGuidance(
            module: 'Messaging',
            action: $validated['action'],
            context: $validated['context'] ?? [],
            locale: $validated['locale'] ?? 'fr',
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
