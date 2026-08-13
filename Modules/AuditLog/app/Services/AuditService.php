<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Modules\AuditLog\Models\AuditLog;

class AuditService
{
    public function log(array $data): AuditLog
    {
        return AuditLog::create(array_merge(['created_at' => now()], $data));
    }

    public function getActivity(
        int $tenantId,
        array $filters = [],
        int $perPage = 50
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $q = AuditLog::forTenant($tenantId)->orderByDesc('created_at');

        if (!empty($filters['module'])) {
            $q->forModule($filters['module']);
        }
        if (!empty($filters['user_id'])) {
            $q->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $q->where('action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $q->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->where('created_at', '<=', $filters['to']);
        }

        return $q->paginate($perPage);
    }

    public function getStats(int $tenantId): array
    {
        $base = AuditLog::forTenant($tenantId);

        return [
            'today'      => (clone $base)->whereDate('created_at', today())->count(),
            'this_week'  => (clone $base)->where('created_at', '>=', now()->startOfWeek())->count(),
            'by_action'  => (clone $base)
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->pluck('count', 'action'),
        ];
    }
}
