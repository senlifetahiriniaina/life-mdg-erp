<?php
declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Forecast;
use Modules\CRM\Models\Opportunity;

uses(RefreshDatabase::class);

describe('CRM Forecasting Advanced Features', function () {
    it('generates forecast with multiple probability scenarios', function () {
        $user = User::factory()->create();

        Opportunity::factory()->create([
            'stage' => 'proposal',
            'amount' => 100000,
            'probability' => 50,
            'owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-Q2'])
            ->assertStatus(201);
        expect($response->json('pipeline_total'))->toBeGreaterThan(0);
    });
});
