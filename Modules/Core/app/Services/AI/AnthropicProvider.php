<?php

declare(strict_types=1);

namespace Modules\Core\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\AIProviderContract;

class AnthropicProvider implements AIProviderContract
{
    private string $apiKey = '';
    private string $model = 'claude-sonnet-4-6';
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.providers.anthropic.api_key') ?? '';
        $this->model = config('ai.providers.anthropic.model') ?? 'claude-sonnet-4-6';
    }

    public function chat(array $messages, array $options = []): string
    {
        $systemPrompt = $options['system'] ?? config('ai.system_prompts.default');

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(config('ai.providers.anthropic.timeout', 60))
            ->post("{$this->baseUrl}/messages", [
                'model' => $options['model'] ?? $this->model,
                'max_tokens' => $options['max_tokens'] ?? config('ai.providers.anthropic.max_tokens', 4096),
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            Log::error('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('AI provider error: ' . $response->body());
        }

        return $response->json('content.0.text', '');
    }

    public function embed(string $text): array
    {
        // Anthropic doesn't provide embeddings; delegate to OpenAI
        throw new \RuntimeException('Embeddings not supported by Anthropic provider. Use OpenAI provider.');
    }

    public function analyze(string $context, string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => "Context:\n{$context}\n\nTask:\n{$prompt}"],
        ]);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }
}
