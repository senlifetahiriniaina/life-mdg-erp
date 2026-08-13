<?php

declare(strict_types=1);



it('returns articles for a question without auth', function () {
    $this->postJson('/api/v1/helpdesk/bot/ask', ['question' => 'How do I reset my password?'])
        ->assertOk()
        ->assertJsonStructure(['question', 'articles', 'confidence']);
});

it('returns low confidence when no articles match', function () {
    $response = $this->postJson('/api/v1/helpdesk/bot/ask', ['question' => 'xyz123 nothingmatch'])
        ->assertOk();

    expect($response->json('confidence'))->toBe(0.0);
});

it('records a deflection result', function () {
    $this->postJson('/api/v1/helpdesk/bot/deflect', [
        'question' => 'How to reset password?',
        'deflected' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('deflected', true);
});
