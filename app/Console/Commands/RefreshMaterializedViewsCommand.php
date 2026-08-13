<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MaterializedViewService;
use Illuminate\Console\Command;

class RefreshMaterializedViewsCommand extends Command
{
    protected $signature = 'materialized-views:refresh {--view=}';

    protected $description = 'Refresh materialized views for analytics and reporting';

    public function __construct(private MaterializedViewService $viewService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $view = $this->option('view');

        if ($view) {
            $this->info("Refreshing materialized view: $view");
            $this->viewService->refresh($view);
            $this->info("✓ View refreshed successfully");
        } else {
            $this->info('Refreshing all materialized views...');
            $this->viewService->refreshAll();
            $this->info('✓ All views refreshed successfully');
        }

        return self::SUCCESS;
    }
}
