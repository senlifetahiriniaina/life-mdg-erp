<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'module'  => 'nullable|string|max:50',
            'context' => 'nullable|array',
        ]);

        $module  = $request->input('module', 'general');
        $message = $request->input('message');
        /** @var array<string, mixed> $context */
        $context = $request->input('context', []);

        try {
            /** @var \Modules\Core\Services\AI\AIService $ai */
            $ai = app('ai');
            $response = $ai->ask($message, $context, $module, app()->getLocale());
        } catch (\Throwable) {
            // Fallback réponse si IA non configurée
            $response = $this->fallbackResponse($module, $message);
        }

        return response()->json([
            'reply'   => $response,
            'module'  => $module,
            'actions' => $this->suggestActions($module, $message),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function suggestActions(string $module, string $message): array
    {
        $lower = strtolower($message);
        $actions = [];

        if ($module === 'CRM' && str_contains($lower, 'contact')) {
            $actions[] = ['label' => 'Créer un contact', 'route' => '/crm/contacts/create'];
        }
        if ($module === 'HR' && str_contains($lower, 'congé')) {
            $actions[] = ['label' => 'Nouvelle demande', 'route' => '/hr/leaves'];
        }
        if ($module === 'Projects' && str_contains($lower, 'tâche')) {
            $actions[] = ['label' => 'Créer une tâche', 'route' => '/projects'];
        }
        if (str_contains($lower, 'rapport')) {
            $actions[] = ['label' => 'Ouvrir BI', 'route' => '/bi'];
        }

        return $actions;
    }

    private function fallbackResponse(string $module, string $message): string
    {
        $responses = [
            'bonjour' => 'Bonjour ! Comment puis-je vous aider avec le module ' . $module . ' ?',
            'aide'    => 'Je suis votre assistant ' . $module . '. Posez-moi une question sur vos données ou demandez-moi une action.',
            'default' => 'Je traite votre demande concernant "' . $message . '" dans le module ' . $module . '. Que souhaitez-vous faire ?',
        ];
        foreach ($responses as $key => $r) {
            if ($key !== 'default' && str_contains(strtolower($message), $key)) {
                return $r;
            }
        }
        return $responses['default'];
    }
}
