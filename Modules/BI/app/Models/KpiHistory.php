<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiHistory extends Model
{
    use HasFactory;
    protected $table = 'bi_kpi_history';

    protected $fillable = [
        'kpi_id',
        'value',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }
}
