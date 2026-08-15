<?php

use Modules\Core\Services\AI\AIService;
use Modules\Core\Services\AI\AnthropicProvider;
use Modules\Core\Services\AI\DeepSeekProvider;
use Modules\Core\Services\AI\OpenAIProvider;

test('ai service resolves correct provider by name', function () {
    $service = new AIService(
        new AnthropicProvider,
        new OpenAIProvider,
        new DeepSeekProvider
    );

    expect($service->provider('anthropic'))->toBeInstanceOf(AnthropicProvider::class);
    expect($service->provider('openai'))->toBeInstanceOf(OpenAIProvider::class);
});

test('ai service throws for unknown provider', function () {
    $service = new AIService(new AnthropicProvider, new OpenAIProvider, new DeepSeekProvider);

    expect(fn () => $service->provider('unknown'))->toThrow(InvalidArgumentException::class);
});

test('ai service resolves embeddings provider', function () {
    config(['ai.embeddings_provider' => 'openai']);

    $service = new AIService(new AnthropicProvider, new OpenAIProvider, new DeepSeekProvider);

    expect($service->embeddings())->toBeInstanceOf(OpenAIProvider::class);
});
