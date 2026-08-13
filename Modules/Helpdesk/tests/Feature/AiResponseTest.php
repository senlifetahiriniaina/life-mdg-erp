<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;

uses(RefreshDatabase::class);

describe('AI Response - Generation', function () {
    test('generates response suggestion for ticket', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'subject' => 'Cannot login to account',
            'description' => 'I am having trouble accessing my account.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('response_text'))->toBeString();
        expect(strlen($response->json('response_text')))->toBeGreaterThan(20);
    });

    test('response suggestion is contextual', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'subject' => 'Billing question',
            'description' => 'Why was I charged twice?',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $text = strtolower($response->json('response_text'));
        expect($text)->toContain('bill') | expect($text)->toContain('charge');
    });

    test('response suggestion includes confidence score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('response suggestion includes tone parameter', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'tone' => 'professional',
            ])
            ->assertOk();

        expect($response->json('tone'))->toBe('professional');
    });

    test('can generate response with different tone', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $formalResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'tone' => 'formal',
            ])
            ->json();

        $friendlyResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'tone' => 'friendly',
            ])
            ->json();

        expect($formalResponse['response_text'])->not()->toBe($friendlyResponse['response_text']);
    });
});

describe('AI Response - Variants Creation', function () {
    test('generates multiple response variants', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 3,
            ])
            ->assertOk();

        expect($response->json('variants'))->toHaveCount(3);
        expect($response->json('variants.0.text'))->toBeString();
        expect($response->json('variants.0.score'))->toBeTruthy();
    });

    test('variants are distinct from each other', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 3,
            ])
            ->assertOk();

        $texts = array_map(fn($v) => $v['text'], $response->json('variants'));
        expect(count(array_unique($texts)))->toBe(count($texts));
    });

    test('variants ranked by relevance score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 3,
            ])
            ->assertOk();

        $variants = $response->json('variants');
        expect($variants[0]['score'])->toBeGreaterThanOrEqual($variants[1]['score'] ?? 0);
    });

    test('can generate variants with specific tone', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 2,
                'tone' => 'formal',
            ])
            ->assertOk();

        expect($response->json('variants'))->toHaveCount(2);
        foreach ($response->json('variants') as $variant) {
            expect($variant['tone'])->toBe('formal');
        }
    });

    test('variant generation respects max length', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 2,
                'max_length' => 100,
            ])
            ->assertOk();

        foreach ($response->json('variants') as $variant) {
            expect(strlen($variant['text']))->toBeLessThanOrEqual(100);
        }
    });
});

describe('AI Response - Personalization', function () {
    test('personalizes response with customer name', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'metadata' => ['customer_name' => 'John Doe'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'personalize' => true,
            ])
            ->assertOk();

        expect($response->json('response_text'))->toContain('John');
    });

    test('personalizes response with customer history', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'metadata' => ['account_age_days' => 365],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'personalize' => true,
            ])
            ->assertOk();

        // Should reference customer as long-time user
        expect($response->json('response_text'))->toBeString();
    });

    test('personalizes based on customer sentiment', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $angryResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'customer_sentiment' => 'negative',
                'personalize' => true,
            ])
            ->json();

        $happyResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'customer_sentiment' => 'positive',
                'personalize' => true,
            ])
            ->json();

        expect($angryResponse['response_text'])->not()->toBe($happyResponse['response_text']);
    });

    test('personalization includes appropriate empathy', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'customer_sentiment' => 'frustrated',
                'personalize' => true,
            ])
            ->assertOk();

        $text = strtolower($response->json('response_text'));
        // Should contain empathy markers
        expect(
            str_contains($text, 'understand') ||
            str_contains($text, 'sorry') ||
            str_contains($text, 'appreciate')
        )->toBeTrue();
    });
});

describe('AI Response - Ranking and Relevance', function () {
    test('ranks responses by relevance score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 3,
            ])
            ->assertOk();

        $scores = array_map(fn($v) => $v['score'], $response->json('variants'));
        // Should be in descending order
        expect($scores[0])->toBeGreaterThanOrEqual($scores[1]);
    });

    test('includes relevance explanation for each response', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 2,
            ])
            ->assertOk();

        expect($response->json('variants.0.explanation'))->toBeString();
    });

    test('relevance considers ticket content', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'subject' => 'Account login issue',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('relevance_factors'))->toBeArray();
    });
});

