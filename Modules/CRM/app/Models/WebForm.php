<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\WebFormFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array<int,mixed> $fields
 * @property string|null $redirect_url
 * @property string|null $success_message
 * @property bool $create_lead
 * @property int|null $pipeline_id
 * @property string $default_lead_source
 * @property bool $is_active
 * @property int|null $created_by
 */
class WebForm extends Model
{
    use HasFactory;

    protected static function newFactory(): WebFormFactory
    {
        return WebFormFactory::new();
    }

    protected $table = 'crm_web_forms';

    protected $fillable = [
        'name', 'slug', 'fields', 'redirect_url', 'success_message',
        'create_lead', 'pipeline_id', 'default_lead_source', 'is_active', 'created_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'create_lead' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(WebFormSubmission::class, 'form_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
