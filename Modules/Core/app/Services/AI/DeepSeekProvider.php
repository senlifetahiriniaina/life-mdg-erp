<?php

declare(strict_types=1);

namespace Modules\Core\Services\AI;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\AIProviderContract;
use OpenAI\Contracts\ClientContract;

/**
 * Self-hosted DeepSeek (served via Ollama, see docker-compose.deepseek.yml),
 * reached through its OpenAI-compatible /v1/chat/completions endpoint.
 *
 * Uses a dedicated OpenAI client instance pointed at DEEPSEEK_BASE_URL rather
 * than the OpenAI:: facade (which stays wired to the real OpenAI API for
 * OpenAIProvider). Ollama doesn't authenticate local requests, so the API
 * key is a placeholder — openai-php/client requires a non-empty string.
 */
class DeepSeekProvider implements AIProviderContract
{
    private ClientContract $client;
    private string $model;
    private bool $configured;

    /**
     * @param ClientContract|null $client Optional — inject an
     *   \OpenAI\Testing\ClientFake in tests instead of a real client; when
     *   omitted, a real client pointed at DEEPSEEK_BASE_URL is built.
     */
    public function __construct(?ClientContract $client = null)
    {
        $baseUrl = config('ai.providers.deepseek.base_url', '');
        $this->model = config('ai.providers.deepseek.model', 'deepseek-r1:7b');
        $this->configured = $baseUrl !== '';

        $this->client = $client ?? \OpenAI::factory()
            ->withApiKey('ollama')
            ->withBaseUri($baseUrl !== '' ? $baseUrl : 'http://localhost:11434/v1')
            ->make();
    }

    public function chat(array $messages, array $options = []): string
    {
        $systemPrompt = $options['system'] ?? config('ai.system_prompts.default');

        $formattedMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        try {
            $response = $this->client->chat()->create([
                'model' => $options['model'] ?? $this->model,
                'messages' => $formattedMessages,
                'max_tokens' => $options['max_tokens'] ?? config('ai.providers.deepseek.max_tokens', 4096),
            ]);

            return $response->choices[0]->message->content ?? '';
        } catch (\Exception $e) {
            Log::error('DeepSeek provider error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('AI provider error: ' . $e->getMessage());
        }
    }

    public function embed(string $text): array
    {
        // Chat-oriented DeepSeek models served via Ollama don't expose a
        // dedicated embeddings endpoint here; delegate to OpenAI like
        // AnthropicProvider does.
        throw new \RuntimeException('Embeddings not supported by DeepSeek provider. Use OpenAI provider.');
    }

    public function analyze(string $context, string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => "Context:\n{$context}\n\nTask:\n{$prompt}"],
        ]);
    }

    public function getProviderName(): string
    {
        return 'deepseek';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
