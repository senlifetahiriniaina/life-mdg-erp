<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EDI Transaction log
 *
 * @property int    $id
 * @property string $type        Transaction set type: 850|856|810|unknown
 * @property string $direction   inbound|outbound
 * @property string $content_raw Raw EDI string
 * @property array  $parsed_json Parsed result
 * @property string $status      received|processed|error
 * @property int|null $partner_id
 * @property \Carbon\Carbon $occurred_at
 */
class EdiTransaction extends Model
{
    use HasFactory;

    protected $table = 'edi_transactions';

    protected $fillable = [
        'type',
        'direction',
        'content_raw',
        'parsed_json',
        'status',
        'partner_id',
        'occurred_at',
        'company_id',
    ];

    protected $casts = [
        'parsed_json' => 'array',
        'occurred_at' => 'datetime',
        'partner_id'  => 'integer',
    ];
}
