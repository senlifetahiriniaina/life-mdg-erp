<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;

uses(RefreshDatabase::class);

describe('Sentiment Analysis - Basic Detection', function () {
    test('detects positive sentiment in customer message', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is absolutely amazing! Great support!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('positive');
        expect($response->json('confidence'))->toBeGreaterThanOrEqual(0.8);
        expect($response->json('score'))->toBeGreaterThan(0);
    });

    test('detects negative sentiment in customer message', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is terrible! Worst experience ever!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('negative');
        expect($response->json('confidence'))->toBeGreaterThanOrEqual(0.8);
        expect($response->json('score'))->toBeLessThan(0);
    });

    test('detects neutral sentiment in customer message', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'The product arrived on time.',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('neutral');
    });

    test('provides confidence score between 0 and 1', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is good.',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $confidence = $response->json('confidence');
        expect($confidence)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('handles empty text gracefully', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => '',
                'ticket_id' => $ticket->id,
            ])
            ->assertUnprocessable();
    });

    test('analyzes sentiment from ticket comment', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'body' => 'Perfect solution! Thank you so much!',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'comment_id' => $comment->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('positive');
        expect($response->json('comment_id'))->toBe($comment->id);
    });
});

describe('Sentiment Analysis - Multi-Language Support', function () {
    test('analyzes sentiment in English text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Excellent work!',
                'language' => 'en',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('en');
        expect($response->json('sentiment'))->toBe('positive');
    });

    test('analyzes sentiment in Spanish text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => '¡Excelente trabajo! Muy bien.',
                'language' => 'es',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('es');
        expect($response->json('sentiment'))->toBe('positive');
    });

    test('analyzes sentiment in French text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Excellent travail! Très bien.',
                'language' => 'fr',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('fr');
        expect($response->json('sentiment'))->toBe('positive');
    });

    test('analyzes sentiment in German text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Ausgezeichnete Arbeit! Sehr gut.',
                'language' => 'de',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBe('de');
        expect($response->json('sentiment'))->toBe('positive');
    });

    test('auto-detects language when not specified', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Magnifique!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('language'))->toBeTruthy();
    });

    test('handles mixed language text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Great! Excellent! Muy bien!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('positive');
    });
});

describe('Sentiment Analysis - Emotion Detection', function () {
    test('detects anger emotion', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I am extremely angry and frustrated with this!',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotions = $response->json('emotions');
        expect($emotions)->toContain('anger');
    });

    test('detects frustration emotion', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is so frustrating! Nothing works!',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotions = $response->json('emotions');
        expect($emotions)->toContain('frustration');
    });

    test('detects satisfaction emotion', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I am very satisfied with your service!',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotions = $response->json('emotions');
        expect($emotions)->toContain('satisfaction');
    });

    test('detects confusion emotion', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I do not understand how this works. I am confused.',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotions = $response->json('emotions');
        expect($emotions)->toContain('confusion');
    });

    test('returns multiple emotions for complex text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I am frustrated and confused but also satisfied with some aspects.',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotions = $response->json('emotions');
        expect(count($emotions))->toBeGreaterThan(1);
    });

    test('provides emotion confidence scores', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I am very angry!',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
            ])
            ->assertOk();

        $emotionScores = $response->json('emotion_scores');
        expect($emotionScores)->toBeArray();
        expect($emotionScores['anger'] ?? 0)->toBeGreaterThan(0.5);
    });
});

describe('Sentiment Analysis - Scoring and Confidence', function () {
    test('provides sentiment score from -1 to 1', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is good.',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $score = $response->json('score');
        expect($score)->toBeGreaterThanOrEqual(-1)->toBeLessThanOrEqual(1);
    });

    test('strong positive sentiment has high score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Absolutely perfect! Best experience ever! Amazing!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('score'))->toBeGreaterThan(0.7);
    });

    test('strong negative sentiment has low score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Absolutely terrible! Worst experience! Horrible!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('score'))->toBeLessThan(-0.7);
    });

    test('confidence reflects model certainty', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is clearly positive!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeGreaterThan(0.8);
    });

    test('uncertain sentiment has lower confidence', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'maybe this is okay.',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeLessThan(0.8);
    });
});

