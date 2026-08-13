<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee compliance document (work permit, certificate, etc.).
 *
 * @property int    $id
 * @property int    $employee_id
 * @property string $document_type  work_permit|residence_permit|professional_cert|medical_cert|driving_license|custom
 * @property string $title
 * @property string|null $reference_number
 * @property string|null $country        ISO alpha-2
 * @property \Carbon\Carbon|null $issue_date
 * @property \Carbon\Carbon|null $expiry_date
 * @property string $status         valid|expiring_soon|expired|pending_renewal
 * @property int    $alert_days_before  default 60
 * @property bool   $alert_sent_60
 * @property bool   $alert_sent_30
 * @property bool   $alert_sent_7
 * @property string|null $file_path
 * @property string|null $notes
 */
class EmployeeDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'employee_id',
        'document_type',
        'title',
        'reference_number',
        'country',
        'issue_date',
        'expiry_date',
        'status',
        'alert_days_before',
        'alert_sent_60',
        'alert_sent_30',
        'alert_sent_7',
        'file_path',
        'notes',
    ];

    protected $casts = [
        'issue_date'       => 'date',
        'expiry_date'      => 'date',
        'alert_days_before' => 'integer',
        'alert_sent_60'    => 'boolean',
        'alert_sent_30'    => 'boolean',
        'alert_sent_7'     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeExpiring($query, int $days = 30)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now());
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function computeStatus(): string
    {
        if (! $this->expiry_date) {
            return 'valid';
        }

        $days = $this->days_until_expiry;

        if ($days < 0) {
            return 'expired';
        }

        if ($days <= $this->alert_days_before) {
            return 'expiring_soon';
        }

        return 'valid';
    }
}
