<?php
declare(strict_types=1);
namespace App\Models;

use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'url', 'secret', 'events', 'is_active', 'description'];
    protected $casts    = ['events' => 'array', 'is_active' => 'boolean'];

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deliveries(): HasMany { return $this->hasMany(WebhookDelivery::class); }
}
