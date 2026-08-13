<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\BI\Models\BiAlert;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Models\BiQuery;
use Modules\BI\Models\Kpi;
use Modules\BI\Models\Report;

class BiWebController extends Controller
{
    public function index(Request $request): Response
    {
        $kpis = Kpi::all();

        $recentReports = Report::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('BI/Index', [
            'kpis' => $kpis,
            'recentReports' => $recentReports,
        ]);
    }

    public function reports(Request $request): Response
    {
        $reports = Report::query()
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('BI/Reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function sqlEditor(Request $request): Response
    {
        $queries = BiQuery::where(function ($q) use ($request): void {
            $q->where('created_by', $request->user()?->id)
                ->orWhere('is_public', true);
        })
            ->latest()
            ->get(['id', 'name', 'description', 'datasource', 'is_public', 'last_run_at']);

        return Inertia::render('BI/SqlEditor', [
            'savedQueries' => $queries,
        ]);
    }

    public function alerts(Request $request): Response
    {
        $alerts = BiAlert::with(['widget', 'biQuery'])
            ->latest()
            ->paginate(25);

        return Inertia::render('BI/Alerts/Index', [
            'alerts' => $alerts,
        ]);
    }

    public function dataSources(Request $request): Response
    {
        $sources = BiDataSource::where('created_by', $request->user()?->id)
            ->latest()
            ->get();

        return Inertia::render('BI/DataSources/Index', [
            'dataSources' => $sources,
        ]);
    }
}
