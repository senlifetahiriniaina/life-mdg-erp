<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Inventory\Database\Factories\CategoryFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
    ];

    protected static function newFactory()
    {
        return CategoryFactory::new();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
