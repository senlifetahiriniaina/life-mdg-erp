<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string $nature 'encaissement'|'decaissement'
 * @property string $counterpart_account_code
 * @property array<int, string>|null $keywords
 * @property bool $is_active
 * @property int|null $company_id
 */
class OperationTemplate extends Model
{
    protected $table = 'acc_operation_templates';

    protected $fillable = [
        'code',
        'label',
        'nature',
        'counterpart_account_code',
        'keywords',
        'is_active',
        'company_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForNature($query, string $nature)
    {
        return $query->where('nature', $nature);
    }

    public function isEncaissement(): bool
    {
        return $this->nature === 'encaissement';
    }
}
