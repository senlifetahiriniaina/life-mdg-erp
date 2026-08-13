<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Helpdesk\Database\Factories\TeamFactory;

class Team extends Model
{
    use HasFactory;

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    protected $table = 'hd_teams';

    protected $fillable = [
        'name',
        'email',
        'auto_assignment',
        'is_active',
    ];

    protected $casts = [
        'auto_assignment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
