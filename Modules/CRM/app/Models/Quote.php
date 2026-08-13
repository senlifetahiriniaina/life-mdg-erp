<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Database\Factories\QuoteFactory;

/**
 * @property int $id
 * @property int|null $opportunity_id
 * @property int|null $contact_id
 * @property string $reference
 * @property string $status
 * @property Carbon|null $valid_until
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Opportunity|null $opportunity
 * @property-read Contact|null $contact
 * @property-read Collection<int, QuoteLine> $lines
 */
class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_quotes';

    protected $fillable = [
        'opportunity_id',
        'contact_id',
        'reference',
        'status',
        'valid_until',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'date',
    ];

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class, 'quote_id');
    }
}
