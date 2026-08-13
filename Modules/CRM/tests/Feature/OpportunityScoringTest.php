<?php

use Modules\CRM\Models\EngagementSignal;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;
use Modules\CRM\Models\ScoringRule;
use Modules\CRM\Services\OpportunityScoringService;

describe('Opportunity Scoring (Einstein-style)', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(OpportunityScoringService::class);
    });

    // ─── Scoring logic ────────────────────────────────────────────────────────

    test('can score an opportunity', function () {
        $opp = Opportunity::factory()->create(['stage' => 'proposal', 'amount' => 50000]);

        $score = $this->service->score($opp);

        expect($score)->toBeInstanceOf(OpportunityScore::class);
        expect($score->total_score)->toBeGreaterThanOrEqual(0);
        expect($score->total_score)->toBeLessThanOrEqual(100);
        expect($score->grade)->toBeIn(['A', 'B', 'C', 'D', 'F']);
        expect((float) $score->win_probability)->toBeGreaterThanOrEqual(0);
        expect((float) $score->win_probability)->toBeLessThanOrEqual(1);
    });

    test('score is idempotent — updates existing record', function () {
        $opp = Opportunity::factory()->create();

        $first = $this->service->score($opp);
        $second = $this->service->score($opp);

        expect(OpportunityScore::where('opportunity_id', $opp->id)->count())->toBe(1);
        expect($second->id)->toBe($first->id);
    });

    test('grade A for score >= 80', function () {
        $score = OpportunityScore::factory()->create([
            'opportunity_id' => Opportunity::factory()->create()->id,
            'total_score' => 85,
        ]);

        expect($score->grade)->toBe('A');
        expect($score->isHighValue())->toBeTrue();
    });

    test('grade F for score < 35', function () {
        $score = OpportunityScore::factory()->create([
            'opportunity_id' => Opportunity::factory()->create()->id,
            'total_score' => 20,
        ]);

        expect($score->grade)->toBe('F');
        expect($score->isHighValue())->toBeFalse();
    });

    test('getRecommendedAction returns correct action per score', function () {
        $mkScore = fn (int $s) => OpportunityScore::factory()->make(['total_score' => $s]);

        expect($mkScore(85)->getRecommendedAction())->toBe('close');
        expect($mkScore(60)->getRecommendedAction())->toBe('nurture');
        expect($mkScore(40)->getRecommendedAction())->toBe('qualify');
        expect($mkScore(20)->getRecommendedAction())->toBe('disqualify');
    });

    // ─── Scoring rules ────────────────────────────────────────────────────────

    test('scoring rule evaluate returns points when condition matches', function () {
        $rule = ScoringRule::factory()->create([
            'condition_field' => 'deal_size',
            'condition_operator' => 'gt',
            'condition_value' => '10000',
            'points' => 15,
        ]);

        expect($rule->evaluate(50000))->toBe(15);
        expect($rule->evaluate(5000))->toBe(0);
    });

    test('eq operator matches exactly', function () {
        $rule = ScoringRule::factory()->create([
            'condition_operator' => 'eq',
            'condition_value' => 'negotiation',
            'points' => 20,
        ]);

        expect($rule->evaluate('negotiation'))->toBe(20);
        expect($rule->evaluate('proposal'))->toBe(0);
    });

    test('contains operator matches substring', function () {
        $rule = ScoringRule::factory()->create([
            'condition_operator' => 'contains',
            'condition_value' => 'enterprise',
            'points' => 10,
        ]);

        expect($rule->evaluate('enterprise deal'))->toBe(10);
        expect($rule->evaluate('small deal'))->toBe(0);
    });

    test('scoring rules boost score when conditions match', function () {
        ScoringRule::factory()->create([
            'category' => 'fit',
            'condition_field' => 'deal_size',
            'condition_operator' => 'gt',
            'condition_value' => '20000',
            'points' => 20,
            'weight' => 1,
        ]);

        $opp = Opportunity::factory()->create(['amount' => 50000]);
        $score = $this->service->score($opp);

        expect($score->fit_score)->toBeGreaterThan(0);
    });

    // ─── Engagement signals ───────────────────────────────────────────────────

    test('can record an engagement signal', function () {
        $opp = Opportunity::factory()->create();
        $signal = $this->service->recordSignal($opp, 'demo_requested');

        expect($signal)->toBeInstanceOf(EngagementSignal::class);
        expect($signal->signal_type)->toBe('demo_requested');
        expect($signal->score_impact)->toBe(20);
    });

    test('recording signal rescores the opportunity', function () {
        $opp = Opportunity::factory()->create(['stage' => 'prospecting']);
        $before = $this->service->score($opp);

        $this->service->recordSignal($opp, 'demo_requested');
        $this->service->recordSignal($opp, 'proposal_viewed');
        $this->service->recordSignal($opp, 'meeting_attended');

        $after = $opp->score()->first();

        expect($after->engagement_score)->toBeGreaterThan(0);
    });

    test('isRecent returns true for signal within 14 days', function () {
        $opp = Opportunity::factory()->create();
        $signal = EngagementSignal::factory()->create([
            'opportunity_id' => $opp->id,
            'occurred_at' => now()->subDays(3),
        ]);

        expect($signal->isRecent())->toBeTrue();
    });

    test('isRecent returns false for old signal', function () {
        $opp = Opportunity::factory()->create();
        $signal = EngagementSignal::factory()->create([
            'opportunity_id' => $opp->id,
            'occurred_at' => now()->subDays(30),
        ]);

        expect($signal->isRecent())->toBeFalse();
    });

    // ─── Pipeline forecast ────────────────────────────────────────────────────

    test('pipeline forecast returns expected keys', function () {
        $opps = Opportunity::factory()->count(3)->create(['stage' => 'proposal', 'amount' => 10000]);
        foreach ($opps as $opp) {
            $this->service->score($opp);
        }

        $forecast = $this->service->pipelineForecast();

        expect($forecast)->toHaveKeys([
            'committed_forecast', 'upside_forecast', 'pipeline_total',
            'opportunities', 'avg_win_probability',
        ]);
        expect($forecast['opportunities'])->toBe(3);
    });

    // ─── API Endpoints ────────────────────────────────────────────────────────

    test('POST /api/v1/crm/opportunities/{id}/score scores opportunity', function () {
        $opp = Opportunity::factory()->create(['stage' => 'proposal', 'amount' => 50000]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/crm/opportunities/{$opp->id}/score");

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['opportunity_id', 'score', 'action']);
        expect($response->json('score.grade'))->toBeIn(['A', 'B', 'C', 'D', 'F']);
    });

    test('GET /api/v1/crm/opportunities/{id}/score returns cached score', function () {
        $opp = Opportunity::factory()->create();
        $this->service->score($opp);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/opportunities/{$opp->id}/score");

        expect($response->status())->toBe(200);
        expect($response->json('is_high_value'))->toBeIn([true, false]);
    });

    test('POST /api/v1/crm/opportunities/{id}/signals records signal', function () {
        $opp = Opportunity::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/crm/opportunities/{$opp->id}/signals", [
                'signal_type' => 'demo_requested',
                'description' => 'Product demo held',
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('signal_type'))->toBe('demo_requested');
        expect($response->json('score_impact'))->toBe(20);
    });

    test('GET /api/v1/crm/opportunities/{id}/signals lists signals', function () {
        $opp = Opportunity::factory()->create();
        EngagementSignal::factory()->count(3)->create(['opportunity_id' => $opp->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/opportunities/{$opp->id}/signals");

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    test('GET /api/v1/crm/scoring/leaderboard returns top scored opportunities', function () {
        $opps = Opportunity::factory()->count(5)->create();
        foreach ($opps as $opp) {
            $this->service->score($opp);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/scoring/leaderboard');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(5);
    });

    test('GET /api/v1/crm/scoring/forecast returns pipeline forecast', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/scoring/forecast');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['committed_forecast', 'pipeline_total', 'opportunities']);
    });

    test('POST /api/v1/crm/scoring/rules creates a scoring rule', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/crm/scoring/rules', [
                'name' => 'Large Deal Bonus',
                'category' => 'fit',
                'condition_field' => 'deal_size',
                'condition_operator' => 'gt',
                'condition_value' => '50000',
                'points' => 25,
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('name'))->toBe('Large Deal Bonus');
    });

    test('GET /api/v1/crm/scoring/rules lists all rules', function () {
        ScoringRule::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/scoring/rules');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveCount(3);
    });

    test('POST /api/v1/crm/scoring/score-all scores all open opportunities', function () {
        Opportunity::factory()->count(3)->create(['stage' => 'proposal']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/crm/scoring/score-all');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['scored', 'errors']);
        expect($response->json('scored'))->toBe(3);
    });
});
