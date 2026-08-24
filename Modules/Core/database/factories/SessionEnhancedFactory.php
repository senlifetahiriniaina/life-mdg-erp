<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\SessionEnhanced;

/**
 * Chantier 38.1: this was scaffold boilerplate (fake()->word() on every
 * column regardless of type, including datetime/int-cast columns and a dozen
 * fields — name/title/slug/status/code/email/... — that don't exist on
 * SessionEnhanced's real $fillable at all) — a guaranteed Carbon-parse fatal
 * on the first real use, confirmed empirically while building
 * Chantier38SessionManagementTest.php. Rewritten to match the model's real
 * $fillable/$casts.
 */
class SessionEnhancedFactory extends Factory
{
    protected $model = SessionEnhanced::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => 1,
            'ip_address' => fake()->ipv4(),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'device_fingerprint' => hash('sha256', fake()->uuid()),
            'browser_fingerprint' => hash('sha256', fake()->uuid()),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet', 'web']),
            'created_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(2),
            'fingerprint_checked_at' => now(),
            'regeneration_count' => 0,
            'concurrent_session_number' => 1,
            'suspicious_activity_count' => 0,
            'tenant_id' => null,
        ];
    }
}
