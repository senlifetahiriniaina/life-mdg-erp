<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Accounting\Models\Invoice;
use Modules\BI\Models\BiQuery;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;
use Modules\BI\Services\ExportService;
use Modules\BI\Services\QueryRunnerService;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\HR\Models\Employee;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group BI - Exports
 *
 * Download reports as XLSX, CSV or PDF.
 */
class ExportController extends Controller
{
    private const DATASETS = ['invoices', 'leads', 'opportunities', 'employees', 'products', 'stock_movements'];

    /** Datasets that require admin / manager / hr-manager role */
    private const RESTRICTED = ['employees'];

    public function __construct(private readonly ExportService $export) {}

    public function download(Request $request, string $dataset): BinaryFileResponse|Response
    {
        abort_unless(in_array($dataset, self::DATASETS, true), 404, "Unknown dataset: {$dataset}");

        if (in_array($dataset, self::RESTRICTED, true)) {
            $user = $request->user();
            abort_unless(
                $user && ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('hr-manager')),
                403,
                'You do not have permission to export this dataset.'
            );
        }

        $format = $request->input('format', 'xlsx');
        $filters = $request->only(['from', 'to', 'status', 'limit']);

        [$data, $headings] = $this->buildDataset($dataset, $filters);

        $filename = "{$dataset}_".now()->format('Ymd_His');

        return match ($format) {
            'csv' => $this->export->toCsv($data, $headings, $filename),
            'pdf' => $this->export->toPdf("bi::exports.{$dataset}", ['rows' => $data, 'headings' => $headings], $filename),
            default => $this->export->toXlsx($data, $headings, $filename),
        };
    }

    private function buildDataset(string $dataset, array $filters): array
    {
        $limit = min((int) ($filters['limit'] ?? 1000), 5000);

        return match ($dataset) {
            'invoices' => $this->invoicesDataset($filters, $limit),
            'leads' => $this->leadsDataset($filters, $limit),
            'opportunities' => $this->opportunitiesDataset($filters, $limit),
            'employees' => $this->employeesDataset($limit),
            'products' => $this->productsDataset($limit),
            'stock_movements' => $this->stockMovementsDataset($filters, $limit),
            default => [collect(), []],
        };
    }

    private function invoicesDataset(array $filters, int $limit): array
    {
        $rows = Invoice::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('invoice_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('invoice_date', '<=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('invoice_date')
            ->limit($limit)
            ->get(['number', 'type', 'partner_name', 'invoice_date', 'due_date', 'total', 'status', 'currency']);

        return [$rows->map(fn ($r) => (array) $r->toArray()), ['Number', 'Type', 'Partner', 'Date', 'Due', 'Total', 'Status', 'Currency']];
    }

    private function leadsDataset(array $filters, int $limit): array
    {
        $rows = Lead::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['title', 'status', 'source', 'estimated_value', 'currency', 'score', 'created_at']);

        $headings = ['Title', 'Status', 'Source', 'Value', 'Currency', 'Score', 'Created'];

        return [$rows->map(fn ($r) => array_values($r->only(['title', 'status', 'source', 'estimated_value', 'currency', 'score', 'created_at']))), $headings];
    }

    private function opportunitiesDataset(array $filters, int $limit): array
    {
        $rows = Opportunity::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['name', 'status', 'amount', 'currency', 'expected_close_date', 'created_at']);

        $headings = ['Name', 'Status', 'Amount', 'Currency', 'Expected Close', 'Created'];

        return [$rows->map(fn ($r) => array_values($r->only(['name', 'status', 'amount', 'currency', 'expected_close_date', 'created_at']))), $headings];
    }

    private function employeesDataset(int $limit): array
    {
        $rows = Employee::with('department:id,name', 'jobPosition:id,title')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'job_position_id', 'department_id', 'status', 'hire_date']);

        $headings = ['First Name', 'Last Name', 'Email', 'Job Title', 'Department', 'Status', 'Hire Date'];

        return [
            $rows->map(fn ($r) => [
                $r->first_name, $r->last_name, $r->email, $r->jobPosition?->title,
                $r->department?->name, $r->status, $r->hire_date?->toDateString(),
            ]),
            $headings,
        ];
    }

    private function productsDataset(int $limit): array
    {
        $rows = Product::with('category:id,name')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'category_id', 'sale_price', 'is_active']);

        $headings = ['Name', 'SKU', 'Category', 'Price', 'Active'];

        return [
            $rows->map(fn ($r) => [
                $r->name, $r->sku, $r->category?->name, $r->sale_price, $r->is_active ? 'Yes' : 'No',
            ]),
            $headings,
        ];
    }

    private function stockMovementsDataset(array $filters, int $limit): array
    {
        $rows = StockMovement::with('product:id,name', 'warehouse:id,name')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $headings = ['Product', 'Warehouse', 'Type', 'Quantity', 'Reference', 'Date'];

        return [
            $rows->map(fn ($r) => [
                $r->product?->name, $r->warehouse?->name, $r->type, $r->quantity, $r->reference, $r->created_at?->toDateString(),
            ]),
            $headings,
        ];
    }

    // -------------------------------------------------------------------------
    // New BI-object export endpoints
    // -------------------------------------------------------------------------

    /**
     * Export a dashboard as PDF.
     */
    public function exportDashboard(Request $request, Dashboard $dashboard): Response|BinaryFileResponse
    {
        $format = $request->query('format', 'pdf');

        return match ($format) {
            'pdf' => $this->export->exportDashboardPdf($dashboard),
            default => $this->export->exportDashboardPdf($dashboard),
        };
    }

    /**
     * Export a widget as CSV or PDF.
     */
    public function exportWidget(Request $request, Widget $widget): StreamedResponse|Response|BinaryFileResponse
    {
        $format = $request->query('format', 'csv');

        return match ($format) {
            'pdf' => $this->export->exportWidgetPdf($widget),
            default => $this->export->exportWidgetCsv($widget),
        };
    }

    /**
     * Export a saved query as CSV or XLSX.
     */
    public function exportQuery(Request $request, BiQuery $query): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        /** @var QueryRunnerService $runner */
        $runner = app(QueryRunnerService::class);
        $format = $request->query('format', 'csv');

        try {
            $result = $runner->runQuery($query);
            $filename = 'query_'.$query->id;

            return match ($format) {
                'xlsx' => $this->export->exportQueryXlsx($result, $filename),
                default => $this->export->exportQueryCsv($result, $filename),
            };
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Export failed: '.$e->getMessage()], 500);
        }
    }
}
