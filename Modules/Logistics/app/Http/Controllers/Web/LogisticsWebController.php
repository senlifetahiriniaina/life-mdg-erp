<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Services\LogisticsAnalyticsService;

class LogisticsWebController extends Controller
{
    public function __construct(private readonly LogisticsAnalyticsService $analytics) {}

    public function index(): Response
    {
        return Inertia::render('Logistics/Dashboard', [
            'kpis' => $this->analytics->kpis(),
        ]);
    }

    public function shipments(): Response
    {
        return Inertia::render('Logistics/Shipments/Index', [
            'carriers' => Carrier::where('is_active', true)->select('id', 'name', 'type')->orderBy('name')->get(),
        ]);
    }

    public function carriers(): Response
    {
        return Inertia::render('Logistics/Carriers/Index');
    }

    public function deliveryRounds(): Response
    {
        return Inertia::render('Logistics/DeliveryRounds/Index', [
            'carriers' => Carrier::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function freightInvoices(): Response
    {
        return Inertia::render('Logistics/FreightInvoices/Index', [
            'carriers' => Carrier::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function customs(): Response
    {
        return Inertia::render('Logistics/Customs/Index');
    }

    public function analytics(): Response
    {
        return Inertia::render('Logistics/Analytics/Index', [
            'kpis' => $this->analytics->kpis(),
            'byMode' => $this->analytics->co2Emissions(),
            'carriers' => $this->analytics->carrierPerformance(),
        ]);
    }
}
