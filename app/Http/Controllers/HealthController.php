<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis'    => $this->checkRedis(),
            'queue'    => $this->checkQueue(),
            'storage'  => $this->checkStorage(),
            'modules'  => $this->checkModules(),
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'status'    => $allHealthy ? 'ok' : 'degraded',
            'version'   => config('app.version', '1.0.0'),
            'env'       => config('app.env'),
            'timestamp' => now()->toISOString(),
            'checks'    => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $ms, 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $ms = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            $failed = DB::table('failed_jobs')->count();

            return [
                'status'      => 'ok',
                'pending'     => $size,
                'failed'      => $failed,
                'connection'  => config('queue.default'),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('app/.healthcheck');
            file_put_contents($path, 'ok');
            $ok = file_get_contents($path) === 'ok';
            unlink($path);

            return ['status' => $ok ? 'ok' : 'error', 'driver' => config('filesystems.default')];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkModules(): array
    {
        $modules = [
            'CRM', 'HR', 'Inventory', 'Accounting', 'Manufacturing',
            'POS', 'Ecommerce', 'BI', 'Email', 'Documents',
            'Helpdesk', 'Projects', 'WhatsApp',
        ];

        $enabled = [];
        $disabled = [];

        foreach ($modules as $module) {
            if (app('erp.modules')->isEnabled($module)) {
                $enabled[] = $module;
            } else {
                $disabled[] = $module;
            }
        }

        return [
            'status'   => 'ok',
            'enabled'  => $enabled,
            'disabled' => $disabled,
            'total'    => count($modules),
        ];
    }
}
