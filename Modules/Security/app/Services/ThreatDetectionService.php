<?php

namespace Modules\Security\app\Services;

use Modules\Security\app\Models\ThreatIndicator;
use Modules\Security\app\Models\SecurityIncident;
use Illuminate\Http\Request;

class ThreatDetectionService
{
    public function __construct(private RateLimitService $rateLimiter) {}

    public function analyzeRequest(Request $request): array
    {
        $threats = [];
        $ip = $request->ip();

        if ($this->rateLimiter->isLimited($request, 'api')) {
            $threats[] = ['type' => 'rate_limit_exceeded', 'severity' => 'medium', 'ip' => $ip];
        }

        if ($this->isKnownThreatIp($ip)) {
            $threats[] = ['type' => 'known_threat_ip', 'severity' => 'high', 'ip' => $ip];
        }

        return $threats;
    }

    public function isKnownThreatIp(string $ip): bool
    {
        return ThreatIndicator::where('indicator_type', 'ip')
            ->where('indicator_value', $ip)
            ->where('is_whitelisted', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function createIncident(string $companyId, string $type, string $severity, string $description, array $context = []): SecurityIncident
    {
        return SecurityIncident::create([
            'company_id'        => $companyId,
            'incident_type'     => $type,
            'severity'          => $severity,
            'description'       => $description,
            'threat_indicators' => $context,
            'incident_status'   => 'open',
            'detected_at'       => now(),
        ]);
    }

    public function addThreatIndicator(string $type, string $value, string $level, string $source): ThreatIndicator
    {
        return ThreatIndicator::updateOrCreate(
            ['indicator_type' => $type, 'indicator_value' => $value],
            ['threat_level' => $level, 'source' => $source, 'detected_at' => now()]
        );
    }
}
