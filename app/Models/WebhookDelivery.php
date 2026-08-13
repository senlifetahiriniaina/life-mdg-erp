<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_id', 'event', 'payload', 'http_status', 'response_body', 'success', 'attempt'];
    protected $casts    = ['payload' => 'array', 'success' => 'boolean'];

    public function webhook(): BelongsTo { return $this->belongsTo(Webhook::class); }
}
