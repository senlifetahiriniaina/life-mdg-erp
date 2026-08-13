<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $ticket_id
 * @property string $detected_language
 * @property float  $confidence
 * @property string $original_text_language
 * @property string $supported_language
 * @property bool   $requires_translation
 * @property string $translation_provider
 * @property string $translated_text
 * @property string $translation_status
 * @property float  $translation_confidence
 * @property array  $language_alternatives
 * @property string $detection_notes
 * @property \Illuminate\Support\Carbon|null $detected_at
 * @property \Illuminate\Support\Carbon|null $translated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LanguageDetection extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_language_detection';

    protected $fillable = [
        'ticket_id',
        'detected_language',
        'confidence',
        'original_text_language',
        'supported_language',
        'requires_translation',
        'translation_provider',
        'translated_text',
        'translation_status',
        'translation_confidence',
        'language_alternatives',
        'detection_notes',
        'detected_at',
        'translated_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'translation_confidence' => 'decimal:4',
        'requires_translation' => 'boolean',
        'language_alternatives' => 'json',
        'detected_at' => 'datetime',
        'translated_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isSupported(): bool
    {
        return $this->detected_language === $this->supported_language;
    }

    public function needsTranslation(): bool
    {
        return $this->requires_translation && $this->translation_status !== 'completed';
    }

    public function markTranslated(): void
    {
        $this->update([
            'translation_status' => 'completed',
            'translated_at' => now(),
        ]);
    }

    public function hasHighConfidence(float $threshold = 0.8): bool
    {
        return $this->confidence >= $threshold;
    }
}
