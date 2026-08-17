<?php

declare(strict_types=1);

namespace Modules\Validation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ValidationRuleSet extends Model
{
    protected $table = 'validation_rule_sets';

    protected $fillable = [
        'name',
        'description',
        'version',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(ValidationRule::class, 'validation_rule_set_rule', 'rule_set_id', 'rule_id');
    }

    public function addRule(ValidationRule $rule): void
    {
        $this->rules()->syncWithoutDetaching([$rule->id]);
        $this->increment('version');
    }
}
