<?php

declare(strict_types=1);

use Modules\Core\Contracts\AIProviderContract;
use Modules\Core\Services\AI\AIService;
use Spatie\Permission\Models\Role;

/**
 * Chantier 19 Lot 3: real bug found via empirical execution —
 * AiAssistantController::assist() (this module's own primary
 * POST /api/v1/ai/assist endpoint, the one CLAUDE.md's own "AI Assisted
 * First" section documents as the standard contextual-guidance API) read
 * `$request->user()?->role` — the well-documented phantom `users.role`
 * column, never populated by the real registration flow — instead of the
 * real Spatie role, so `AiContextualAssistantService::getGuidance()`'s
 * `userRole` (which the service's own docblock says "influences depth/tone
 * of guidance") was silently always the literal string 'user' for every
 * real caller, regardless of their actual role. The static-fallback path
 * (used whenever no AI provider is configured, e.g. this test suite) never
 * reads userRole at all, which is exactly why the code-reading-only audits
 * that already fixed this identical bug pattern on 3 sibling controllers
 * this session never caught it here — only a real call with a *configured*
 * AI provider actually exercises the broken value. Faking the provider
 * contract here (rather than the raw HTTP client) reaches the real
 * userRole-carrying code path and confirms the fix.
 */
test('assist endpoint sends the real Spatie role to the AI provider, not the phantom role column', function () {
    Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
    $user = actingAsUser('accountant');

    $capturedMessages = null;

    $fakeProvider = Mockery::mock(AIProviderContract::class);
    $fakeProvider->shouldReceive('isConfigured')->andReturn(true);
    $fakeProvider->shouldReceive('chat')
        ->once()
        ->withArgs(function (array $messages) use (&$capturedMessages) {
            $capturedMessages = $messages;
            return true;
        })
        ->andReturn(json_encode([
            'what_to_do' => 'Test',
            'how_to_do' => [],
            'decision_indicators' => [],
            'warnings' => [],
            'next_actions' => [],
            'tips' => [],
        ]));

    $fakeAiService = Mockery::mock(AIService::class);
    $fakeAiService->shouldReceive('forModule')->with('AI')->andReturn($fakeProvider);
    app()->instance(AIService::class, $fakeAiService);
    app()->forgetInstance(\Modules\AI\Services\AiContextualAssistantService::class);

    $response = $this->postJson('/api/v1/ai/assist', [
        'module' => 'Accounting',
        'action' => 'post_invoice',
    ]);

    $response->assertOk();
    expect($capturedMessages)->not->toBeNull();
    $sentPayload = json_decode($capturedMessages[0]['content'], true);
    expect($sentPayload['user_role'])->toBe('accountant');
});

/**
 * The identical bug, found in the same pass, on the sibling per-module
 * AI-assist controllers this same AiContextualAssistantService backs:
 * Modules\Security\Http\Controllers\Api\SecurityAiAssistController and
 * Modules\AuditLog\Http\Controllers\Api\AuditLogAiAssistController.
 */
test('security ai/assist endpoint sends the real Spatie role, not the phantom role column', function () {
    Role::firstOrCreate(['name' => 'security-admin', 'guard_name' => 'web']);
    $user = actingAsUser('security-admin');

    $capturedMessages = null;
    $fakeProvider = Mockery::mock(AIProviderContract::class);
    $fakeProvider->shouldReceive('isConfigured')->andReturn(true);
    $fakeProvider->shouldReceive('chat')
        ->once()
        ->withArgs(function (array $messages) use (&$capturedMessages) {
            $capturedMessages = $messages;
            return true;
        })
        ->andReturn(json_encode(['what_to_do' => 'Test', 'how_to_do' => [], 'decision_indicators' => [], 'warnings' => [], 'next_actions' => [], 'tips' => []]));

    $fakeAiService = Mockery::mock(AIService::class);
    $fakeAiService->shouldReceive('forModule')->with('AI')->andReturn($fakeProvider);
    app()->instance(AIService::class, $fakeAiService);
    app()->forgetInstance(\Modules\AI\Services\AiContextualAssistantService::class);

    $response = $this->postJson('/api/v1/security/ai/assist', ['action' => 'view_dashboard']);

    $response->assertOk();
    $sentPayload = json_decode($capturedMessages[0]['content'], true);
    expect($sentPayload['user_role'])->toBe('security-admin');
});

test('audit-log ai/assist endpoint sends the real Spatie role, not the phantom role column', function () {
    $user = actingAsUser('admin');

    $capturedMessages = null;
    $fakeProvider = Mockery::mock(AIProviderContract::class);
    $fakeProvider->shouldReceive('isConfigured')->andReturn(true);
    $fakeProvider->shouldReceive('chat')
        ->once()
        ->withArgs(function (array $messages) use (&$capturedMessages) {
            $capturedMessages = $messages;
            return true;
        })
        ->andReturn(json_encode(['what_to_do' => 'Test', 'how_to_do' => [], 'decision_indicators' => [], 'warnings' => [], 'next_actions' => [], 'tips' => []]));

    $fakeAiService = Mockery::mock(AIService::class);
    $fakeAiService->shouldReceive('forModule')->with('AI')->andReturn($fakeProvider);
    app()->instance(AIService::class, $fakeAiService);
    app()->forgetInstance(\Modules\AI\Services\AiContextualAssistantService::class);

    $response = $this->postJson('/api/v1/audit-logs/ai/assist', ['action' => 'view_logs']);

    $response->assertOk();
    $sentPayload = json_decode($capturedMessages[0]['content'], true);
    expect($sentPayload['user_role'])->toBe('admin');
});
