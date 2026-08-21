<?php

namespace Modules\Security\Services;

use Modules\Security\Models\ThreatIndicator;
use Modules\Security\Models\SecurityIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        // Chantier 32.3: RateLimitController::blockIp() (an admin action —
        // "IP X has been blocked") wrote a Cache::put("security.blocked_ip.
        // {$ip}", ...) entry that nothing anywhere ever read — the block was
        // never actually enforced by anything, confirmed via grep before this
        // fix. This is the one real read-path into the request lifecycle for
        // an IP reputation check (app/Http/Middleware/RequestInspectionMiddleware,
        // root-level and out of this module's scope, already calls this exact
        // method) — checking the manual-block cache key here, rather than
        // adding a second check into that root middleware, closes the gap
        // without touching a file outside Modules/Security.
        if (Cache::has("security.blocked_ip.{$ip}")) {
            return true;
        }

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
