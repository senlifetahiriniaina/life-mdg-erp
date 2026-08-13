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
