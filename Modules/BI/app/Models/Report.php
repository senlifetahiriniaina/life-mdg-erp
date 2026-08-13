<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BI\Database\Factories\ReportFactory;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bi_reports';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'type',
        'query_config',
        'chart_config',
        'filters',
        'is_scheduled',
        'schedule',
        'schedule_recipients',
        'last_run_at',
    ];

    protected $casts = [
        'query_config' => 'array',
        'chart_config' => 'array',
        'filters' => 'array',
        'schedule_recipients' => 'array',
        'is_scheduled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
