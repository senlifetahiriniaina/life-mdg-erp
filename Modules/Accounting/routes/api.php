<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Api\BudgetController;
use Modules\Accounting\Http\Controllers\Api\BudgetManagementController;
use Modules\Accounting\Http\Controllers\Api\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\Api\ExpenseController;
use Modules\Accounting\Http\Controllers\Api\FinancialRatiosController;
use Modules\Accounting\Http\Controllers\Api\GLAccountController;
use Modules\Accounting\Http\Controllers\Api\InvoiceApprovalController;
use Modules\Accounting\Http\Controllers\Api\InvoiceController;
use Modules\Accounting\Http\Controllers\Api\InvoiceExportController;
use Modules\Accounting\Http\Controllers\Api\JournalController;
use Modules\Accounting\Http\Controllers\Api\ReportController;
use Modules\Accounting\Http\Controllers\Api\ReportingController;
use Modules\Accounting\Http\Controllers\TaxComplianceReportController;
use Modules\Accounting\Http\Controllers\Api\TaxController;
use Modules\Accounting\Http\Controllers\Api\TaxRateController;
use Modules\Accounting\Http\Controllers\Api\TaxCategoriesController;
use Modules\Accounting\Http\Controllers\Api\TaxRulesController;
use Modules\Accounting\Http\Controllers\Api\VatDeclarationController;
use Modules\Accounting\Http\Controllers\Api\TaxCalculationController;
use Modules\Accounting\Http\Controllers\Api\TaxComplianceController;
use Modules\Accounting\Http\Controllers\Api\ReconciliationController;
use Modules\Accounting\Http\Controllers\Api\ConsolidationController;
use Modules\Accounting\Http\Controllers\Api\ExpensesController;
use Modules\Accounting\Http\Controllers\Api\AdvancedAccountingController;
use Modules\Accounting\Http\Controllers\Api\PerformanceOptimizationController;
use Modules\Accounting\Http\Controllers\Api\BankAccountController;
use Modules\Accounting\Http\Controllers\Api\BankTransactionController;
use Modules\Accounting\Http\Controllers\Api\ExchangeRateController;
use Modules\Accounting\Http\Controllers\Api\SmartCategorizationController;
use Modules\Accounting\Http\Controllers\Api\ExpenseReportController;
use Modules\Accounting\Http\Controllers\Api\TreasuryController;
use Modules\Accounting\Http\Controllers\Api\FixedAssetController;
use Modules\Accounting\Http\Controllers\Api\BankReconciliationController;
use Modules\Accounting\Http\Controllers\Api\ReconciliationSessionController;
use Modules\Accounting\Http\Controllers\Api\TreasuryPlanningController;
use Modules\Accounting\Http\Controllers\Api\AccOpenBankingController;
use Modules\Accounting\Http\Controllers\Api\VatRateController;
use Modules\Accounting\Http\Controllers\Api\AccountingAIController;
use Modules\Accounting\Http\Controllers\Api\JournalEntryApiController;

// Webhooks (no auth required, signature validation only, rate limited)
Route::middleware('throttle:webhook')->post('open-banking/webhook', function (\Modules\Accounting\Http\Requests\HandleOpenBankingWebhookRequest $request) {
    $provider = $request->input('provider');
    return response()->json(['status' => 'received', 'provider' => $provider], 200);
});

