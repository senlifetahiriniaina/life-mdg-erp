<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\BI\Http\Resources\KpiResource;
use Modules\BI\Models\BiAlert;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Models\BiQuery;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Kpi;
use Modules\BI\Models\Report;

class BiWebController extends Controller
{
    public function index(Request $request): Response
    {
        $kpis = Kpi::all();

        $dashboards = Dashboard::withCount('widgets')
            ->where(function ($q) use ($request): void {
                $q->where('user_id', $request->user()?->id)->orWhere('is_public', true);
            })
            ->latest()
            ->get();

        $recentReports = Report::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('BI/Index', [
            'kpis' => KpiResource::collection($kpis)->resolve(),
            'dashboards' => $dashboards,
            'recentReports' => $recentReports,
        ]);
    }

    public function analytics(Request $request): Response
    {
        return Inertia::render('BI/Analytics');
    }

    public function kpisPage(Request $request): Response
    {
        $kpis = Kpi::latest()->get();

        return Inertia::render('BI/Kpis', [
            'kpis' => KpiResource::collection($kpis)->resolve(),
            'period' => 'month',
        ]);
    }

    public function nlQuery(Request $request): Response
    {
        return Inertia::render('BI/NlQuery');
    }

    public function visualizations(Request $request): Response
    {
        return Inertia::render('BI/Visualizations/Index');
    }

    public function aiNarratives(Request $request): Response
    {
        return Inertia::render('BI/AINarratives/Index');
    }

    public function predictiveAnalytics(Request $request): Response
    {
        return Inertia::render('BI/PredictiveAnalytics/Index');
    }

    public function dashboardShow(Dashboard $dashboard): Response
    {
        $dashboard->load('widgets');

        return Inertia::render('BI/Dashboard', [
            'dashboard' => $dashboard,
            'widgets' => $dashboard->widgets,
        ]);
    }

    public function builder(Request $request, ?Dashboard $dashboard = null): Response
    {
        $dashboard?->load('widgets');

        return Inertia::render('BI/Builder', [
            'dashboardId' => $dashboard?->id,
            'dashboardName' => $dashboard?->name ?? '',
            'existingWidgets' => $dashboard?->widgets ?? [],
            'dataSources' => BiDataSource::latest()->limit(20)->get(['id', 'name', 'type']),
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
