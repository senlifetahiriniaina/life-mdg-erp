<?php

namespace Modules\AI\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * AI's routes/api.php declares its own `v1/ai` prefix internally (same convention
 * as Analytics/HR/etc.) but relied on AIServiceProvider::boot() calling
 * loadRoutesFrom() directly, with no surrounding `api` middleware group/prefix.
 * That registered every AI endpoint at `/v1/ai/...` instead of the documented
 * `/api/v1/ai/...` (see routes/api.php's own docblock, CLAUDE.md's AI Assisted
 * First section, and every controller's own `POST /api/v1/ai/...` docblock) —
 * every real AI endpoint (assist, anomalies, search, advise, admin) 404'd at its
 * documented path. Never caught by tests because every existing AI test exercises
 * the underlying services directly, none go through HTTP. Same class of bug
 * already fixed for Analytics/Security (see Modules/Analytics's RouteServiceProvider
 * docblock) — fixed the same way here: a dedicated RouteServiceProvider wrapping
 * the module's routes in the `api` middleware group + `api` prefix.
 */
class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'AI';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
