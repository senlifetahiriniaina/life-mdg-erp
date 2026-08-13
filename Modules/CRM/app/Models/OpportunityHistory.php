<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int|null $changed_by
 * @property Carbon $changed_at
 */
class OpportunityHistory extends Model
{
    use HasFactory;
    protected $table = 'crm_opportunity_history';

    protected $fillable = ['opportunity_id', 'field', 'old_value', 'new_value', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
