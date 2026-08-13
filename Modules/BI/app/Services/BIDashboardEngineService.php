<?php

namespace Modules\BI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BIDashboardEngineService
{
    const CACHE_TTL = 300; // 5 minutes for dashboard data
    const MAX_WIDGETS = 50;

    /**
     * Create custom dashboard
     */
    public function createDashboard(array $config): array
    {
        $dashboardId = uniqid('dashboard_');

        $dashboard = [
            'id' => $dashboardId,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'layout' => $config['layout'] ?? 'grid', // grid, flex, custom
            'widgets' => [],
            'refresh_interval' => $config['refresh_interval'] ?? 300, // seconds
            'is_public' => $config['is_public'] ?? false,
            'owner_id' => $config['owner_id'],
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("dashboard:{$dashboardId}", $dashboard, now()->addDays(90));

        return [
            'dashboard_id' => $dashboardId,
            'status' => 'created',
        ];
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(string $dashboardId, array $widgetConfig): array
    {
        $dashboard = Cache::get("dashboard:{$dashboardId}");

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        if (count($dashboard['widgets']) >= self::MAX_WIDGETS) {
            return ['error' => 'Maximum widgets reached'];
        }

        $widgetId = uniqid('widget_');
        $widget = [
            'id' => $widgetId,
            'type' => $widgetConfig['type'], // chart, metric, table, kpi, gauge, heatmap
            'title' => $widgetConfig['title'],
            'report_id' => $widgetConfig['report_id'],
            'position' => $widgetConfig['position'] ?? ['row' => 0, 'col' => 0],
            'size' => $widgetConfig['size'] ?? ['width' => 4, 'height' => 3],
            'config' => $widgetConfig['config'] ?? [],
            'created_at' => now()->toIso8601String(),
        ];

        $dashboard['widgets'][] = $widget;
        Cache::put("dashboard:{$dashboardId}", $dashboard, now()->addDays(90));

        return [
            'widget_id' => $widgetId,
            'status' => 'added',
            'dashboard_id' => $dashboardId,
        ];
    }

    /**
     * Update widget configuration
     */
    public function updateWidget(string $dashboardId, string $widgetId, array $config): array
    {
        $dashboard = Cache::get("dashboard:{$dashboardId}");

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        $widgetIndex = $this->findWidgetIndex($dashboard['widgets'], $widgetId);

        if ($widgetIndex === -1) {
            return ['error' => 'Widget not found'];
        }

        foreach ($config as $key => $value) {
            if (in_array($key, ['type', 'title', 'report_id', 'position', 'size', 'config'])) {
                $dashboard['widgets'][$widgetIndex][$key] = $value;
            }
        }

        Cache::put("dashboard:{$dashboardId}", $dashboard, now()->addDays(90));

        return [
            'widget_id' => $widgetId,
            'status' => 'updated',
        ];
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(string $dashboardId, string $widgetId): array
    {
        $dashboard = Cache::get("dashboard:{$dashboardId}");

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        $dashboard['widgets'] = array_filter(
            $dashboard['widgets'],
            fn($w) => $w['id'] !== $widgetId
        );

        Cache::put("dashboard:{$dashboardId}", $dashboard, now()->addDays(90));

        return [
            'widget_id' => $widgetId,
            'status' => 'removed',
        ];
    }

    /**
     * Get dashboard with all widget data
     */
    public function getDashboard(string $dashboardId): ?array
    {
        return Cache::remember("dashboard:full:{$dashboardId}", self::CACHE_TTL, function () use ($dashboardId) {
            $dashboard = Cache::get("dashboard:{$dashboardId}");

            if (!$dashboard) {
                return null;
            }

            // Load widget data
            foreach ($dashboard['widgets'] as &$widget) {
                if (isset($widget['report_id'])) {
                    // Widget data would be loaded from report
                    $widget['data'] = []; // Placeholder
                }
            }

            return $dashboard;
        });
    }

    /**
     * Refresh dashboard data
     */
    public function refreshDashboard(string $dashboardId): array
    {
        Cache::forget("dashboard:full:{$dashboardId}");

        $dashboard = $this->getDashboard($dashboardId);

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        return [
            'dashboard_id' => $dashboardId,
            'status' => 'refreshed',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Clone dashboard
     */
    public function cloneDashboard(string $dashboardId, string $newName): array
    {
        $dashboard = Cache::get("dashboard:{$dashboardId}");

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        $newDashboardId = uniqid('dashboard_');
        $dashboard['id'] = $newDashboardId;
        $dashboard['name'] = $newName;

        Cache::put("dashboard:{$newDashboardId}", $dashboard, now()->addDays(90));

        return [
            'new_dashboard_id' => $newDashboardId,
            'status' => 'cloned',
        ];
    }

    /**
     * Share dashboard
     */
    public function shareDashboard(string $dashboardId, array $userIds, string $permission = 'view'): array
    {
        $dashboard = Cache::get("dashboard:{$dashboardId}");

        if (!$dashboard) {
            return ['error' => 'Dashboard not found'];
        }

        $shares = Cache::get("dashboard:shares:{$dashboardId}", []);

        foreach ($userIds as $userId) {
            $shares[$userId] = [
                'permission' => $permission, // view, edit, manage
                'shared_at' => now()->toIso8601String(),
            ];
        }

        Cache::put("dashboard:shares:{$dashboardId}", $shares, now()->addDays(90));

        return [
            'dashboard_id' => $dashboardId,
            'shared_with' => count($userIds),
            'status' => 'shared',
        ];
    }

    /**
     * Set dashboard as favorite
     */
    public function toggleFavorite(string $dashboardId, int $userId): array
    {
        $favorites = Cache::get("user:favorites:{$userId}", []);

        $key = array_search($dashboardId, $favorites);

        if ($key !== false) {
            unset($favorites[$key]);
            $status = 'removed';
        } else {
            $favorites[] = $dashboardId;
            $status = 'added';
        }

        Cache::put("user:favorites:{$userId}", array_values($favorites), now()->addDays(365));

        return [
            'dashboard_id' => $dashboardId,
            'status' => $status,
        ];
    }

    /**
     * Get user favorite dashboards
     */
    public function getFavoriteDashboards(int $userId): array
    {
        $favorites = Cache::get("user:favorites:{$userId}", []);

        $dashboards = [];
        foreach ($favorites as $dashboardId) {
            $dashboard = Cache::get("dashboard:{$dashboardId}");
            if ($dashboard) {
                $dashboards[] = $dashboard;
            }
        }

        return $dashboards;
    }

    /**
     * Helper: Find widget index
     */
    private function findWidgetIndex(array $widgets, string $widgetId): int
    {
        foreach ($widgets as $index => $widget) {
            if ($widget['id'] === $widgetId) {
                return $index;
            }
        }

        return -1;
    }
}
