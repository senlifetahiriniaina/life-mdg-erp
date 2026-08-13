<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Database\Factories\TaxSettingFactory;
use Modules\Accounting\Traits\CalculatesTax;

class TaxSetting extends Model
{
    use CalculatesTax, HasFactory;

    protected $table = 'acc_tax_settings';

    protected $fillable = [
        'tax_name',
        'tax_rate',
        'tax_type',
        'gl_account_id',
        'status',
        'description',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return TaxSettingFactory::new();
    }

    public function glAccount()
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }

    protected function getTaxRate(): float
    {
        return (float) $this->tax_rate;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