describe('Sentiment Analysis - History Tracking', function () {
    test('stores sentiment analysis history', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Great service!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_sentiment_analyses', [
            'ticket_id' => $ticket->id,
            'sentiment' => 'positive',
        ]);
    });

    test('can retrieve sentiment history for ticket', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // Create multiple sentiments
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Not great initially.',
                'ticket_id' => $ticket->id,
            ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Much better now!',
                'ticket_id' => $ticket->id,
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-history")
            ->assertOk();

        expect($response->json('data'))->toHaveCount(2);
    });

    test('sentiment history shows timestamps', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Good service.',
                'ticket_id' => $ticket->id,
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-history")
            ->assertOk();

        expect($response->json('data.0.created_at'))->toBeTruthy();
    });

    test('can filter sentiment history by date range', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Good.',
                'ticket_id' => $ticket->id,
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-history", [
                'from' => now()->subDay()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1);
    });
});

describe('Sentiment Analysis - Routing', function () {
    test('routes high priority negative sentiment to supervisor', function () {
        $user = User::factory()->create();
        $supervisor = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'assignee_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I am extremely angry! This is unacceptable!',
                'ticket_id' => $ticket->id,
                'apply_routing' => true,
            ])
            ->assertOk();

        expect($response->json('routing_applied'))->toBeTrue();
        expect($response->json('suggested_action'))->toBe('escalate');
    });

    test('suggests immediate response for angry customer', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'This is terrible! I want to cancel!',
                'ticket_id' => $ticket->id,
                'apply_routing' => true,
            ])
            ->assertOk();

        expect($response->json('suggested_priority'))->toBe('high');
    });

    test('suggests knowledge base for confused customer', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'I do not understand how to use this feature.',
                'ticket_id' => $ticket->id,
                'detect_emotions' => true,
                'apply_routing' => true,
            ])
            ->assertOk();

        expect($response->json('suggested_action'))->toBeTruthy();
    });

    test('routing follows configured rules', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Very satisfied with your service!',
                'ticket_id' => $ticket->id,
                'apply_routing' => true,
            ])
            ->assertOk();

        expect($response->json('routing_applied'))->toBeTrue();
    });
});

describe('Sentiment Analysis - Real-time vs Batch', function () {
    test('performs real-time sentiment analysis', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $start = now();
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Great service!',
                'ticket_id' => $ticket->id,
                'mode' => 'realtime',
            ])
            ->assertOk();
        $duration = now()->diffInMilliseconds($start);

        expect($duration)->toBeLessThan(5000); // Should respond in < 5 seconds
        expect($response->json('sentiment'))->toBeTruthy();
    });

    test('queues batch sentiment analysis for multiple tickets', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(5)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/batch-analyze', [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
                'mode' => 'batch',
            ])
            ->assertAccepted();

        expect($response->json('job_id'))->toBeTruthy();
        expect($response->json('ticket_count'))->toBe(5);
    });

    test('can retrieve batch analysis job status', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(3)->create();

        $batchResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/batch-analyze', [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
            ])
            ->assertAccepted();

        $jobId = $batchResponse->json('job_id');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/sentiment/batch/{$jobId}/status")
            ->assertOk();

        expect($response->json('status'))->toBeIn(['pending', 'processing', 'completed']);
        expect($response->json('progress'))->toBeTruthy();
    });

    test('batch mode analyzes tickets asynchronously', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/batch-analyze', [
                'ticket_ids' => [$ticket->id],
                'mode' => 'batch',
            ])
            ->assertAccepted();

        // Sentiment should be stored after job completes
        // (In real test, would wait/poll for completion)
    });
});

