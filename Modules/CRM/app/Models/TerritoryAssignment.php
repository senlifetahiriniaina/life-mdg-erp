<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\TerritoryAssignmentFactory;

/**
 * @property int $id
 * @property int $territory_id
 * @property int|null $contact_id
 * @property int|null $account_id
 * @property bool $auto_assigned
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Territory $territory
 * @property-read Contact|null $contact
 * @property-read Account|null $account
 */
class TerritoryAssignment extends Model
{
    use HasFactory;

    protected $table = 'crm_territory_assignments';

    protected $fillable = [
        'territory_id',
        'contact_id',
        'account_id',
        'auto_assigned',
    ];

    protected $casts = [
        'auto_assigned' => 'boolean',
    ];

    protected static function newFactory(): TerritoryAssignmentFactory
    {
        return TerritoryAssignmentFactory::new();
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