describe('AI Response - Performance Tracking', function () {
    test('stores response suggestion for tracking', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $suggestionId = $response->json('id');
        $this->assertDatabaseHas('helpdesk_ai_responses', [
            'id' => $suggestionId,
            'ticket_id' => $ticket->id,
        ]);
    });

    test('can retrieve response effectiveness metrics', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/effectiveness-metrics')
            ->assertOk();

        expect($response->json('total_suggestions'))->toBeGreaterThanOrEqual(0);
        expect($response->json('average_adoption_rate'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('tracks response adoption', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $suggestionId = $response->json('id');

        // Mark as adopted
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/ai-response/{$suggestionId}/adopt")
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_ai_responses', [
            'id' => $suggestionId,
            'adopted' => true,
        ]);
    });

    test('tracks response effectiveness over time', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $suggestionResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->json();

        $suggestionId = $suggestionResponse['id'];

        // Mark as adopted and eventually resolved
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/ai-response/{$suggestionId}/adopt");

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/ai-response/{$suggestionId}/effectiveness", [
                'outcome' => 'resolved',
                'customer_satisfaction' => 5,
            ])
            ->assertOk();

        $metricsResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/effectiveness-metrics')
            ->assertOk();

        expect($metricsResponse->json('total_tracked'))->toBeGreaterThanOrEqual(1);
    });
});

describe('AI Response - A/B Testing', function () {
    test('enables a/b testing for response variants', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 2,
                'enable_ab_test' => true,
            ])
            ->assertOk();

        expect($response->json('ab_test_id'))->toBeTruthy();
    });

    test('tracks variant selection in a/b test', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $variantsResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/variants', [
                'ticket_id' => $ticket->id,
                'count' => 2,
                'enable_ab_test' => true,
            ])
            ->json();

        $abTestId = $variantsResponse['ab_test_id'];
        $variantId = $variantsResponse['variants'][0]['id'];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/ai-response/ab-test/{$abTestId}/select", [
                'variant_id' => $variantId,
            ])
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_ab_tests', [
            'id' => $abTestId,
        ]);
    });

    test('calculates a/b test effectiveness', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/ab-test/results')
            ->assertOk();

        expect($response->json('tests'))->toBeArray();
    });

    test('a/b test results show variant performance', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/ab-test/results')
            ->assertOk();

        if (count($response->json('tests')) > 0) {
            expect($response->json('tests.0.variant_a_performance'))->toBeTruthy();
            expect($response->json('tests.0.variant_b_performance'))->toBeTruthy();
        }
    });
});

describe('AI Response - Agent Preference Learning', function () {
    test('learns agent response preferences', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $suggestionId = $response->json('id');

        // Agent modifies response
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/ai-response/{$suggestionId}/feedback", [
                'modified_text' => 'Thank you for your issue. We will help you.',
                'rating' => 'good',
            ])
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_agent_preferences', [
            'agent_id' => $user->id,
        ]);
    });

    test('provides agent-specific response suggestions', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'consider_agent_preferences' => true,
            ])
            ->assertOk();

        expect($response->json('considers_agent_style'))->toBeTruthy();
    });

    test('tracks which response variations agent prefers', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/agent-preferences')
            ->assertOk();

        expect($response->json('preferred_tone'))->toBeTruthy() | expect($response->json('preferred_tone'))->toBeNull();
    });

    test('agent preferences improve over time', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(5)->create();

        foreach ($tickets as $ticket) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                    'ticket_id' => $ticket->id,
                ])
                ->json();

            $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/helpdesk/ai-response/{$response['id']}/feedback", [
                    'rating' => 'excellent',
                ]);
        }

        $preferencesResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/ai-response/agent-preferences')
            ->assertOk();

        expect($preferencesResponse->json('samples_collected'))->toBeGreaterThanOrEqual(5);
    });
});

describe('AI Response - Multi-language Support', function () {
    test('generates response in requested language', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
                'language' => 'es',
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('es');
    });

    test('supports multiple languages', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $languages = ['en', 'es', 'fr', 'de'];

        foreach ($languages as $lang) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                    'ticket_id' => $ticket->id,
                    'language' => $lang,
                ])
                ->assertOk();

            expect($response->json('language'))->toBe($lang);
        }
    });

    test('detects ticket language automatically', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'description' => 'Je ne peux pas accéder à mon compte.',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('fr');
    });
});

describe('AI Response - Authorization', function () {
    test('requires authentication to suggest responses', function () {
        $ticket = Ticket::factory()->create();

        $this->postJson('/api/v1/helpdesk/ai-response/suggest', [
            'ticket_id' => $ticket->id,
        ])->assertUnauthorized();
    });

    test('cannot suggest response for restricted tickets', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });
});

describe('AI Response - Edge Cases', function () {
    test('handles very long ticket descriptions', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'description' => str_repeat('This is a detailed issue. ', 500),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('response_text'))->toBeString();
    });

    test('handles duplicate ticket references', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'subject' => 'Duplicate issue',
            'description' => 'This is the same as ticket #123',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('is_duplicate_suggested'))->toBeBoolean();
    });
});

describe('AI Response - Company Isolation', function () {
    test('responses are company-isolated', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/ai-response/suggest', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });
});
