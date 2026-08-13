<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Database\Factories\Admin\BackupScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $table = 'admin_backup_schedules';

    protected $fillable = [
        'type',
        'frequency',
        'time_of_day',
        'retention_days',
        'storage_driver',
        'enabled',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled'     => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BackupScheduleFactory
    {
        return BackupScheduleFactory::new();
    }
}
