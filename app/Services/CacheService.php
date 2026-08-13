<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;

class CacheService
{
    const CACHE_TTL = 3600; // 1 hour default
    const CACHE_TTL_SHORT = 300; // 5 minutes
    const CACHE_TTL_LONG = 86400; // 24 hours

    public static function remember(
        string $key,
        callable $callback,
        int $ttl = self::CACHE_TTL
    ) {
        return Cache::remember($key, $ttl, $callback);
    }

    public static function rememberForever(string $key, callable $callback)
    {
        return Cache::rememberForever($key, $callback);
    }

    public static function get(string $key, $default = null)
    {
        return Cache::get($key, $default);
    }

    public static function put(string $key, $value, int $ttl = self::CACHE_TTL): void
    {
        Cache::put($key, $value, $ttl);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
    }

    public static function flush(string $prefix = ''): void
    {
        if ($prefix) {
            Cache::tags([$prefix])->flush();
        } else {
            Cache::flush();
        }
    }

    // Cache keys for common queries
    public static function tenantKey(int $tenantId, string $resource): string
    {
        return "tenant.{$tenantId}.{$resource}";
    }

    public static function userKey(int $userId, string $resource): string
    {
        return "user.{$userId}.{$resource}";
    }

    public static function listKey(string $resource, array $filters = []): string
    {
        $filterStr = implode('.', array_map(fn($k, $v) => "{$k}:{$v}", array_keys($filters), $filters));
        return "list.{$resource}." . ($filterStr ?: 'all');
    }

    public static function modelKey(string $model, int $id): string
    {
        return "model.{$model}.{$id}";
    }

    public static function statsKey(int $tenantId, string $metric): string
    {
        return "stats.{$tenantId}.{$metric}";
    }

    public static function invalidateList(string $resource): void
    {
        Cache::tags(["list:{$resource}"])->flush();
    }

    public static function invalidateModel(string $model, int $id): void
    {
        Cache::tags(["model:{$model}",$model . ":{$id}"])->flush();
    }

    public static function invalidateTenant(int $tenantId): void
    {
        Cache::tags(["tenant:{$tenantId}"])->flush();
    }

    public static function getOrFetch(
        string $key,
        callable $fetch,
        int $ttl = self::CACHE_TTL
    ) {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $fetch();
        Cache::put($key, $data, $ttl);

        return $data;
    }
}
