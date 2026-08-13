<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Modules\Core\Models\Concerns\BelongsToTenant;
use Modules\CRM\Database\Factories\ContactFactory;
use Modules\Helpdesk\Traits\HelpdeskLinkable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $account_id
 * @property int|null $owner_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $job_title
 * @property string|null $department
 * @property string|null $linkedin_url
 * @property string|null $source
 * @property string|null $status
 * @property string|null $notes
 * @property array<string,mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Contact extends Model
{
    use BelongsToTenant, HasFactory, HelpdeskLinkable, LogsActivity, Searchable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    protected $table = 'crm_contacts';

    protected static string $logName = 'crm';

    protected static array $logAttributes = ['first_name', 'last_name', 'email', 'phone', 'status', 'owner_id', 'account_id'];

    protected static bool $logOnlyDirty = true;

    protected static bool $submitEmptyLogs = false;

    protected $fillable = [
        'account_id', 'owner_id', 'company_id', 'first_name', 'last_name', 'lead_id',
        'email', 'phone', 'mobile', 'job_title', 'department',
        'linkedin_url', 'source', 'status', 'notes', 'custom_fields',
        'archived_at', 'merged_into_id',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'archived_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCompany($query, $company)
    {
        $companyId = is_object($company) ? $company->id : $company;

        return $query->where('company_id', $companyId);
    }

    public function archive(): bool
    {
        $this->status = 'archived';
        $this->archived_at = now();

        return $this->save();
    }

    public function unarchive(): bool
    {
        $this->status = 'active';
        $this->archived_at = null;

        return $this->save();
    }

    /**
     * Mark another contact as a duplicate merged into this one.
     */
    public function mergeDuplicate(self $duplicate): bool
    {
        $duplicate->status = 'merged';
        $duplicate->merged_into_id = $this->id;

        return $duplicate->save();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'contact_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function searchableAs(): string
    {
        $tenantId = tenancy()->tenant?->getTenantKey() ?? 'central';

        return "tenant_{$tenantId}_contacts";
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'company' => $this->account?->name,
        ];
    }
}
