<?php

declare(strict_types=1);

namespace Modules\HR\Services;

/**
 * Masks employee bank account/routing numbers for API responses
 * (PCI DSS compliance) — shows only the last 4 digits.
 */
class BankDetailsMaskingService
{
    private const MASKABLE_FIELDS = ['account_number', 'iban', 'routing_number', 'bank_account_number'];

    /**
     * @param  array<string, mixed>|string|null  $bankDetails
     * @return array<string, mixed>|null
     */
    public static function maskBankDetails(array|string|null $bankDetails): ?array
    {
        if ($bankDetails === null) {
            return null;
        }

        if (is_string($bankDetails)) {
            $decoded = json_decode($bankDetails, true);
            $bankDetails = is_array($decoded) ? $decoded : [];
        }

        foreach (self::MASKABLE_FIELDS as $field) {
            if (! empty($bankDetails[$field]) && is_string($bankDetails[$field])) {
                $bankDetails[$field] = self::mask($bankDetails[$field]);
            }
        }

        return $bankDetails;
    }

    private static function mask(string $value): string
    {
        $digitsOnly = preg_replace('/\s+/', '', $value);
        $length = strlen($digitsOnly);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($digitsOnly, -4);
    }
}
