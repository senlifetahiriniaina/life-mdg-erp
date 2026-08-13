<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\ChartOfAccountFactory;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $description
 * @property int|null $parent_id
 * @property bool $is_active
 */
class ChartOfAccount extends Model
{
    use HasFactory, RecordsActivity;

    protected static string $auditModule = 'Accounting';

    protected static function newFactory(): ChartOfAccountFactory
    {
        return ChartOfAccountFactory::new();
    }

    protected $table = 'acc_chart_of_accounts';

    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'description', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }
}
