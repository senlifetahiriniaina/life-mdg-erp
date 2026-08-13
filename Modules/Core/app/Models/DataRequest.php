<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\DataRequestFactory;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property string $request_type
 * @property string $status
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $data_snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DataRequest extends Model
{
    use HasFactory;

    protected $table = 'core_data_requests';

    protected $fillable = [
        'user_id',
        'email',
        'request_type',
        'status',
        'notes',
        'admin_notes',
        'requested_at',
        'completed_at',
        'expires_at',
        'data_snapshot',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'data_snapshot' => 'array',
    ];

    protected static function newFactory(): DataRequestFactory
    {
        return DataRequestFactory::new();
    }

    public function getResultAttribute(): ?array
    {
        return $this->data_snapshot;
    }

    public function getDownloadTokenAttribute(): ?string
    {
        return $this->data_snapshot['download_token'] ?? null;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->data_snapshot['download_url'] ?? null;
    }

    public function getExportedAtAttribute(): ?string
    {
        return $this->data_snapshot['exported_at'] ?? null;
    }

    public function getFileSizeAttribute(): ?int
    {
        return $this->data_snapshot['file_size'] ?? null;
    }

    public function getEncryptedAttribute(): ?bool
    {
        return $this->data_snapshot['encrypted'] ?? null;
    }

    public function getReasonAttribute(): ?string
    {
        return $this->admin_notes;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->requested_at->addDays(30)->isPast();
    }

    public function complete(array $data = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'data_snapshot' => $data,
        ]);
    }

    public function reject(string $reason = ''): void
    {
        $this->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
        ]);
    }
}
