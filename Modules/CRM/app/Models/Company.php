<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Database\Factories\CompanyFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $website
 * @property string|null $industry
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_companies';

    protected $fillable = [
        'name',
        'website',
        'industry',
        'phone',
        'email',
        'notes',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'company_id');
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
