<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;

class AuditService
{
    public function log(
        string $action,
        ?int $userId = null,
        ?object $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
        ?string $module = null,
        ?string $eventType = null,
        ?string $description = null,
    ): AuditLog {
        $subjectType = null;
        $subjectId   = null;
        $userName    = null;
        $userRole    = null;
        $companyId   = null;

        if ($subject !== null) {
            $subjectType = get_class($subject);
            $subjectId   = method_exists($subject, 'getKey') ? (int) $subject->getKey() : null;
        }

        if ($userId !== null) {
            $user      = User::find($userId);
            $userName  = $user?->name;
            $userRole  = $user?->getRoleNames()->first();
            $companyId = $user?->company_id;
        }

        // Chantier 19 Lot 3: this generic, DI-injected logger — called
        // throughout the app, including several sites inside this very
        // module (SecretsService::storeSecret/rotateSecret/revokeSecret,
        // SecretAccessControl::grantSecretAccess/revokeSecretAccess/
        // generateApiKey/revokeApiKey, TenantManagerService's own
        // provision/suspend/reactivate/... audit trail) — never set
        // company_id at all, unlike the other 3 real writers this app's
        // audit trail relies on (RecordsActivity, the root AuditableActions
        // trait, AuditAuthListener), all fixed in Chantier 8.5-light. Every
        // entry logged through this path landed with company_id NULL,
        // invisible under GET /api/v1/audit-logs' `where('company_id', ...)`
        // filter for every company — confirmed empirically via a real
        // AuditService::log() call whose row came back with a NULL
        // company_id instead of the acting user's real one. Falls back to
        // the currently-authenticated actor when no $userId was resolved
        // (matching AuditableActions'/RecordsActivity's own
        // auth()->user()?->company_id ?? 0 convention) rather than leaving
        // it null, since a null company_id is functionally indistinguishable
        // from "belongs to no company" for the controller's int-keyed filter.
        $companyId ??= auth()->user()?->company_id;

        return AuditLog::create([
            'user_id'      => $userId,
            'user_name'    => $userName,
            'user_role'    => $userRole,
            'company_id'   => $companyId ?? 0,
            'action'       => $action,
            'module'       => $module ?? ($subject !== null ? $this->inferModule($subject) : null),
            'event_type'   => $eventType ?? $action,
            'description'  => $description,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'old_values'   => empty($oldValues) ? null : $oldValues,
            'new_values'   => empty($newValues) ? null : $newValues,
            'ip_address'   => $request?->ip(),
            'user_agent'   => $request?->userAgent(),
        ]);
    }

    public function logLogin(int $userId, ?Request $request = null): AuditLog
    {
        $user = User::find($userId);

        return $this->log(
            action: 'login',
            userId: $userId,
            request: $request,
            module: 'Auth',
            eventType: 'login',
            description: "Connexion de {$user?->name}",
        );
    }

    public function logLoginFailed(string $email, ?Request $request = null): AuditLog
    {
        return AuditLog::create([
            'user_id'     => null,
            'user_name'   => $email,
            'action'      => 'login_failed',
            'module'      => 'Auth',
            'event_type'  => 'login_failed',
            'description' => "Tentative de connexion échouée pour {$email}",
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);
    }

    public function logLogout(int $userId, ?Request $request = null): AuditLog
    {
        $user = User::find($userId);

        return $this->log(
            action: 'logout',
            userId: $userId,
            request: $request,
            module: 'Auth',
            eventType: 'logout',
            description: "Déconnexion de {$user?->name}",
        );
    }

    public function logCreate(int $userId, object $model, ?Request $request = null, ?string $module = null): AuditLog
    {
        $newValues = method_exists($model, 'toArray') ? $model->toArray() : [];
        $label     = $this->modelLabel($model);

        return $this->log(
            action: 'created',
            userId: $userId,
            subject: $model,
            oldValues: [],
            newValues: $newValues,
            request: $request,
            module: $module ?? $this->inferModule($model),
            eventType: 'model_created',
            description: "Création de {$label}",
        );
    }

    public function logUpdate(int $userId, object $model, array $oldValues, ?Request $request = null, ?string $module = null): AuditLog
    {
        $newValues = method_exists($model, 'toArray') ? $model->toArray() : [];
        $label     = $this->modelLabel($model);

        return $this->log(
            action: 'updated',
            userId: $userId,
            subject: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request,
            module: $module ?? $this->inferModule($model),
            eventType: 'model_updated',
            description: "Modification de {$label}",
        );
    }

    public function logDelete(int $userId, object $model, ?Request $request = null, ?string $module = null): AuditLog
    {
        $oldValues = method_exists($model, 'toArray') ? $model->toArray() : [];
        $label     = $this->modelLabel($model);

        return $this->log(
            action: 'deleted',
            userId: $userId,
            subject: $model,
            oldValues: $oldValues,
            newValues: [],
            request: $request,
            module: $module ?? $this->inferModule($model),
            eventType: 'model_deleted',
            description: "Suppression de {$label}",
        );
    }

    public function logExport(int $userId, string $resource, ?Request $request = null, ?string $module = null): AuditLog
    {
        return $this->log(
            action: 'export',
            userId: $userId,
            request: $request,
            module: $module,
            eventType: 'export',
            description: "Export de {$resource}",
        );
    }

    public function getUserActivity(int $userId, int $limit = 50): Collection
    {
        return AuditLog::where('user_id', $userId)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getSubjectHistory(string $subjectType, int $subjectId): Collection
    {
        return AuditLog::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getStats(array $filters = []): array
    {
        $query = AuditLog::query();

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();

        $byAction = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $byModule = (clone $query)
            ->whereNotNull('module')
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->pluck('count', 'module')
            ->toArray();

        $topUsers = (clone $query)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, user_name, COUNT(*) as count')
            ->groupBy('user_id', 'user_name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['user_id' => $r->user_id, 'user_name' => $r->user_name, 'count' => $r->count]) // @phpstan-ignore-line
            ->toArray();

        $today = (clone $query)->where('created_at', '>=', now()->startOfDay())->count();

        return [
            'total_logs'   => $total,
            'today'        => $today,
            'by_action'    => array_merge(
                ['created' => 0, 'updated' => 0, 'deleted' => 0, 'login' => 0, 'logout' => 0],
                $byAction,
            ),
            'by_module'    => $byModule,
            'top_users'    => $topUsers,
        ];
    }

    private function modelLabel(object $model): string
    {
        $class = class_basename($model);

        foreach (['name', 'title', 'number', 'email', 'first_name'] as $field) {
            if (isset($model->{$field})) {
                return "{$class} « {$model->{$field}} »";
            }
        }

        $key = method_exists($model, 'getKey') ? $model->getKey() : null;

        return $key ? "{$class} #{$key}" : $class;
    }

    private function inferModule(object $model): ?string
    {
        $namespace = get_class($model);

        if (preg_match('/Modules\\\\([^\\\\]+)\\\\/', $namespace, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
