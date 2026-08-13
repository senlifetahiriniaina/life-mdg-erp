<?php

declare(strict_types=1);

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $table = 'shared_languages';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'rtl',
        'active',
        'region',
        'flag_emoji',
        'locale_code',
    ];

    protected $casts = [
        'rtl'    => 'boolean',
        'active' => 'boolean',
    ];

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeRtl($query)
    {
        return $query->where('rtl', true);
    }

    public function scopeLtr($query)
    {
        return $query->where('rtl', false);
    }

    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    // --- Helpers ---

    public function isRtl(): bool
    {
        return (bool) $this->rtl;
    }

    public function getTextDirection(): string
    {
        return $this->rtl ? 'rtl' : 'ltr';
    }
}