// Simple GET endpoints (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:accountant,finance-manager,manager,admin', 'throttle:simple_get'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/summary', [InvoiceController::class, 'summary']);
    Route::get('invoices/aged-receivables', [InvoiceController::class, 'agedReceivables']);
    Route::get('invoices/outstanding', [InvoiceController::class, 'outstanding']);
    Route::get('invoices/overdue', [InvoiceController::class, 'overdue']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('invoices/{invoice}/pdf', [InvoiceExportController::class, 'pdf']);
    Route::get('invoices/export/excel', [InvoiceExportController::class, 'excel']);
    Route::get('invoices/approvals/pending', [InvoiceApprovalController::class, 'pending']);
    Route::get('invoices/{invoice}/approvals', [InvoiceApprovalController::class, 'index']);
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::get('expenses/pending', [ExpenseController::class, 'pending']);
    Route::get('expenses/by-category/{category}', [ExpenseController::class, 'byCategory']);
    Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
    Route::get('gl-accounts', [GLAccountController::class, 'index']);
    Route::get('gl-accounts/{glAccount}', [GLAccountController::class, 'show']);
    Route::get('journals', [JournalController::class, 'index']);
    Route::get('journals/{journal}', [JournalController::class, 'show']);
    Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index']);
    Route::get('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'show']);
    Route::get('budgets', [BudgetController::class, 'index']);
    Route::get('tax-rates', [TaxRateController::class, 'index']);
    Route::get('tax-rates/active', [TaxRateController::class, 'active']);
    Route::get('tax-rates/{taxRate}', [TaxRateController::class, 'show']);
    Route::get('tax', [TaxController::class, 'index']);
    Route::get('tax/entries', [TaxController::class, 'entries']);
    Route::get('tax/liability', [TaxController::class, 'liability']);
    Route::get('reconciliations', [ReconciliationController::class, 'index']);
    Route::get('reconciliations/{reconciliation}', [ReconciliationController::class, 'show']);
    Route::get('consolidations', [ConsolidationController::class, 'index']);
    Route::get('consolidations/{company}', [ConsolidationController::class, 'show']);
    Route::get('consolidations/{company}/subsidiaries', [ConsolidationController::class, 'listSubsidiaries']);
    Route::get('consolidations/{company}/summary', [ConsolidationController::class, 'groupSummary']);
    Route::get('consolidation-reports', [ConsolidationController::class, 'listReports']);
    Route::get('consolidation-reports/{report}', [ConsolidationController::class, 'showReport']);
    Route::get('intercompany-transactions', [ConsolidationController::class, 'listTransactions']);
    Route::get('expenses', [ExpensesController::class, 'index']);
    Route::get('expenses/pending', [ExpensesController::class, 'pending']);
    Route::get('expenses/category/{category}', [ExpensesController::class, 'byCategory']);
    Route::get('expenses/analytics', [ExpensesController::class, 'analytics']);
    Route::get('expenses/{expense}', [ExpensesController::class, 'show']);
    Route::get('expense-reports/{report}/analytics', [ExpensesController::class, 'reportAnalytics']);
});

// Self-service expense reports ("Mes notes de frais") — any authenticated
// employee manages their own reports here, not just accountant/finance-manager/
// manager/admin. Approval/reimbursement stay role-gated further below.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::get('expense-reports', [ExpensesController::class, 'listReports']);
    Route::get('expense-reports/{report}', [ExpensesController::class, 'showReport']);
    Route::post('expense-reports', [ExpensesController::class, 'createReport']);
    Route::post('expenses/{report}/lines', [ExpenseReportController::class, 'addLine']);
    Route::post('expenses/{report}/mileage', [ExpenseReportController::class, 'addMileage']);
    Route::post('expenses/{report}/submit', [ExpenseReportController::class, 'submit']);
    Route::post('smart-categorize', [SmartCategorizationController::class, 'categorize']);
});

// Complex GET endpoints with calculations (400 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:accountant,finance-manager,manager,admin', 'throttle:complex_get'])->group(function () {
    Route::get('invoices/outstanding', [InvoiceController::class, 'outstanding']);
    Route::get('invoices/overdue', [InvoiceController::class, 'overdue']);
    Route::get('expenses/pending', [ExpenseController::class, 'pending']);
    Route::get('expenses/by-category/{category}', [ExpenseController::class, 'byCategory']);
    Route::get('budgets/over-budget', [BudgetController::class, 'overBudget']);
    Route::get('budgets/{budget}/variance', [BudgetManagementController::class, 'variance']);
    Route::get('budgets/{budget}/variance-trend', [BudgetManagementController::class, 'varianceTrend']);
    Route::get('budgets/{budget}/monthly-comparison', [BudgetManagementController::class, 'monthlyComparison']);
    Route::get('budget-lines/{budgetLine}/variance', [BudgetManagementController::class, 'lineVariance']);
    Route::get('budget-lines/{budgetLine}/forecast', [BudgetManagementController::class, 'forecast']);
    Route::get('tax/calculate', [TaxController::class, 'calculate']);
});

