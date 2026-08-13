<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BI\Database\Factories\DashboardFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $layout
 * @property bool $is_public
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, Widget> $widgets
 */
class Dashboard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bi_dashboards';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'layout',
        'is_public',
        'is_default',
    ];

    protected $casts = [
        'layout' => 'array',
        'is_public' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function newFactory(): DashboardFactory
    {
        return DashboardFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }
}
