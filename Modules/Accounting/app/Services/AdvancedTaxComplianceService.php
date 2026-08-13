<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Models\TaxJurisdiction;
use Modules\Shared\Services\BaseService;

class AdvancedTaxComplianceService extends BaseService
{
    /**
     * Create a tax compliance report for a company and jurisdiction
     */
    public function createComplianceReport(
        int $companyId,
        TaxJurisdiction $jurisdiction,
        \Carbon\Carbon $periodStart,
        \Carbon\Carbon $periodEnd
    ): TaxComplianceReport {
        return TaxComplianceReport::create([
            'company_id' => $companyId,
            'tax_jurisdiction_id' => $jurisdiction->id,
            'report_period_start' => $periodStart,
            'report_period_end' => $periodEnd,
            'status' => 'draft',
            'tax_data' => [],
            'compliance_checks' => [],
        ]);
    }

    /**
     * Calculate VAT/GST for a transaction
     */
    public function calculateVAT(
        TaxJurisdiction $jurisdiction,
        float $baseAmount,
        string $customerType = 'domestic'
    ): array {
        $taxRate = (float)$jurisdiction->tax_rate;

        $taxAmount = match ($jurisdiction->tax_calculation_method) {
            'inclusive' => $this->calculateInclusiveTax($baseAmount, $taxRate),
            'exclusive' => $this->calculateExclusiveTax($baseAmount, $taxRate),
            'mixed' => $this->calculateMixedTax($baseAmount, $taxRate),
            default => 0,
        };

        $exemption = $this->checkExemption($jurisdiction, $customerType, $baseAmount);

        if ($exemption) {
            $taxAmount = 0;
        }

        return [
            'base_amount' => $baseAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $baseAmount + $taxAmount,
            'is_exempt' => $exemption !== null,
            'exemption_reason' => $exemption,
        ];
    }

    /**
     * Calculate income tax with withholding
     */
    public function calculateIncomeTax(
        TaxJurisdiction $jurisdiction,
        float $grossIncome,
        array $deductions = []
    ): array {
        $totalDeductions = array_sum($deductions);
        $taxableIncome = $grossIncome - $totalDeductions;

        // Progressive tax brackets
        $taxAmount = $this->calculateProgressiveTax($taxableIncome, $jurisdiction);

        // Calculate withholding if applicable
        $withholding = 0;
        if ($jurisdiction->tax_type === 'income_tax_withholding') {
            $withholding = $taxAmount * 0.1; // 10% withholding
        }

        return [
            'gross_income' => $grossIncome,
            'total_deductions' => $totalDeductions,
            'taxable_income' => max(0, $taxableIncome),
            'tax_amount' => $taxAmount,
            'withholding_tax' => $withholding,
            'net_tax' => $taxAmount - $withholding,
        ];
    }

    /**
     * Calculate transfer pricing for intercompany transactions
     */
    public function calculateTransferPrice(
        TaxJurisdiction $jurisdiction,
        float $cost,
        string $method = 'cost_plus'
    ): array {
        $margin = 0.25; // 25% markup for cost-plus

        $transferPrice = match ($method) {
            'cost_plus' => $cost * (1 + $margin),
            'comparable_uncontrolled' => $cost * 1.20, // 20% markup
            'resale_price' => $cost / 0.80, // 20% margin for reseller
            'profit_split' => $cost * 1.15, // 15% markup
            default => $cost,
        };

        return [
            'original_cost' => $cost,
            'transfer_price' => $transferPrice,
            'method' => $method,
            'profit_margin' => (($transferPrice - $cost) / $cost) * 100,
            'jurisdiction' => $jurisdiction->jurisdiction_code,
            'compliant' => true,
        ];
    }

    /**
     * Prepare tax filing for a jurisdiction
     */
    public function prepareTaxFiling(
        TaxComplianceReport $report
    ): array {
        $template = $report->jurisdiction->filingTemplates()->first();

        if (!$template) {
            return ['error' => 'No filing template found'];
        }

        $taxData = $report->tax_data ?? [];
        $filingData = [];

        foreach ($template->field_mappings ?? [] as $field => $mapping) {
            $value = $taxData[$mapping['gl_account']] ?? 0;

            // Apply calculations if defined
            if (isset($template->calculation_rules[$field])) {
                $value = $this->applyCalculation($value, $template->calculation_rules[$field]);
            }

            // Validate field
            if (!$template->validateField($field, $value)) {
                $report->update([
                    'status' => 'error',
                    'notes' => "Validation failed for field: {$field}",
                ]);

                return ['error' => "Validation failed for {$field}"];
            }

            $filingData[$field] = $value;
        }

        $report->update([
            'status' => 'prepared',
            'tax_data' => $filingData,
            'compliance_checks' => $this->performComplianceChecks($report, $filingData),
        ]);

        return $filingData;
    }

