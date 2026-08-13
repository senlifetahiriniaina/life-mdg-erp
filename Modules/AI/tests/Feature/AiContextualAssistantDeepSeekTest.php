<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\Core\Services\AI\AIService;
use Modules\Core\Services\AI\AnthropicProvider;
use Modules\Core\Services\AI\DeepSeekProvider;
use Modules\Core\Services\AI\OpenAIProvider;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;

/**
 * End-to-end coverage for the DeepSeek path specifically: AI_DEFAULT_PROVIDER
 * (or a module_providers.AI override) pointed at 'deepseek' routes guidance
 * requests through DeepSeekProvider instead of Anthropic, with the same
 * fallback/never-throw contract as the default (Anthropic) path — see
 * AiAssistantTest.php for that contract's own coverage.
 */
function makeAiServiceWithFakeDeepSeek(ClientFake $fake): AIService
{
    return new AIService(
        new AnthropicProvider(),
        new OpenAIProvider(),
        new DeepSeekProvider($fake),
    );
}

test('guidance is served through DeepSeek when it is the configured provider', function () {
    Config::set('ai.providers.deepseek.base_url', 'http://deepseek:11434/v1');
    Config::set('ai.default_provider', 'deepseek');

    $fake = new ClientFake([
        CreateResponse::fake([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'what_to_do' => 'Créez un contact.',
                    'how_to_do' => ['Étape 1', 'Étape 2'],
                    'decision_indicators' => [],
                    'warnings' => [],
                    'next_actions' => [],
                    'tips' => [],
                ])],
            ]],
        ]),
    ]);

    $service = new AiContextualAssistantService(makeAiServiceWithFakeDeepSeek($fake));
    $guidance = $service->getGuidance('CRM', 'create_contact');

    expect($guidance['enabled'])->toBeTrue();
    expect($guidance['what_to_do'])->toBe('Créez un contact.');

    $fake->assertSent(\OpenAI\Resources\Chat::class);
});

test('guidance falls back gracefully when DeepSeek is configured but unreachable', function () {
    Config::set('ai.providers.deepseek.base_url', 'http://deepseek:11434/v1');
    Config::set('ai.default_provider', 'deepseek');

    $fake = new ClientFake([
        new \Exception('connection refused'),
    ]);

    $service = new AiContextualAssistantService(makeAiServiceWithFakeDeepSeek($fake));
    $guidance = $service->getGuidance('CRM', 'create_contact');

    // Never throws, always a full guidance shape — same contract as the
    // Anthropic path when the API call fails.
    expect($guidance)
        ->toHaveKey('enabled')
        ->toHaveKey('what_to_do')
        ->toHaveKey('how_to_do');
});

test('AI_DEFAULT_PROVIDER=anthropic (unchanged) does not touch DeepSeek at all', function () {
    Config::set('ai.default_provider', 'anthropic');
    Config::set('ai.providers.anthropic.api_key', '');

    // A DeepSeek fake with zero queued responses — if anything ever called
    // it, the "No fake responses left." exception would surface as a
    // fallback (not a thrown error, per the never-throw contract), so we
    // assert on assertNotSent for a precise, unambiguous check instead.
    $fake = new ClientFake([]);

    $service = new AiContextualAssistantService(makeAiServiceWithFakeDeepSeek($fake));
    $guidance = $service->getGuidance('CRM', 'create_contact');

    expect($guidance['enabled'])->toBeFalse();
    $fake->assertNotSent(\OpenAI\Resources\Chat::class);
});
