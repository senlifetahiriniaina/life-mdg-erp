<?php
declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Forecast;

uses(RefreshDatabase::class);

describe('CRM Forecasting Advanced Features', function () {
    it('generates forecast with multiple probability scenarios', function () {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-Q2'])
            ->assertStatus(201);
        expect($response->json('pipeline_total'))->toBeGreaterThan(0);
    });
});
