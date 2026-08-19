<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Web\AccountingWebController;
use Modules\Accounting\Http\Controllers\Web\BankReconciliationWebController;
use Modules\Accounting\Http\Controllers\Web\ConsolidationHierarchyWebController;
use Modules\Accounting\Http\Controllers\Web\ConsolidationWebController;
use Modules\Accounting\Http\Controllers\Web\InvoiceWebController;
use Modules\Accounting\Http\Controllers\Web\VatDeclarationWebController;
use Modules\Accounting\Http\Controllers\Web\AssetImpairmentWebController;
use Modules\Accounting\Http\Controllers\Web\BudgetVarianceWebController;
use Modules\Accounting\Http\Controllers\Web\CostEngineWebController;
use Modules\Accounting\Http\Controllers\Web\DepreciationPolicyWebController;
use Modules\Accounting\Http\Controllers\Web\DepreciationScheduleWebController;
use Modules\Accounting\Http\Controllers\Web\IntercompanyClearanceWebController;
use Modules\Accounting\Http\Controllers\Web\ScenarioPlanningWebController;
use Modules\Accounting\Http\Controllers\Web\TreasuryImportWebController;

Route::middleware(['auth'])->group(function () {
    // Chantier 10 fix: rendered a nonexistent 'accounting::dashboard' Blade view (this module
    // is Inertia-based, like the rest of the app — the Blade view was never built, and this
    // route is referenced from nowhere in the frontend, confirmed via a full nav grep) — every
    // visit to the bare /accounting root 500'd. Redirects to the module's real, central,
    // already-routed Invoices list instead of maintaining a dead Blade file.
    Route::get('/', fn () => redirect()->route('invoices.index'))->name('dashboard');

    Route::get('invoices', [InvoiceWebController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceWebController::class, 'create'])->name('invoices.create');
    Route::get('invoices/approvals', [InvoiceWebController::class, 'approvalQueue'])->name('invoices.approvals.index');
    Route::get('invoices/{invoice}/approval', [InvoiceWebController::class, 'showApproval'])->name('invoices.approval.show');
    Route::get('invoices/{invoice}/edit', [InvoiceWebController::class, 'edit'])->name('invoices.edit');
    Route::get('invoices/{invoice}', [InvoiceWebController::class, 'show'])->name('invoices.show');

    Route::get('chart-of-accounts', [AccountingWebController::class, 'chartOfAccounts'])->name('chart-of-accounts.index');
    Route::get('expenses', [AccountingWebController::class, 'expenses'])->name('expenses.index');

    Route::get('bank-reconciliation', [BankReconciliationWebController::class, 'index'])->name('bank-reconciliation.index');
    Route::get('bank-reconciliation/{account}', [BankReconciliationWebController::class, 'reconcile'])->name('bank-reconciliation.reconcile');

    Route::get('vat-declarations', [VatDeclarationWebController::class, 'index'])->name('vat-declarations.index');
    Route::get('vat-declarations/{vatDeclaration}', [VatDeclarationWebController::class, 'show'])->name('vat-declarations.show');

    Route::get('consolidations', [ConsolidationWebController::class, 'index'])->name('consolidations.index');
    Route::get('consolidations/create', [ConsolidationWebController::class, 'create'])->name('consolidations.create');
    Route::get('consolidations/{company}', [ConsolidationWebController::class, 'show'])->name('consolidations.show');

    Route::get('consolidation-hierarchies', [ConsolidationHierarchyWebController::class, 'index'])->name('consolidation-hierarchies.index');

    Route::get('ai-anomaly-detection', [AccountingWebController::class, 'aiAnomalyDetection'])->name('ai-anomaly-detection.index');

    // Chantier 8.1b — previously orphaned Accounting controllers, now wired
    Route::get('asset-impairments', [AssetImpairmentWebController::class, 'index'])->name('asset-impairments.index');
    Route::get('budget-variance', [BudgetVarianceWebController::class, 'index'])->name('budget-variance.index');
    Route::get('cost-engine', [CostEngineWebController::class, 'index'])->name('cost-engine.index');
    Route::get('depreciation-policies', [DepreciationPolicyWebController::class, 'index'])->name('depreciation-policies.index');
    Route::get('depreciation-schedules', [DepreciationScheduleWebController::class, 'index'])->name('depreciation-schedules.index');
    Route::get('intercompany-clearances', [IntercompanyClearanceWebController::class, 'index'])->name('intercompany-clearances.index');
    Route::get('scenario-planning', [ScenarioPlanningWebController::class, 'index'])->name('scenario-planning.index');
    Route::get('treasury-import', [TreasuryImportWebController::class, 'index'])->name('treasury-import.index');
});
