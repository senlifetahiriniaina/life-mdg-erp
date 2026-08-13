<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QueryOptimizationService
{
    public static array $relationshipLoadStrategies = [
        // CRM
        'Modules\CRM\Models\Account' => [
            'list' => ['contacts:id,account_id,first_name,last_name,email'],
            'show' => ['contacts', 'opportunities:id,account_id,name,amount,stage'],
        ],
        'Modules\CRM\Models\Contact' => [
            'list' => [],
            'show' => ['account:id,name'],
        ],
        'Modules\CRM\Models\Opportunity' => [
            'list' => ['account:id,name'],
            'show' => ['account', 'owner:id,name,email'],
        ],

        // Inventory
        'Modules\Inventory\Models\Product' => [
            'list' => [],
            'show' => ['warehouses:id,name', 'stocks:id,warehouse_id,quantity,reorder_level'],
        ],

        // Accounting
        'Modules\Accounting\Models\Invoice' => [
            'list' => ['customer:id,name'],
            'show' => ['customer', 'lineItems', 'payments:id,amount,payment_date,method'],
        ],

        // HR
        'Modules\HR\Models\Employee' => [
            'list' => [],
            'show' => ['department:id,name', 'manager:id,name'],
        ],
    ];

    public static function optimizeQuery(
        Builder $query,
        string $action = 'list',
        bool $withCounts = false
    ): Builder {
        $modelClass = $query->getModel()::class;
        $relationships = self::$relationshipLoadStrategies[$modelClass][$action] ?? [];

        if (!empty($relationships)) {
            $query->with($relationships);
        }

        if ($withCounts) {
            $query->withCount(self::getCountRelationships($modelClass));
        }

        return $query;
    }

    public static function getCountRelationships(string $modelClass): array
    {
        return match($modelClass) {
            'Modules\CRM\Models\Account' => ['contacts', 'opportunities'],
            'Modules\Inventory\Models\Product' => ['stocks'],
            'Modules\Accounting\Models\Invoice' => ['lineItems', 'payments'],
            'Modules\HR\Models\Employee' => ['leaveRequests'],
            default => [],
        };
    }

    public static function selectOptimalColumns(
        Builder $query,
        array $columns = []
    ): Builder {
        if (empty($columns)) {
            return $query; // Select all if not specified
        }

        // Always include id for relationships and tenant_id for security
        $essentialColumns = ['id', 'tenant_id', 'created_at', 'updated_at'];
        $allColumns = array_unique(array_merge($essentialColumns, $columns));

        return $query->select($allColumns);
    }

    public static function paginateOptimized(
        Builder $query,
        int $perPage = 15,
        array $columns = ['*'],
        string $pageName = 'page',
        int $page = null
    ) {
        return $query->paginate(
            perPage: $perPage,
            columns: $columns,
            pageName: $pageName,
            page: $page
        );
    }

    public static function chunkProcess(
        Builder $query,
        int $chunkSize = 1000,
        callable $callback = null
    ): void {
        $query->chunk($chunkSize, function ($items) use ($callback) {
            if ($callback) {
                $callback($items);
            }
        });
    }

    public static function getOptimalIndexes(string $modelClass): array
    {
        return match($modelClass) {
            'Modules\CRM\Models\Contact' => ['account_id', 'status', 'tenant_id'],
            'Modules\CRM\Models\Account' => ['type', 'status', 'tenant_id'],
            'Modules\Inventory\Models\Product' => ['category', 'status', 'tenant_id'],
            'Modules\Accounting\Models\Invoice' => ['status', 'customer_id', 'tenant_id'],
            default => ['tenant_id'],
        };
    }

    public static function enableQueryLogging(bool $enable = true): void
    {
        if ($enable) {
            \DB::listen(function ($query) {
                if ($query->time > 1000) { // Log queries taking > 1 second
                    \Log::warning('Slow Query', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }
    }
}
