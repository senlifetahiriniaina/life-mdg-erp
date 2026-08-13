<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Rma;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Warehouse;

class InventoryWebController extends Controller
{
    public function suppliers(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%")
            )
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventory/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function purchaseOrders(Request $request): Response
    {
        $orders = PurchaseOrder::with(['supplier:id,name', 'warehouse:id,name'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $suppliers = Supplier::select('id', 'name')->where('is_active', true)->get();
        $warehouses = Warehouse::select('id', 'name')->where('is_active', true)->get();

        return Inertia::render('Inventory/PurchaseOrders/Index', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'filters' => $request->only(['status', 'supplier_id']),
        ]);
    }

    public function picking(Request $request): Response
    {
        $orders = PickingOrder::with(['warehouse:id,name', 'assignee:id,name'])
            ->withCount('lines')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderBy('priority')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->where('is_active', true)->get();

        return Inertia::render('Inventory/WMS/Picking', [
            'orders' => $orders,
            'warehouses' => $warehouses,
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function cycleCounts(Request $request): Response
    {
        $counts = CycleCount::with(['warehouse:id,name', 'assignee:id,name'])
            ->withCount('lines')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->where('is_active', true)->get();

        return Inertia::render('Inventory/CycleCounts/Index', [
            'counts' => $counts,
            'warehouses' => $warehouses,
            'filters' => $request->only(['status']),
        ]);
    }

    public function shipments(Request $request): Response
    {
        $shipments = Shipment::query()
            ->with(['carrier:id,name', 'warehouse:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Shipment::count(),
            'pending' => Shipment::where('status', 'pending')->count(),
            'shipped' => Shipment::where('status', 'shipped')->count(),
            'delivered' => Shipment::where('status', 'delivered')->count(),
        ];

        return Inertia::render('Inventory/Shipments/Index', [
            'shipments' => $shipments,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }

    public function returns(Request $request): Response
    {
        $returns = Rma::query()
            ->with(['supplier:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Rma::count(),
            'pending' => Rma::where('status', 'pending')->count(),
            'approved' => Rma::where('status', 'approved')->count(),
        ];

        return Inertia::render('Inventory/Returns/Index', [
            'returns' => $returns,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }

    public function crossdock(Request $request): Response
    {
        $operations = CrossdockOperation::query()
            ->with(['sourceWarehouse:id,name', 'destinationWarehouse:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventory/WMS/Crossdock/Index', [
            'operations' => $operations,
            'filters' => $request->only(['status']),
        ]);
    }

    public function waves(Request $request): Response
    {
        $waves = PickingWave::query()
            ->with(['warehouse:id,name'])
            ->withCount('pickingOrders')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => PickingWave::count(),
            'pending' => PickingWave::where('status', 'pending')->count(),
            'in_picking' => PickingWave::where('status', 'in_picking')->count(),
        ];

        return Inertia::render('Inventory/WMS/Waves/Index', [
            'waves' => $waves,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }
}
