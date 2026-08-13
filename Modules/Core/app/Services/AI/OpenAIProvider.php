<?php

declare(strict_types=1);

namespace Modules\Core\Services\AI;

use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\AIProviderContract;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderContract
{
    private string $model;
    private string $embeddingModel;

    public function __construct()
    {
        $this->model = config('ai.providers.openai.model', 'gpt-4o');
        $this->embeddingModel = config('ai.providers.openai.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $options = []): string
    {
        $systemPrompt = $options['system'] ?? config('ai.system_prompts.default');

        $formattedMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        try {
            $response = OpenAI::chat()->create([
                'model' => $options['model'] ?? $this->model,
                'messages' => $formattedMessages,
                'max_tokens' => $options['max_tokens'] ?? 4096,
            ]);

            return $response->choices[0]->message->content ?? '';
        } catch (\Exception $e) {
            Log::error('OpenAI API error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('AI provider error: ' . $e->getMessage());
        }
    }

    public function embed(string $text): array
    {
        try {
            $response = OpenAI::embeddings()->create([
                'model' => $this->embeddingModel,
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding ?? [];
        } catch (\Exception $e) {
            Log::error('OpenAI embeddings error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Embeddings error: ' . $e->getMessage());
        }
    }

    public function analyze(string $context, string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => "Context:\n{$context}\n\nTask:\n{$prompt}"],
        ]);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