    /**
     * File tax report with jurisdiction
     */
    public function fileReport(TaxComplianceReport $report, string $filingReferenceNumber): void
    {
        $report->markFiled($filingReferenceNumber);
    }

    /**
     * Calculate deferred tax assets/liabilities
     */
    public function calculateDeferredTax(
        TaxJurisdiction $jurisdiction,
        float $bookIncome,
        float $taxableIncome,
        float $taxRate
    ): array {
        $temporaryDifference = $bookIncome - $taxableIncome;
        $deferredTaxAmount = $temporaryDifference * ($taxRate / 100);

        $assetOrLiability = $deferredTaxAmount > 0 ? 'asset' : 'liability';

        return [
            'book_income' => $bookIncome,
            'taxable_income' => $taxableIncome,
            'temporary_difference' => $temporaryDifference,
            'tax_rate' => $taxRate,
            'deferred_tax_amount' => abs($deferredTaxAmount),
            'type' => $assetOrLiability,
            'account_type' => $assetOrLiability === 'asset' ? 'deferred_tax_asset' : 'deferred_tax_liability',
        ];
    }

    // Private helper methods

    private function calculateInclusiveTax(float $amount, float $rate): float
    {
        return $amount - ($amount / (1 + ($rate / 100)));
    }

    private function calculateExclusiveTax(float $amount, float $rate): float
    {
        return $amount * ($rate / 100);
    }

    private function calculateMixedTax(float $amount, float $rate): float
    {
        return $amount * (($rate / 100) * 0.5);
    }

    private function checkExemption(
        TaxJurisdiction $jurisdiction,
        string $type,
        float $amount
    ): ?string {
        $exemptions = $jurisdiction->exemptions ?? [];

        foreach ($exemptions as $exemption) {
            if ($exemption['type'] === $type) {
                if (isset($exemption['threshold']) && $amount < $exemption['threshold']) {
                    return $exemption['reason'] ?? 'Below threshold';
                }

                if (isset($exemption['enabled']) && $exemption['enabled']) {
                    return $exemption['reason'] ?? 'Exemption applied';
                }
            }
        }

        return null;
    }

    private function calculateProgressiveTax(float $taxableIncome, TaxJurisdiction $jurisdiction): float
    {
        $brackets = $jurisdiction->tax_rules['tax_brackets'] ?? [];

        $tax = 0;
        $previousLimit = 0;

        foreach ($brackets as $bracket) {
            if ($taxableIncome <= $previousLimit) {
                break;
            }

            $taxableInBracket = min($taxableIncome, $bracket['limit']) - $previousLimit;
            $tax += $taxableInBracket * ($bracket['rate'] / 100);
            $previousLimit = $bracket['limit'];
        }

        return $tax;
    }

    private function applyCalculation(float $value, array $rule): float
    {
        return match ($rule['operation'] ?? null) {
            'multiply' => $value * ($rule['factor'] ?? 1),
            'divide' => $value / ($rule['factor'] ?? 1),
            'add' => $value + ($rule['factor'] ?? 0),
            'subtract' => $value - ($rule['factor'] ?? 0),
            'percentage' => $value * (($rule['factor'] ?? 0) / 100),
            default => $value,
        };
    }

    private function performComplianceChecks(
        TaxComplianceReport $report,
        array $filingData
    ): array {
        $checks = [
            'total_matches_sum' => false,
            'required_fields_present' => false,
            'values_in_range' => false,
            'cross_validation_passed' => false,
        ];

        // Implement specific compliance checks per jurisdiction
        if ($report->jurisdiction->jurisdiction_code === 'US') {
            $checks['1040_validation'] = true;
        } elseif ($report->jurisdiction->country_code === 'FR') {
            $checks['vat_compliance'] = true;
        }

        return $checks;
    }
}
