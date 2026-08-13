<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Modules\Accounting\Models\CurrencyGainLoss;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Models\Invoice;
use RuntimeException;

class MultiCurrencyService
{
    /**
     * Get exchange rate for a currency pair on a given date.
     */
    public function getRate(string $from, string $to, ?Carbon $date = null): string
    {
        if ($from === $to) {
            return '1.000000';
        }

        $date ??= Carbon::today();

        /** @var ExchangeRate|null $rate */
        $rate = ExchangeRate::where('base_currency', $from)
            ->where('target_currency', $to)
            ->where('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->first();

        if ($rate === null) {
            // Try inverse
            /** @var ExchangeRate|null $inverse */
            $inverse = ExchangeRate::where('base_currency', $to)
                ->where('target_currency', $from)
                ->where('date', '<=', $date->toDateString())
                ->orderByDesc('date')
                ->first();

            if ($inverse === null) {
                throw new RuntimeException("No exchange rate found for {$from}/{$to}.");
            }

            $inverseRate = (float) $inverse->rate;
            if ($inverseRate == 0.0) {
                throw new RuntimeException("Exchange rate is zero for {$to}/{$from}.");
            }

            return number_format(1.0 / $inverseRate, 6, '.', '');
        }

        return (string) $rate->rate;
    }

    /**
     * Convert an amount from one currency to another.
     */
    public function convert(string $amount, string $from, string $to): string
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = (float) $this->getRate($from, $to);

        return number_format((float) $amount * $rate, 2, '.', '');
    }

    /**
     * Record a currency gain/loss entry for an invoice.
     */
    public function recordGainLoss(Invoice $inv): CurrencyGainLoss
    {
        $baseCurrency = 'EUR';
        $originalAmount = (float) $inv->total;
        $convertedAmount = (float) $this->convert((string) $originalAmount, $inv->currency, $baseCurrency);
        $gainLoss = $convertedAmount - $originalAmount;

        /** @var CurrencyGainLoss $record */
        $record = CurrencyGainLoss::create([
            'invoice_id' => $inv->id,
            'original_amount' => $originalAmount,
            'original_currency' => $inv->currency,
            'converted_amount' => $convertedAmount,
            'base_currency' => $baseCurrency,
            'gain_loss' => $gainLoss,
            'realized' => $inv->status === 'paid',
        ]);

        return $record;
    }
}
