<?php

namespace Modules\Security\Services;

use Modules\Security\Models\ComplianceControl;
use Illuminate\Support\Facades\Log;

class GdprComplianceService
{
    private const GDPR_CONTROLS = [
        'ART-6'  => 'Licéité du traitement',
        'ART-13' => 'Information des personnes concernées',
        'ART-17' => 'Droit à l\'effacement',
        'ART-20' => 'Droit à la portabilité',
        'ART-25' => 'Protection des données dès la conception',
        'ART-32' => 'Sécurité du traitement',
        'ART-33' => 'Notification des violations',
    ];

    public function getComplianceStatus(string $companyId): array
    {
        $controls = ComplianceControl::where('company_id', $companyId)
            ->where('framework', 'GDPR')
            ->get()
            ->keyBy('control_id');

        $status = [];
        foreach (self::GDPR_CONTROLS as $controlId => $name) {
            $control = $controls->get($controlId);
            $status[$controlId] = [
                'name'   => $name,
                'status' => $control?->implementation_status ?? 'not_started',
            ];
        }
        return $status;
    }

    public function processErasureRequest(string $companyId, string $userId): array
    {
        Log::info("GDPR erasure request: company={$companyId}, user={$userId}");
        return ['status' => 'queued', 'reference' => 'GDPR-' . uniqid()];
    }

    public function exportUserData(string $companyId, string $userId): array
    {
        return ['status' => 'processing', 'reference' => 'EXPORT-' . uniqid()];
    }

    public function recordDataBreach(string $companyId, array $details): void
    {
        ComplianceControl::where('company_id', $companyId)
            ->where('control_id', 'ART-33')
            ->update(['last_verified_at' => now()]);

        Log::critical("GDPR data breach recorded for company {$companyId}", $details);
    }
}