// Expensive operations - reporting (10 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:accountant,finance-manager,manager,admin', 'throttle:expensive'])->group(function () {
    Route::get('reports/metrics', [ReportController::class, 'financialMetrics']);
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow']);
    Route::get('matching', [ReportController::class, 'ledgerMatching']);
    Route::post('matching/match', [ReportController::class, 'ledgerMatch']);
    Route::post('matching/unmatch', [ReportController::class, 'ledgerUnmatch']);
    Route::get('tax/report', [TaxController::class, 'report']);

    // Financial Reports
    Route::post('financial-reports/income-statement', [ReportingController::class, 'incomeStatement']);
    Route::post('financial-reports/balance-sheet', [ReportingController::class, 'balanceSheet']);
    Route::post('financial-reports/cash-flow', [ReportingController::class, 'cashFlowStatement']);
    Route::post('financial-reports/tax-summary', [ReportingController::class, 'taxSummary']);
    Route::post('financial-reports/multi-period', [ReportingController::class, 'multiPeriodComparison']);
    Route::get('financial-reports', [ReportingController::class, 'index']);
    Route::get('financial-reports/{report}', [ReportingController::class, 'show']);
    Route::get('dashboard/kpi', [ReportingController::class, 'dashboard']);
    Route::get('accounts/{accountId}/drilldown', [ReportingController::class, 'accountDrilldown']);
    Route::get('gl-accounts/{accountCode}/drilldown', [ReportingController::class, 'glDrilldown']);
    Route::get('account-types/{accountType}/detail', [ReportingController::class, 'accountTypeDetail']);

    // Financial Ratios (IFRS-aligned analysis)
    Route::get('ratios', [FinancialRatiosController::class, 'allRatios']);
    Route::get('ratios/liquidity', [FinancialRatiosController::class, 'liquidityRatios']);
    Route::get('ratios/profitability', [FinancialRatiosController::class, 'profitabilityRatios']);
    Route::get('ratios/efficiency', [FinancialRatiosController::class, 'efficiencyRatios']);
    Route::get('ratios/leverage', [FinancialRatiosController::class, 'leverageRatios']);
    Route::get('ratios/market', [FinancialRatiosController::class, 'marketRatios']);
    Route::get('ratios/dupont', [FinancialRatiosController::class, 'duPontAnalysis']);
});