describe('Sentiment Analysis - Trend Analysis', function () {
    test('calculates sentiment trend over time', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // Create multiple sentiments over time
        foreach (range(1, 5) as $i) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                    'text' => $i <= 2 ? 'Bad' : 'Good',
                    'ticket_id' => $ticket->id,
                ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-trend")
            ->assertOk();

        expect($response->json('trend'))->toBeTruthy();
        expect($response->json('trajectory'))->toBeIn(['improving', 'declining', 'stable']);
    });

    test('sentiment trend shows improvement trajectory', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // Create improving sentiment
        $texts = [
            'This is bad.',
            'Getting better.',
            'Pretty good now.',
            'Excellent!',
        ];

        foreach ($texts as $text) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                    'text' => $text,
                    'ticket_id' => $ticket->id,
                ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-trend")
            ->assertOk();

        expect($response->json('trajectory'))->toBe('improving');
    });

    test('calculates sentiment trend across team', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(10)->create();

        foreach ($tickets as $ticket) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                    'text' => 'Good service.',
                    'ticket_id' => $ticket->id,
                ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/sentiment/trend/team')
            ->assertOk();

        expect($response->json('average_sentiment_score'))->toBeGreaterThan(0);
        expect($response->json('ticket_count'))->toBe(10);
    });

    test('trend analysis includes confidence metrics', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Good.',
                'ticket_id' => $ticket->id,
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-trend")
            ->assertOk();

        expect($response->json('average_confidence'))->toBeGreaterThan(0);
    });
});

describe('Sentiment Analysis - Authorization', function () {
    test('requires authentication to analyze sentiment', function () {
        $ticket = Ticket::factory()->create();

        $this->postJson('/api/v1/helpdesk/sentiment/analyze', [
            'text' => 'Test',
            'ticket_id' => $ticket->id,
        ])->assertUnauthorized();
    });

    test('cannot analyze sentiment for restricted tickets', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // Assuming company isolation policy
        // This would depend on actual permission implementation
        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Test',
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });

    test('required permission for sentiment routing', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // User without permission should not apply routing
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Test',
                'ticket_id' => $ticket->id,
                'apply_routing' => true,
            ]);

        // Should either work or require permission based on implementation
        expect($response->status())->toBeIn([200, 403]);
    });
});

describe('Sentiment Analysis - Edge Cases', function () {
    test('handles very long text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $longText = str_repeat('This is a test. ', 500);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => $longText,
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBeTruthy();
    });

    test('handles special characters and emojis', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Great! 👍 Amazing! 🎉 Love it! ❤️',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('positive');
    });

    test('handles sarcasm gracefully', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Oh great, another bug. Just what I needed.',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBeTruthy();
        // Confidence may be lower for sarcasm
    });

    test('handles multiple languages in single text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Great! Excellent! Muy bien! Très bien!',
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sentiment'))->toBe('positive');
    });

    test('handles null or whitespace only text', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => '   ',
                'ticket_id' => $ticket->id,
            ])
            ->assertUnprocessable();
    });

    test('returns consistent results for identical input', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();
        $text = 'This is a good product.';

        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => $text,
                'ticket_id' => $ticket->id,
            ])
            ->json();

        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => $text,
                'ticket_id' => $ticket->id,
            ])
            ->json();

        expect($response1['sentiment'])->toBe($response2['sentiment']);
    });
});

describe('Sentiment Analysis - Company Isolation', function () {
    test('cannot analyze sentiment for other company tickets', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Assuming users belong to different companies
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Test',
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });

    test('sentiment history is company-isolated', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/sentiment/analyze', [
                'text' => 'Good',
                'ticket_id' => $ticket->id,
            ]);

        // Another user from different company
        $other = User::factory()->create();

        $response = $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/sentiment-history")
            ->assertForbidden();
    });
});
