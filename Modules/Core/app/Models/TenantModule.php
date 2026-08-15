<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $module
 * @property bool $enabled
 * @property string|null $department
 * @property array<string,mixed>|null $settings
 */
class TenantModule extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'module', 'enabled', 'department', 'settings'];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];
}
