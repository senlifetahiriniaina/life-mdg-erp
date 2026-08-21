<?php

namespace Modules\Security\Services;

use Modules\Security\Models\AuthenticationEvent;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ThreatIndicator;
use Illuminate\Support\Facades\Cache;

class SecurityAuditService
{
    public function recordAuthEvent(array $data): AuthenticationEvent
    {
        return AuthenticationEvent::create($data);
    }

    public function getFailedLoginAttempts(string $ip, int $minutes = 15): int
    {
        return AuthenticationEvent::where('ip_address', $ip)
            ->where('status', 'failure')
            ->where('authenticated_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    public function getSecuritySummary(string $companyId): array
    {
        $cacheKey = "security_summary_{$companyId}";
        return Cache::remember($cacheKey, 300, function () use ($companyId) {
            return [
                'open_incidents'    => SecurityIncident::where('company_id', $companyId)->where('incident_status', 'open')->count(),
                'compliance_score'  => $this->getComplianceScore($companyId),
                'auth_failures_24h' => AuthenticationEvent::where('status', 'failure')->where('authenticated_at', '>=', now()->subDay())->count(),
                // Chantier 32.3: this used to be a hardcoded 0 (never a real
                // query) — ThreatIndicator has no company_id (a shared,
                // cross-tenant threat feed, same design as AuthenticationEvent
                // and confirmed via Schema::getColumnListing before this fix),
                // so "critical, active, not whitelisted" is the right scope —
                // matching the same non-expired/non-whitelisted semantics
                // ThreatDetectionService::isKnownThreatIp() already uses.
                'critical_threats'  => ThreatIndicator::where('threat_level', 'critical')
                    ->where('is_whitelisted', false)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
            ];
        });
    }

    /**
     * Null (not 0.0) when the company has zero compliance controls at all —
     * "nothing recorded yet" is a materially different state from "0% of
     * recorded controls are implemented", and the real frontend consumer
     * (Security/Index.vue) already distinguishes the two.
     */
    public function getComplianceScore(string $companyId): ?float
    {
        $controls = ComplianceControl::where('company_id', $companyId)->get();
        if ($controls->isEmpty()) {
            return null;
        }
        $implemented = $controls->where('implementation_status', 'implemented')->count();
        return round(($implemented / $controls->count()) * 100, 1);
    }
}