// Write operations (150 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:accountant,finance-manager,manager,admin', 'throttle:create_post'])->group(function () {
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus']);
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment']);
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
    Route::post('invoices/{invoice}/approvals', [InvoiceApprovalController::class, 'store']);
    Route::post('invoices/{invoice}/approve', [InvoiceApprovalController::class, 'approve']);
    Route::post('invoices/{invoice}/reject', [InvoiceApprovalController::class, 'reject']);

    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve']);

    Route::post('gl-accounts', [GLAccountController::class, 'store']);
    Route::put('gl-accounts/{glAccount}', [GLAccountController::class, 'update']);
    Route::delete('gl-accounts/{glAccount}', [GLAccountController::class, 'destroy']);

    Route::post('journals', [JournalController::class, 'store']);
    Route::put('journals/{journal}', [JournalController::class, 'update']);
    Route::delete('journals/{journal}', [JournalController::class, 'destroy']);

    // Journal Entries API
    Route::get('journal-entries', [JournalEntryApiController::class, 'index']);
    Route::post('journal-entries', [JournalEntryApiController::class, 'store']);
    Route::post('journal-entries/{id}/post', [JournalEntryApiController::class, 'post']);
    Route::post('journal-entries/{id}/reverse', [JournalEntryApiController::class, 'reverse']);

    Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store']);
    Route::put('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update']);
    Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy']);

    // Budget Management (full variance, forecasting, alerts)
    Route::get('budgets/summary', [BudgetManagementController::class, 'summary']);
    Route::get('budgets/department-breakdown', [BudgetManagementController::class, 'departmentBreakdown']);
    Route::get('budgets/{budget}', [BudgetController::class, 'show']);
    Route::post('budgets', [BudgetManagementController::class, 'store']);
    Route::put('budgets/{budget}', [BudgetManagementController::class, 'update']);
    Route::delete('budgets/{budget}', [BudgetManagementController::class, 'destroy']);
    Route::post('budgets/{budget}/lines', [BudgetManagementController::class, 'createLine']);
    Route::post('budgets/{budget}/alerts', [BudgetManagementController::class, 'generateAlerts']);
    Route::post('budget-lines/{budgetLine}/forecasts', [BudgetManagementController::class, 'createForecasts']);
    Route::post('budgets/{budget}/approve', [BudgetManagementController::class, 'approveBudget']);
    Route::post('budgets/{budget}/reject', [BudgetManagementController::class, 'rejectBudget']);
    Route::post('budgets/{budget}/clone', [BudgetManagementController::class, 'cloneBudget']);
    Route::post('budgets/lines/{budgetLine}/spend', [BudgetManagementController::class, 'recordSpend']);
    Route::post('budgets/{budget}/sync-actuals', [BudgetManagementController::class, 'syncActuals']);
    Route::get('budgets/{budget}/variance-report', [BudgetManagementController::class, 'varianceReport']);
    Route::get('budgets/{budget}/monthly-trend', [BudgetManagementController::class, 'monthlyTrend']);
    Route::get('budgets/{budget}/top-variances', [BudgetManagementController::class, 'topVariances']);
    Route::get('budgets/{budget}/scenarios', [BudgetManagementController::class, 'listScenarios']);
    Route::post('budgets/{budget}/scenarios', [BudgetManagementController::class, 'createScenario']);
    Route::get('budget-scenarios/{scenario}/project', [BudgetManagementController::class, 'projectScenario']);

    Route::post('tax-rates', [TaxRateController::class, 'store']);
    Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update']);
    Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy']);

    Route::post('tax/entries', [TaxController::class, 'recordEntry']);

    // VAT Declarations
    Route::get('vat-declarations', [VatDeclarationController::class, 'index']);
    Route::post('vat-declarations/calculate', [VatDeclarationController::class, 'calculate']);
    Route::get('vat-declarations/{vatDeclaration}', [VatDeclarationController::class, 'show']);
    Route::post('vat-declarations/{vatDeclaration}/submit', [VatDeclarationController::class, 'submit']);
    Route::get('vat-declarations/{vatDeclaration}/report', [VatDeclarationController::class, 'report']);
    Route::apiResource('vat-rates', VatRateController::class);

    // Tax Management Routes
    Route::apiResource('tax/categories', TaxCategoriesController::class);
    Route::get('tax/categories/by-jurisdiction/{jurisdiction}', [TaxCategoriesController::class, 'byJurisdiction']);

    Route::apiResource('tax/rules', TaxRulesController::class);
    Route::post('tax/rules/{rule}/evaluate', [TaxRulesController::class, 'evaluate']);

    Route::post('tax/calculate', [TaxController::class, 'calculate']);
    Route::post('tax/calculate-compound', [TaxController::class, 'calculateCompound']);
    Route::post('tax-rates', [TaxRateController::class, 'store']);
    Route::post('tax-rates/{taxRate}/activate', [TaxRateController::class, 'activate']);
    Route::post('tax-rates/{taxRate}/deactivate', [TaxRateController::class, 'deactivate']);
    Route::post('tax/calculate/batch', [TaxCalculationController::class, 'batch']);
    Route::post('tax/calculate/simulate', [TaxCalculationController::class, 'simulate']);
    Route::get('tax/calculate/history', [TaxCalculationController::class, 'history']);

    Route::get('tax/compliance', [TaxComplianceController::class, 'index']);
    Route::post('tax/compliance', [TaxComplianceController::class, 'store']);
    Route::put('tax/compliance/{compliance}', [TaxComplianceController::class, 'update']);
    Route::get('tax/compliance/upcoming', [TaxComplianceController::class, 'upcoming']);
    Route::get('tax/compliance/report', [TaxComplianceController::class, 'report']);
    Route::get('tax/compliance/summary', [TaxComplianceController::class, 'summary']);
    Route::get('tax/compliance/export', [TaxComplianceController::class, 'export']);
    Route::post('tax/compliance/mark-overdue', [TaxComplianceController::class, 'markOverdue']);

    // Tax Compliance Reports (formal filing register — TaxComplianceReportController)
    Route::get('tax-compliance-reports', [TaxComplianceReportController::class, 'index']);
    Route::post('tax-compliance-reports', [TaxComplianceReportController::class, 'store']);
    Route::get('tax-compliance-reports/{report}', [TaxComplianceReportController::class, 'show']);
    Route::put('tax-compliance-reports/{report}', [TaxComplianceReportController::class, 'update']);
    Route::post('tax-compliance-reports/{report}/file', [TaxComplianceReportController::class, 'file']);
    Route::delete('tax-compliance-reports/{report}', [TaxComplianceReportController::class, 'destroy']);

    // Financial Report Management
    Route::post('financial-reports/{report}/publish', [ReportingController::class, 'publish']);
    Route::post('financial-reports/{report}/review', [ReportingController::class, 'review']);
    Route::get('financial-reports/{report}/export/excel', [ReportingController::class, 'exportExcel']);
    Route::get('financial-reports/{report}/export/pdf', [ReportingController::class, 'exportPdf']);
    Route::post('financial-reports/export/multiple', [ReportingController::class, 'exportMultiple']);

    // Bank Reconciliation
    Route::post('reconciliations', [ReconciliationController::class, 'initiate']);
    Route::post('reconciliations/{reconciliation}/import', [ReconciliationController::class, 'importStatement']);
    Route::post('reconciliations/{reconciliation}/auto-match', [ReconciliationController::class, 'autoMatch']);
    Route::post('reconciliations/{reconciliation}/match', [ReconciliationController::class, 'manualMatch']);
    Route::post('reconciliations/{reconciliation}/complete', [ReconciliationController::class, 'complete']);
    Route::post('reconciliations/{reconciliation}/approve', [ReconciliationController::class, 'approve']);
    Route::post('reconciliations/{reconciliation}/reject', [ReconciliationController::class, 'reject']);
    Route::get('reconciliations/{reconciliation}/outstanding', [ReconciliationController::class, 'outstandingItems']);
    Route::get('reconciliations/{reconciliation}/exceptions', [ReconciliationController::class, 'exceptions']);
    Route::get('reconciliations/{reconciliation}/suggest/{bankTransaction}', [ReconciliationController::class, 'suggestMatches']);

    // Multi-Entity Consolidation
    Route::post('consolidations', [ConsolidationController::class, 'store']);
    Route::put('consolidations/{company}', [ConsolidationController::class, 'update']);
    Route::post('consolidations/{company}/report', [ConsolidationController::class, 'generateReport']);
    Route::post('consolidations/eliminate', [ConsolidationController::class, 'eliminateAll']);
    Route::post('intercompany-transactions', [ConsolidationController::class, 'recordTransaction']);

    // Expense Management
    Route::put('expenses/{expense}', [ExpensesController::class, 'update']);
    Route::delete('expenses/{expense}', [ExpensesController::class, 'destroy']);
    Route::post('expenses/{expense}/approve', [ExpensesController::class, 'approve']);
    Route::post('expenses/{expense}/reject', [ExpensesController::class, 'reject']);
    Route::post('expense-reports/{report}/add', [ExpensesController::class, 'addToReport']);
    Route::post('expense-reports/{report}/submit', [ExpensesController::class, 'submitReport']);
    Route::post('expense-reports/{report}/approve', [ExpensesController::class, 'approveReport']);
    Route::post('expense-reports/{report}/reimburse', [ExpensesController::class, 'reimburse']);
    Route::post('expenses/by-period', [ExpensesController::class, 'byPeriod']);

    // Advanced Accounting Features
    // Audit Trail
    Route::get('audit-trail/{entityType}/{entityId}', [AdvancedAccountingController::class, 'auditTrail']);
    Route::get('audit-summary/{entityType}', [AdvancedAccountingController::class, 'auditSummary']);

    // XBRL Export (rate limited - expensive operation)
    Route::middleware('throttle:10,60')->group(function () {
        Route::post('xbrl-export/{report}', [AdvancedAccountingController::class, 'generateXBRL']);
        Route::get('xbrl-validate/{exportId}', [AdvancedAccountingController::class, 'validateXBRL']);
        Route::get('xbrl-export/{exportId}', [AdvancedAccountingController::class, 'exportXBRLFile']);
    });

    // Intercompany Automation
    Route::post('process-intercompany-rules', [AdvancedAccountingController::class, 'processIntercompanyRules']);
    Route::post('intercompany-rules', [AdvancedAccountingController::class, 'createIntercompanyRule']);
    Route::get('intercompany-rules/{ruleId}/test', [AdvancedAccountingController::class, 'testIntercompanyRule']);

    // Consolidation Worksheets (rate limited)
    Route::middleware('throttle:20,60')->group(function () {
        Route::post('consolidations/{consolidation}/worksheets', [AdvancedAccountingController::class, 'createConsolidationWorksheet']);
        Route::post('worksheets/{worksheetId}/submit', [AdvancedAccountingController::class, 'submitWorksheetForReview']);
        Route::post('worksheets/{worksheetId}/approve', [AdvancedAccountingController::class, 'approveWorksheet']);
        Route::get('worksheets/{worksheetId}/summary', [AdvancedAccountingController::class, 'worksheetSummary']);
    });

    // ML Matching
    Route::post('reconciliations/{reconciliation}/metrics', [AdvancedAccountingController::class, 'recordMatchingMetric']);
    Route::get('reconciliations/{reconciliation}/accuracy', [AdvancedAccountingController::class, 'matchingAccuracy']);
    Route::get('reconciliations/{reconciliation}/trends', [AdvancedAccountingController::class, 'matchingTrends']);
    Route::get('reconciliations/{reconciliation}/recommended-algorithm', [AdvancedAccountingController::class, 'recommendedAlgorithm']);
    Route::post('metrics/{metricId}/feedback', [AdvancedAccountingController::class, 'feedbackMatch']);

    // AI Routes
    Route::post('ai/forecast-cash-flow', [AccountingAIController::class, 'forecastCashFlow']);
    Route::post('ai/summarize-balance-sheet', [AccountingAIController::class, 'summarizeBalanceSheet']);
    Route::post('ai/categorize-transactions', [AccountingAIController::class, 'categorizeTransactions']);

    // Open Banking
    Route::post('open-banking/connect', [AccOpenBankingController::class, 'connect']);
    Route::post('open-banking/sync/{connection}', [AccOpenBankingController::class, 'sync']);
    Route::get('open-banking/connections', [AccOpenBankingController::class, 'indexConnections']);
    Route::post('open-banking/connections', [AccOpenBankingController::class, 'storeConnection']);
    Route::post('open-banking/connections/{connection}/sync', [AccOpenBankingController::class, 'syncConnection']);
    Route::delete('open-banking/connections/{connection}', [AccOpenBankingController::class, 'destroyConnection']);
    Route::get('open-banking/transactions', [AccOpenBankingController::class, 'indexTransactions']);
    Route::patch('open-banking/transactions/{transaction}', [AccOpenBankingController::class, 'updateTransaction']);

    // Bank Accounts
    Route::get('bank/summary', [BankAccountController::class, 'summary']);
    Route::get('bank/statements/{statement}', [BankReconciliationController::class, 'showStatement']);
    Route::post('bank/statements/{statement}/auto-match', [BankReconciliationController::class, 'autoMatch']);
    Route::post('bank/statements/{statement}/reconcile', [BankReconciliationController::class, 'reconcileStatement']);
    Route::get('bank/statements/{statement}/unmatched', [BankReconciliationController::class, 'unmatched']);
    Route::apiResource('bank', BankAccountController::class);
    Route::get('bank/{bank}/statements', [BankAccountController::class, 'statements']);
    Route::post('bank/{bank}/statements', [BankReconciliationController::class, 'importStatement']);
    Route::get('bank/{bankAccount}/transactions', [BankTransactionController::class, 'index']);
    Route::post('bank/transactions/{transaction}/match', [BankTransactionController::class, 'match']);
    Route::post('bank/transactions/{transaction}/ignore', [BankTransactionController::class, 'ignore']);
    Route::post('bank/transactions/unmatch', [BankTransactionController::class, 'unmatch']);

    // Reconciliation sessions & account-level auto-match (bank-accounts/{bankAccount}/... —
    // matches BankAccount $bankAccount param naming used by ReconciliationSessionController)
    Route::post('bank-accounts/{bankAccount}/transactions/auto-match', [BankReconciliationController::class, 'autoMatchAccount']);
    Route::get('bank-accounts/{bankAccount}/sessions', [ReconciliationSessionController::class, 'index']);
    Route::post('bank-accounts/{bankAccount}/sessions', [ReconciliationSessionController::class, 'store']);
    Route::get('bank-accounts/{bankAccount}/sessions/{reconciliationSession}', [ReconciliationSessionController::class, 'show']);
    Route::delete('bank-accounts/{bankAccount}/sessions/{reconciliationSession}', [ReconciliationSessionController::class, 'destroy']);
    Route::post('bank-accounts/{bankAccount}/sessions/{reconciliationSession}/complete', [ReconciliationSessionController::class, 'complete']);

    // Exchange Rates (static routes BEFORE apiResource to avoid {exchangeRate} binding conflict)
    Route::get('exchange-rates/gain-losses', [ExchangeRateController::class, 'gainLosses']);
    Route::post('exchange-rates/fetch', [ExchangeRateController::class, 'fetch']);
    Route::apiResource('exchange-rates', ExchangeRateController::class);

    // Expense Reports
    Route::get('expense-report-items', [ExpenseReportController::class, 'index']);
    Route::post('expense-report-items', [ExpenseReportController::class, 'store']);

    // Treasury Planning
    Route::get('treasury-planning/projection', [TreasuryPlanningController::class, 'projection']);
    Route::get('treasury-planning/dashboard', [TreasuryPlanningController::class, 'dashboard']);
    Route::get('treasury-planning', [TreasuryPlanningController::class, 'index']);
    Route::post('treasury-planning', [TreasuryPlanningController::class, 'store']);
    Route::get('treasury-planning/{forecast}', [TreasuryPlanningController::class, 'show']);
    Route::put('treasury-planning/{forecast}', [TreasuryPlanningController::class, 'update']);
    Route::delete('treasury-planning/{forecast}', [TreasuryPlanningController::class, 'destroy']);
    Route::post('treasury-planning/{forecast}/lines', [TreasuryPlanningController::class, 'addLine']);
    Route::delete('treasury-planning/{forecast}/lines/{line}', [TreasuryPlanningController::class, 'removeLine']);
    Route::post('treasury-planning/{forecast}/recompute', [TreasuryPlanningController::class, 'recompute']);
    Route::get('treasury-planning/{forecast}/scenarios', [TreasuryPlanningController::class, 'scenarios']);
    Route::post('treasury-planning/{forecast}/scenarios', [TreasuryPlanningController::class, 'createScenario']);
    Route::post('treasury-planning/{forecast}/scenario-analysis', [TreasuryPlanningController::class, 'runAnalysis']);
    Route::post('treasury-planning/{forecast}/lines/{line}/realize', [TreasuryPlanningController::class, 'realizeLine']);
    Route::post('treasury-planning/lines/{line}/realize', [TreasuryPlanningController::class, 'realizeLine']);

    // Treasury / Cash Flow Forecasting
    Route::get('treasury/dashboard', [TreasuryController::class, 'dashboard']);
    Route::get('treasury/alerts', [TreasuryController::class, 'alerts']);
    Route::post('treasury/alerts', [TreasuryController::class, 'storeAlert']);
    Route::put('treasury/alerts/{alert}', [TreasuryController::class, 'updateAlert']);
    Route::delete('treasury/alerts/{alert}', [TreasuryController::class, 'destroyAlert']);
    Route::post('treasury/scenarios/compare', [TreasuryController::class, 'compareScenarios']);
    Route::get('treasury/forecasts', [TreasuryController::class, 'index']);
    Route::post('treasury/forecasts', [TreasuryController::class, 'store']);
    Route::get('treasury/forecasts/{forecast}/timeline', [TreasuryController::class, 'timeline']);
    Route::get('treasury/forecasts/{forecast}/summary', [TreasuryController::class, 'summary']);
    Route::post('treasury/forecasts/{forecast}/items', [TreasuryController::class, 'addItem']);
    Route::delete('treasury/forecasts/{forecast}/items/{itemId}', [TreasuryController::class, 'removeItem']);
    Route::post('treasury/forecasts/{forecast}/evaluate-alerts', [TreasuryController::class, 'evaluateAlerts']);
    Route::get('treasury/forecasts/{forecast}', [TreasuryController::class, 'show']);
    Route::delete('treasury/forecasts/{forecast}', [TreasuryController::class, 'destroy']);

    // Fixed Assets
    Route::get('fixed-assets/register', [FixedAssetController::class, 'register']);
    Route::post('fixed-assets/depreciate-all', [FixedAssetController::class, 'depreciateAll']);
    Route::get('fixed-assets', [FixedAssetController::class, 'index']);
    Route::post('fixed-assets', [FixedAssetController::class, 'store']);
    Route::get('fixed-assets/{fixedAsset}', [FixedAssetController::class, 'show']);
    Route::put('fixed-assets/{fixedAsset}', [FixedAssetController::class, 'update']);
    Route::delete('fixed-assets/{fixedAsset}', [FixedAssetController::class, 'destroy']);
    Route::get('fixed-assets/{fixedAsset}/schedule', [FixedAssetController::class, 'schedule']);
    Route::post('fixed-assets/{fixedAsset}/depreciate', [FixedAssetController::class, 'depreciate']);
    Route::post('fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose']);

    // Performance Optimization (rate limited - admin operations)
    Route::middleware('throttle:30,60')->group(function () {
        Route::get('performance/cache-stats', [PerformanceOptimizationController::class, 'cacheStats']);
        Route::post('performance/cache-clear', [PerformanceOptimizationController::class, 'clearCache']);
        Route::post('performance/query-analysis', [PerformanceOptimizationController::class, 'queryPerformance']);
        Route::get('performance/optimization-tips', [PerformanceOptimizationController::class, 'optimizationRecommendations']);
        Route::get('performance/profile', [PerformanceOptimizationController::class, 'performanceProfile']);
        Route::get('performance/slow-queries', [PerformanceOptimizationController::class, 'slowQueries']);
        Route::get('performance/memory-peaks', [PerformanceOptimizationController::class, 'memoryPeaks']);
        Route::get('performance/metrics', [PerformanceOptimizationController::class, 'optimizationMetrics']);
        Route::post('performance/invalidate-gl-cache/{accountId}', [PerformanceOptimizationController::class, 'invalidateGLCache']);
        Route::post('performance/invalidate-report-cache/{reportId}', [PerformanceOptimizationController::class, 'invalidateReportCache']);
        Route::post('performance/invalidate-trial-balance-cache/{companyId}', [PerformanceOptimizationController::class, 'invalidateTrialBalanceCache']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/accounting')->group(function () {
    Route::post('ai/assist', [\Modules\Accounting\Http\Controllers\Api\AccountingAiAssistController::class, 'assist'])
        ->name('accounting.ai.assist');
});

// ── Multi-entity consolidation hierarchies (permission-gated, not role-gated) ──
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::apiResource('consolidation-hierarchies', \Modules\Accounting\Http\Controllers\ConsolidationHierarchyController::class)
        ->parameters(['consolidation-hierarchies' => 'hierarchy']);
});
