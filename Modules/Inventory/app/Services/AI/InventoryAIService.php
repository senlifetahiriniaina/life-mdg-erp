<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\AI;

use Modules\Core\Services\AI\AIService;

class InventoryAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function forecastDemand(array $productData, array $historicalMovements): array
    {
        $response = $this->ai->ask(
            'Analyze these stock movements and forecast demand for the next 30 days. Return valid JSON only: {"forecast":{"daily_avg":X,"monthly_estimate":Y,"confidence":"high|medium|low","seasonal_notes":"..."}}',
            ['product' => json_encode($productData), 'movements' => json_encode($historicalMovements)],
            'Inventory'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['forecast' => [], 'raw' => $response];
    }

    public function suggestReorder(array $stockSummary): array
    {
        $response = $this->ai->ask(
            'Analyze current stock levels and suggest reorder priorities. Return valid JSON only: {"suggestions":[{"product_id":X,"action":"reorder|watch|ok","suggested_qty":Y,"urgency":"urgent|soon|normal","reason":"..."}]}',
            ['stock' => json_encode($stockSummary)],
            'Inventory'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['suggestions' => [], 'raw' => $response];
    }

    public function analyzeAnomalies(array $movements): string
    {
        return $this->ai->ask(
            'Analyze these stock movements for anomalies, suspicious patterns, or data quality issues. Summarize findings concisely.',
            ['movements' => json_encode($movements)],
            'Inventory'
        );
    }

    public function classifyProductsABC(array $products): array
    {
        $prompt = 'Perform ABC inventory classification on these products based on value and turnover. Return JSON with: class_a (array of product_ids — top 20% by value), class_b (array — next 30%), class_c (array — remaining 50%), classification_criteria (string), recommendations (array).';
        $result = $this->ai->ask($prompt, ['products' => json_encode($products)], 'Inventory', 'en');

        return ['classification' => $result];
    }

    public function detectObsoleteProducts(array $products): array
    {
        $prompt = 'Identify potentially obsolete products based on sales velocity, last sale date, and stock age. Return JSON with: obsolete (array of {product_id, reason, last_sold, days_no_movement, recommended_action}), total_obsolete_value (float), recommendations (array).';
        $result = $this->ai->ask($prompt, ['products' => json_encode($products)], 'Inventory', 'en');

        return ['report' => $result];
    }
}
