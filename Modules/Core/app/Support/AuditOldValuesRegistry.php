<?php

declare(strict_types=1);

namespace Modules\Core\Support;

class AuditOldValuesRegistry
{
    /** @var array<int, array<string, mixed>> */
    private static array $store = [];

    public static function set(object $model, mixed $values): void
    {
        self::$store[spl_object_id($model)] = is_array($values) ? $values : [];
    }

    /** @return array<string, mixed> */
    public static function get(object $model): array
    {
        return self::$store[spl_object_id($model)] ?? [];
    }

    public static function forget(object $model): void
    {
        unset(self::$store[spl_object_id($model)]);
    }
}
