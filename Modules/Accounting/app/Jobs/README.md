# Accounting Module Async Jobs (BLOC 3)

Advanced financial operations for the Accounting module supporting complex calculations, consolidation, revenue recognition, tax provisioning, and compliance validation.

## 12 Async Job Classes

1. **ConsolidateFinancialsJob** - Multi-entity financial consolidation with IFRS/GAAP standards
2. **EliminateIntercompanyJob** - Intercompany transaction elimination
3. **CalculateMinorityInterestJob** - Minority interest calculations for non-wholly-owned subsidiaries
4. **ProcessConsolidationAdjustmentsJob** - Fair value, goodwill, and deferred tax adjustments
5. **RecognizeRevenueJob** - ASC 606/IFRS 15 revenue recognition
6. **ProcessContractModificationJob** - Contract modification accounting
7. **CalculateTaxProvisionsJob** - Income tax provision with uncertain tax positions (ASC 740)
8. **UpdateDeferredTaxJob** - Deferred tax asset/liability calculations
9. **ComputeTransferPricingJob** - OECD transfer pricing compliance
10. **GenerateConsolidatedReportsJob** - Consolidated financial statements and reports
11. **GenerateTaxReportsJob** - Tax compliance and regulatory reports
12. **ValidateComplianceJob** - Financial and tax compliance validation

## Queue Configuration

All jobs use the dedicated `accounting` queue:
- Queue: `accounting`
- Default Timeout: 1.5h - 3h
- Retries: 1-2 attempts
- Multi-tenancy: Full support with company isolation

## Key Features

- **Advanced Consolidation**: Multi-entity consolidation with currency translation and minority interest
- **Revenue Recognition**: ASC 606/IFRS 15 compliant contract evaluation and revenue recognition
- **Tax Compliance**: Provision calculations, uncertain tax positions (ASC 740), deferred tax assets/liabilities
- **Transfer Pricing**: OECD method applications (CUP, Cost Plus, Resale Price, Profit Split)
- **Financial Reporting**: Consolidated balance sheets, income statements, cash flow statements
- **Compliance Validation**: IFRS/GAAP, tax, revenue recognition, lease accounting, GDPR, anti-fraud controls

## Usage Examples

```php
use Modules\Accounting\Jobs\ConsolidateFinancialsJob;

// Consolidate financial statements
ConsolidateFinancialsJob::dispatch($consolidationGroup, '2026-03-31');

// Recognize revenue per ASC 606
RecognizeRevenueJob::dispatch($contract, '2026-Q1');

// Calculate tax provisions
CalculateTaxProvisionsJob::dispatch(fiscalYear: '2026');

// Generate consolidated reports
GenerateConsolidatedReportsJob::dispatch($consolidationGroup, 'all');
```

## Configuration

Add to `.env`:
```env
QUEUE_CONNECTION=database
ACCOUNTING_QUEUE_TIMEOUT=7200
CONSOLIDATION_ENABLED=true
REVENUE_RECOGNITION_ENABLED=true
TAX_PROVISIONING_ENABLED=true
```

## Testing

Run accounting jobs tests:
```bash
cd apps/api
vendor/bin/pest Modules/Accounting/Tests/Feature/Jobs/
```

## References

- ASC 606 Revenue Recognition
- ASC 740 Income Taxes
- ASC 842 Leases
- IFRS Consolidation (IFRS 10, 11, 12)
- OECD Transfer Pricing Guidelines
- GDPR Personal Data Protection
- Multi-tenancy Architecture

---
**Version:** 1.0.0 | **PHP:** 8.3+ | **Laravel:** 12+
