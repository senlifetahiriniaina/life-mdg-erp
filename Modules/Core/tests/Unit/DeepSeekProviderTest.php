<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\Core\Services\AI\DeepSeekProvider;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;

// ---------------------------------------------------------------------------
// isConfigured()
// ---------------------------------------------------------------------------

test('isConfigured is true when DEEPSEEK_BASE_URL is set', function () {
    Config::set('ai.providers.deepseek.base_url', 'http://deepseek:11434/v1');

    $provider = new DeepSeekProvider();

    expect($provider->isConfigured())->toBeTrue();
});

test('isConfigured is false when DEEPSEEK_BASE_URL is empty', function () {
    Config::set('ai.providers.deepseek.base_url', '');

    $provider = new DeepSeekProvider();

    expect($provider->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// getProviderName()
// ---------------------------------------------------------------------------

test('getProviderName returns deepseek', function () {
    $provider = new DeepSeekProvider(new ClientFake());

    expect($provider->getProviderName())->toBe('deepseek');
});

// ---------------------------------------------------------------------------
// chat() — success
// ---------------------------------------------------------------------------

test('chat returns the message content from a fake DeepSeek response', function () {
    $fake = new ClientFake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'Bonjour, comment puis-je vous aider ?']],
            ],
        ]),
    ]);

    $provider = new DeepSeekProvider($fake);

    $result = $provider->chat([
        ['role' => 'user', 'content' => 'Bonjour'],
    ]);

    expect($result)->toBe('Bonjour, comment puis-je vous aider ?');

    $fake->assertSent(\OpenAI\Resources\Chat::class, function (string $method, array $parameters): bool {
        return $parameters['messages'][0]['role'] === 'system'
            && $parameters['messages'][1]['content'] === 'Bonjour';
    });
});

// ---------------------------------------------------------------------------
// chat() — failure never leaks a raw exception type other than RuntimeException
// ---------------------------------------------------------------------------

test('chat wraps client errors in a RuntimeException', function () {
    $fake = new ClientFake([
        new \Exception('connection refused'),
    ]);

    $provider = new DeepSeekProvider($fake);

    expect(fn () => $provider->chat([['role' => 'user', 'content' => 'Bonjour']]))
        ->toThrow(\RuntimeException::class);
});

// ---------------------------------------------------------------------------
// embed() — explicitly unsupported, like AnthropicProvider
// ---------------------------------------------------------------------------

test('embed throws because DeepSeek provider does not support embeddings', function () {
    $provider = new DeepSeekProvider(new ClientFake());

    expect(fn () => $provider->embed('some text'))
        ->toThrow(\RuntimeException::class);
});
