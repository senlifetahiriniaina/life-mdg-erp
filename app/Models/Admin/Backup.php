<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Database\Factories\Admin\BackupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasFactory;

    protected $table = 'admin_backups';

    protected $fillable = [
        'type',
        'status',
        'size_bytes',
        'file_path',
        'storage_driver',
        'notes',
        'triggered_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'size_bytes'   => 'integer',
    ];

    protected static function newFactory(): BackupFactory
    {
        return BackupFactory::new();
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
