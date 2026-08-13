<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * SmartDefaultsService — Simplicity First
 *
 * Returns pre-filled form defaults for a given country + industry + module.
 * Powers the Africa First / Asia First smart-fill feature so non-technical
 * users never have to look up their VAT rate or currency code.
 */
class SmartDefaultsService
{
    /** Country-level static data. */
    private const COUNTRIES = [
        'SN' => [
            'label'            => 'Sénégal',
            'currency'         => 'XOF',
            'tax_rate'         => 18.0,
            'tax_label'        => 'TVA',
            'payment_methods'  => ['orange_money', 'wave', 'mtn_momo', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'OHADA',
            'mobile_country_code' => '+221',
        ],
        'CI' => [
            'label'            => "Côte d'Ivoire",
            'currency'         => 'XOF',
            'tax_rate'         => 18.0,
            'tax_label'        => 'TVA',
            'payment_methods'  => ['orange_money', 'mtn_momo', 'wave', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'OHADA',
            'mobile_country_code' => '+225',
        ],
        'CM' => [
            'label'            => 'Cameroun',
            'currency'         => 'XAF',
            'tax_rate'         => 19.25,
            'tax_label'        => 'TVA',
            'payment_methods'  => ['orange_money', 'mtn_momo', 'airtel_money', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'OHADA',
            'mobile_country_code' => '+237',
        ],
        'MA' => [
            'label'            => 'Maroc',
            'currency'         => 'MAD',
            'tax_rate'         => 20.0,
            'tax_label'        => 'TVA',
            'payment_methods'  => ['cashplus', 'wafacash', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'PCM',
            'mobile_country_code' => '+212',
        ],
        'NG' => [
            'label'            => 'Nigeria',
            'currency'         => 'NGN',
            'tax_rate'         => 7.5,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['paystack', 'flutterwave', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'IFRS',
            'mobile_country_code' => '+234',
        ],
        'GH' => [
            'label'            => 'Ghana',
            'currency'         => 'GHS',
            'tax_rate'         => 15.0,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['mtn_momo', 'vodafone_cash', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'IFRS',
            'mobile_country_code' => '+233',
        ],
        'KE' => [
            'label'            => 'Kenya',
            'currency'         => 'KES',
            'tax_rate'         => 16.0,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['m_pesa', 'airtel_money', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 7,
            'accounting_std'   => 'IFRS',
            'mobile_country_code' => '+254',
        ],
        'TZ' => [
            'label'            => 'Tanzania',
            'currency'         => 'TZS',
            'tax_rate'         => 18.0,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['m_pesa', 'airtel_money', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 7,
            'accounting_std'   => 'IFRS',
            'mobile_country_code' => '+255',
        ],
        'MG' => [
            'label'            => 'Madagascar',
            'currency'         => 'MGA',
            'tax_rate'         => 20.0,
            'tax_label'        => 'TVA',
            'payment_methods'  => ['mvola', 'airtel_money', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'PCG',
            'mobile_country_code' => '+261',
        ],
        'IN' => [
            'label'            => 'India',
            'currency'         => 'INR',
            'tax_rate'         => 18.0,
            'tax_label'        => 'GST',
            'payment_methods'  => ['upi', 'paytm', 'razorpay', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 4,
            'accounting_std'   => 'IndAS',
            'mobile_country_code' => '+91',
        ],
        'CN' => [
            'label'            => 'China',
            'currency'         => 'CNY',
            'tax_rate'         => 13.0,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['wechat_pay', 'alipay', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 1,
            'accounting_std'   => 'CAS',
            'mobile_country_code' => '+86',
        ],
        'EG' => [
            'label'            => 'Egypt',
            'currency'         => 'EGP',
            'tax_rate'         => 14.0,
            'tax_label'        => 'VAT',
            'payment_methods'  => ['fawry', 'vodafone_cash', 'cash', 'bank_transfer'],
            'fiscal_year_start'=> 7,
            'accounting_std'   => 'IFRS',
            'mobile_country_code' => '+20',
        ],
    ];

    /** Industry-specific product/service unit defaults. */
    private const INDUSTRY_UNITS = [
        'textile'        => ['mètre', 'rouleau', 'pièce', 'kg'],
        'menuiserie'     => ['mètre linéaire', 'm²', 'pièce', 'feuille'],
        'agroalimentaire'=> ['kg', 'litre', 'tonne', 'sac'],
        'technologie'    => ['unité', 'licence', 'abonnement'],
        'btp'            => ['m²', 'm³', 'tonne', 'kg', 'lot'],
        'general'        => ['unité', 'kg', 'litre', 'boîte'],
    ];

    /**
     * OHADA account classes by transaction/nature type.
     *
     * Class 1 → capital & long-term liabilities
     * Class 2 → fixed assets (immobilisations)
     * Class 3 → inventories (stocks)
     * Class 4 → receivables & payables (tiers)
     * Class 5 → treasury (cash & bank)
     * Class 6 → expenses (charges)
     * Class 7 → revenues (produits)
     * Class 8 → special accounts
     */
    private const OHADA_CLASSES = [
        'liability'       => 1,
        'capital'         => 1,
        'long_term_debt'  => 1,
        'asset'           => 2,
        'fixed_asset'     => 2,
        'immobilisation'  => 2,
        'inventory'       => 3,
        'stock'           => 3,
        'receivable'      => 4,
        'payable'         => 4,
        'customer'        => 4,
        'supplier'        => 4,
        'cash'            => 5,
        'bank'            => 5,
        'treasury'        => 5,
        'expense'         => 6,
        'charge'          => 6,
        'revenue'         => 7,
        'income'          => 7,
        'product'         => 7,
        'special'         => 8,
    ];

    /** Fallback country used when the requested code is unknown. */
    private const FALLBACK_COUNTRY = 'SN';

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Return full form defaults for a module, pre-filled for the given country
     * and industry.  Safe to call with unknown countries/industries — it always
     * returns a valid array.
     */
    public function getDefaults(string $module, string $country = 'SN', string $industry = 'general'): array
    {
        $countryData = $this->countryData($country);

        return [
            'module'            => $module,
            'country'           => $country,
            'industry'          => $industry,
            'currency'          => $countryData['currency'],
            'taxRate'           => $countryData['tax_rate'],
            'taxLabel'          => $countryData['tax_label'],
            'paymentMethods'    => $countryData['payment_methods'],
            'fiscalYearStart'   => $countryData['fiscal_year_start'],
            'accountingStd'     => $countryData['accounting_std'],
            'units'             => $this->units($industry),
            'mobileCountryCode' => $countryData['mobile_country_code'],
        ];
    }

    /**
     * Return the standard VAT/GST/TVA rate (%) for a country.
     * Falls back to SN (18%) for unknown codes.
     */
    public function taxRate(string $country): float
    {
        return (float) $this->countryData($country)['tax_rate'];
    }

    /**
     * Return the ISO 4217 currency code for a country.
     */
    public function currency(string $country): string
    {
        return (string) $this->countryData($country)['currency'];
    }

    /**
     * Return the supported payment method keys for a country.
     *
     * @return list<string>
     */
    public function paymentMethods(string $country): array
    {
        return (array) $this->countryData($country)['payment_methods'];
    }

    /**
     * Return the fiscal year start month (1=January … 12=December).
     * Kenya/Tanzania/Egypt start in July (7); India starts in April (4).
     */
    public function fiscalYearStart(string $country): int
    {
        return (int) $this->countryData($country)['fiscal_year_start'];
    }

    /**
     * Return the OHADA plan comptable class number for a transaction type.
     * Returns 4 (tiers) as a safe default for unknown types.
     */
    public function ohadaClass(string $transactionType): int
    {
        return self::OHADA_CLASSES[strtolower($transactionType)] ?? 4;
    }

    /**
     * Return the preferred unit labels for an industry.
     *
     * @return list<string>
     */
    public function units(string $industry): array
    {
        return self::INDUSTRY_UNITS[strtolower($industry)]
            ?? self::INDUSTRY_UNITS['general'];
    }

    /**
     * Return the full data map for all supported countries.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allCountries(): array
    {
        return self::COUNTRIES;
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function countryData(string $country): array
    {
        return self::COUNTRIES[strtoupper($country)]
            ?? self::COUNTRIES[self::FALLBACK_COUNTRY];
    }
}
