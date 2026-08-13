<?php

namespace Modules\Security\Services;

use Modules\Security\Models\AuthenticationEvent;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ComplianceControl;
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
                'critical_threats'  => 0,
            ];
        });
    }

    public function getComplianceScore(string $companyId): float
    {
        $controls = ComplianceControl::where('company_id', $companyId)->get();
        if ($controls->isEmpty()) {
            return 0.0;
        }
        $implemented = $controls->where('implementation_status', 'implemented')->count();
        return round(($implemented / $controls->count()) * 100, 1);
    }
}
