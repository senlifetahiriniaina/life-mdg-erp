<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Database\Factories\LeadFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $owner_id
 * @property int|null $contact_id
 * @property string $title
 * @property string $status
 * @property string $source
 * @property int|null $score
 * @property string|null $estimated_value
 * @property string|null $currency
 * @property string|null $description
 * @property Carbon|null $converted_at
 * @property array<string,mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Lead extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'crm_leads';

    protected static string $logName = 'crm';

    protected static array $logAttributes = ['title', 'status', 'score', 'owner_id', 'contact_id'];

    protected static bool $logOnlyDirty = true;

    protected static bool $submitEmptyLogs = false;

    protected $fillable = [
        'owner_id', 'contact_id', 'title', 'status', 'source',
        'company_id', 'converted_to_contact_id',
        'score', 'estimated_value', 'currency', 'description',
        'converted_at', 'custom_fields',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'custom_fields' => 'array',
        'converted_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
