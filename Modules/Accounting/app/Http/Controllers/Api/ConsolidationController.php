<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\ConsolidationReport;
use Modules\Accounting\Models\IntercompanyTransaction;
use Modules\Accounting\Services\ConsolidationService;

/**
 * @group Accounting - Multi-company Consolidation
 *
 * Company hierarchy management, intercompany transactions, and consolidated reporting.
 */
class ConsolidationController extends Controller
{
    public function __construct(private readonly ConsolidationService $service) {}

    // ─── Companies ─────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->when($request->company_type, fn ($q, $t) => $q->where('company_type', $t))
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->with('parent')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:acc_companies,code',
            'parent_company_id' => 'nullable|exists:acc_companies,id',
            'company_type' => 'in:parent,subsidiary,associate',
            'ownership_percentage' => 'numeric|min:0|max:100',
            'currency' => 'string|size:3',
            'fiscal_year_start_month' => 'integer|min:1|max:12',
            'is_active' => 'boolean',
            'elimination_account_id' => 'nullable|integer',
        ]);

        $company = Company::create($validated);

        return response()->json($company, 201);
    }

    public function show(Company $company): JsonResponse
    {
        $company->load(['parent', 'subsidiaries']);

        return response()->json($company);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:acc_companies,code,'.$company->id,
            'parent_company_id' => 'nullable|exists:acc_companies,id',
            'company_type' => 'in:parent,subsidiary,associate',
            'ownership_percentage' => 'numeric|min:0|max:100',
            'currency' => 'string|size:3',
            'fiscal_year_start_month' => 'integer|min:1|max:12',
            'is_active' => 'boolean',
            'elimination_account_id' => 'nullable|integer',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    public function generateReport(Request $request, Company $company): JsonResponse
    {
        $this->authorize('generateReport', $company);

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $report = $this->service->generateReport(
            $company,
            Carbon::parse($validated['period_start']),
            Carbon::parse($validated['period_end'])
        );

        return response()->json($report, 201);
    }

    public function listSubsidiaries(Company $company): JsonResponse
    {
        $subsidiaries = $company->allSubsidiaries();

        return response()->json([
            'parent' => $company,
            'subsidiaries' => $subsidiaries,
            'total' => $subsidiaries->count(),
        ]);
    }

    public function groupSummary(Company $company): JsonResponse
    {
        $summary = $this->service->getGroupSummary($company);

        return response()->json([
            'parent_company' => $company,
            'group_summary' => $summary,
        ]);
    }

    // ─── Intercompany Transactions ──────────────────────────────────────────────

    public function recordTransaction(Request $request): JsonResponse
    {
        $this->authorize('recordTransaction', Company::class);

        $validated = $request->validate([
            'from_company_id' => 'required|exists:acc_companies,id',
            'to_company_id' => 'required|exists:acc_companies,id|different:from_company_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'transaction_date' => 'nullable|date',
        ]);

        $from = Company::findOrFail($validated['from_company_id']);
        $to = Company::findOrFail($validated['to_company_id']);
        $date = isset($validated['transaction_date'])
            ? Carbon::parse($validated['transaction_date'])
            : null;

        $txn = $this->service->recordIntercompanyTransaction(
            $from,
            $to,
            (float) $validated['amount'],
            $validated['description'] ?? '',
            $date
        );

        $txn->load(['fromCompany', 'toCompany']);

        return response()->json($txn, 201);
    }

    public function listTransactions(Request $request): JsonResponse
    {
        $transactions = IntercompanyTransaction::query()
            ->when($request->from_company_id, fn ($q, $id) => $q->where('from_company_id', $id))
            ->when($request->to_company_id, fn ($q, $id) => $q->where('to_company_id', $id))
            ->when($request->has('is_eliminated'), fn ($q) => $q->where('is_eliminated', $request->boolean('is_eliminated')))
            ->with(['fromCompany', 'toCompany'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(15);

        return response()->json($transactions);
    }

    public function eliminateAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_company_id' => 'required|exists:acc_companies,id',
        ]);

        $parent = Company::findOrFail($validated['parent_company_id']);
        $this->authorize('eliminateIntercompany', $parent);

        $count = $this->service->eliminateIntercompany($parent);

        return response()->json([
            'message' => "Eliminated {$count} intercompany transaction(s).",
            'count' => $count,
        ]);
    }

    // ─── Consolidation Reports ──────────────────────────────────────────────────

    public function listReports(Request $request): JsonResponse
    {
        $reports = ConsolidationReport::query()
            ->when($request->parent_company_id, fn ($q, $id) => $q->where('parent_company_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with('parentCompany')
            ->orderBy('report_date', 'desc')
            ->paginate(15);

        return response()->json($reports);
    }

    public function showReport(ConsolidationReport $report): JsonResponse
    {
        $report->load('parentCompany');

        return response()->json(array_merge($report->toArray(), [
            'net_income_margin' => $report->netIncomeMargin(),
            'debt_to_asset_ratio' => $report->debtToAssetRatio(),
            'consolidated_equity' => $report->consolidatedEquity(),
        ]));
    }
}
