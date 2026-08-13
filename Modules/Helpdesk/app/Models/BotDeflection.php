<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Helpdesk\Database\Factories\BotDeflectionFactory;

/**
 * @property int $id
 * @property string $question
 * @property int|null $matched_article_id
 * @property bool $deflected
 * @property bool $ticket_created
 * @property string|null $session_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BotDeflection extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_bot_deflections';

    protected $fillable = [
        'question',
        'matched_article_id',
        'deflected',
        'ticket_created',
        'session_id',
    ];

    protected $casts = [
        'deflected' => 'boolean',
        'ticket_created' => 'boolean',
    ];

    protected static function newFactory(): BotDeflectionFactory
    {
        return BotDeflectionFactory::new();
    }
}
