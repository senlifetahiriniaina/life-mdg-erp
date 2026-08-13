<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property array<string,mixed> $data
 * @property int|null $lead_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class WebFormSubmission extends Model
{
    use HasFactory;
    protected $table = 'crm_web_form_submissions';

    protected $fillable = ['form_id', 'data', 'lead_id', 'ip_address', 'user_agent'];

    protected $casts = ['data' => 'array'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(WebForm::class, 'form_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
