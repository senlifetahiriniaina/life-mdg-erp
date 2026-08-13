<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\CspViolation;

class CspViolationFactory extends Factory
{
    protected $model = CspViolation::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid(),
            'document_uri' => $this->faker->url(),
            'violated_directive' => $this->faker->randomElement([
                'script-src',
                'style-src',
                'img-src',
                'font-src',
                'object-src',
                'frame-src',
                'connect-src',
            ]),
            'effective_directive' => 'script-src',
            'original_policy' => "script-src 'self'; style-src 'self' https://fonts.googleapis.com",
            'disposition' => 'enforce',
            'blocked_uri' => 'https://malicious.com/script.js',
            'source_file' => 'https://example.com/app.js',
            'line_number' => $this->faker->numberBetween(1, 1000),
            'column_number' => $this->faker->numberBetween(1, 100),
            'status_code' => 200,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'user_id' => null,
            'tenant_id' => null,
            'module' => $this->faker->randomElement([
                'CRM',
                'Accounting',
                'HR',
                'Inventory',
                'Manufacturing',
                'Ecommerce',
                'BI',
            ]),
            'violation_data' => [
                'document-uri' => $this->faker->url(),
                'violated-directive' => 'script-src',
                'effective-directive' => 'script-src',
                'original-policy' => "script-src 'self'",
                'disposition' => 'enforce',
                'blocked-uri' => 'https://malicious.com/script.js',
                'source-file' => 'https://example.com/app.js',
                'line-number' => 42,
                'column-number' => 10,
                'status-code' => 200,
            ],
            'is_internal_request' => false,
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'created_at' => now(),
            'updated_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state([
            'violated_directive' => 'script-src',
            'severity' => 'critical',
        ]);
    }

    public function high(): static
    {
        return $this->state([
            'violated_directive' => 'style-src',
            'severity' => 'high',
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'resolved_at' => now(),
        ]);
    }

    public function forUser(?string $userId): static
    {
        return $this->state([
            'user_id' => $userId,
        ]);
    }

    public function forModule(string $module): static
    {
        return $this->state([
            'module' => $module,
        ]);
    }

    public function forTenant(?string $tenantId): static
    {
        return $this->state([
            'tenant_id' => $tenantId,
        ]);
    }
}
