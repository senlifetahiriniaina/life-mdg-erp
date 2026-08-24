<?php

declare(strict_types=1);
use Modules\CRM\Services\AI\CrmAIService;


test('can detect duplicate contacts', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('detectDuplicates')
        ->once()
        ->andReturn(['analysis' => 'No duplicates found.', 'contact' => ['name' => 'Jean Dupont', 'email' => 'jean@example.com']]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/detect-duplicates', [
        'contact_data' => ['name' => 'Jean Dupont', 'email' => 'jean@example.com'],
    ])->assertOk()->assertJsonStructure(['analysis', 'contact']);
});

test('can draft prospecting email', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('draftProspectingEmail')
        ->once()
        ->andReturn(['email_draft' => 'Dear prospect...', 'contact_id' => 1]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/draft-prospecting-email', [
        'contact_id' => 1,
        'context' => 'SaaS company, 50 employees',
    ])->assertOk()->assertJsonStructure(['email_draft', 'contact_id']);
});

test('can analyze conversation sentiment', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('analyzeConversationSentiment')
        ->once()
        ->andReturn(['sentiment' => 'positive', 'contact_id' => 1]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/analyze-sentiment', [
        'contact_id' => 1,
        'conversation' => 'The client seemed very interested in the premium plan.',
    ])->assertOk()->assertJsonStructure(['sentiment', 'contact_id']);
});

test('can transcribe call to activity', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('transcribeCallToActivity')
        ->once()
        ->andReturn(['activity' => 'Call summary...', 'contact_id' => 1]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/transcribe-call', [
        'contact_id' => 1,
        'transcription' => 'Sales rep: Hello. Client: Yes I am interested in the enterprise plan.',
    ])->assertOk()->assertJsonStructure(['activity', 'contact_id']);
});

/**
 * Chantier 38.3: detect-duplicates/generate-prospecting-email/draft-prospecting-email/
 * analyze-sentiment used to route to the now-deleted CrmProspectingController — a duplicate
 * of CrmAIController with ZERO request validation. Confirmed empirically (real tinker call)
 * that posting without the exact expected field name threw a fatal TypeError (500) instead
 * of a clean 422, since CrmAIService's methods are type-hinted array/int, not nullable.
 * Repointed onto CrmAIController's already-validated methods — these lock in that a missing
 * required field now degrades to a real validation error, never a fatal crash.
 */
test('detect-duplicates returns a clean validation error, not a fatal crash, when contact_data is missing', function () {
    actingAsUser('sales-rep');

    $this->postJson('/api/v1/crm/ai/detect-duplicates', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contact_data']);
});

test('generate-prospecting-email returns a clean validation error when contact_id is missing', function () {
    actingAsUser('sales-rep');

    $this->postJson('/api/v1/crm/ai/generate-prospecting-email', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contact_id']);
});

test('draft-prospecting-email (alias route) also reaches the real, validated CrmAIController method', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('draftProspectingEmail')->once()->andReturn(['email_draft' => 'x', 'contact_id' => 3]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/draft-prospecting-email', ['contact_id' => 3])
        ->assertOk()->assertJsonStructure(['email_draft', 'contact_id']);
});

test('analyze-sentiment accepts the legacy `conversation` field name as well as `text`', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('analyzeConversationSentiment')
        ->once()
        ->with(1, 'positive vibes')
        ->andReturn(['sentiment' => 'positive', 'contact_id' => 1]);
    app()->instance(CrmAIService::class, $mock);

    $this->postJson('/api/v1/crm/ai/analyze-sentiment', [
        'contact_id' => 1,
        'text' => 'positive vibes',
    ])->assertOk()->assertJsonStructure(['sentiment', 'contact_id']);
});

test('analyze-sentiment 422s cleanly when neither text nor conversation is provided', function () {
    actingAsUser('sales-rep');

    $this->postJson('/api/v1/crm/ai/analyze-sentiment', ['contact_id' => 1])
        ->assertStatus(422);
});

test('the dead, unvalidated CrmProspectingController class is gone', function () {
    expect(class_exists(\Modules\CRM\Http\Controllers\Api\CrmProspectingController::class))->toBeFalse();
});

/**
 * Chantier 38.3: `POST crm/ai/draft-follow-up` called `$this->aiService->draftFollowUpEmail(...)`
 * — a method that has never existed anywhere on CrmAIService (the real method is
 * `draftFollowUp(array $contactData, string $context)`) — a guaranteed fatal
 * "Call to undefined method" on every real call, confirmed empirically via tinker before this
 * fix. Zero test coverage existed for this endpoint at all.
 */
test('draft-follow-up calls the real CrmAIService::draftFollowUp() method, not a fatal typo', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('draftFollowUp')
        ->once()
        ->with(['contact_id' => 7], 'renewal due soon')
        ->andReturn('Dear customer, following up on your renewal...');
    app()->instance(CrmAIService::class, $mock);

    $response = $this->postJson('/api/v1/crm/ai/draft-follow-up', [
        'contact_id' => 7,
        'context' => 'renewal due soon',
    ])->assertOk();

    expect($response->json())->toBe('Dear customer, following up on your renewal...');
});

/**
 * Chantier 38.3: `POST crm/ai/suggest-next-action` passed the bare `opportunity_id` int
 * straight through, where `CrmAIService::suggestNextAction(array $opportunityData)` requires
 * an array — a guaranteed fatal TypeError on every real call, confirmed empirically via
 * tinker before this fix. Zero test coverage existed for this endpoint at all.
 */
test('suggest-next-action calls the real service with an array, not a fatal TypeError', function () {
    actingAsUser('sales-rep');

    $mock = Mockery::mock(CrmAIService::class);
    $mock->shouldReceive('suggestNextAction')
        ->once()
        ->with(['opportunity_id' => 12])
        ->andReturn('Send a follow-up proposal within 48 hours.');
    app()->instance(CrmAIService::class, $mock);

    $response = $this->postJson('/api/v1/crm/ai/suggest-next-action', ['opportunity_id' => 12])
        ->assertOk();

    expect($response->json())->toBe('Send a follow-up proposal within 48 hours.');
});
