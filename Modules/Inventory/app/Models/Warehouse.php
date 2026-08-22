<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\WarehouseFactory;

class Warehouse extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_warehouses';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'address',
        'city',
        'country',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function newFactory()
    {
        return WarehouseFactory::new();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = strtoupper(Str::slug($model->name, ''));
            }
        });
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
