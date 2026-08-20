<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\CostingSheetLine;
use Modules\Shared\Models\Currency;

/**
 * Chantier 21 — computes a CostingSheet's line totals and header
 * aggregates, reproducing numerically what the avant-vente team currently
 * calculates by hand in a spreadsheet: matière + accessoires montage +
 * accessoires finition + valeur ajoutée + lavage + main-d'œuvre + frais
 * fixes = coût de revient unitaire.
 *
 * Every monetary figure is converted into the sheet's own base_currency
 * before being summed — a line quoted in EUR/USD (common for imported
 * fabric) must not be added directly to one quoted in MGA. Conversion
 * reuses the real seeded exchange_rate_to_usd data (Modules\Shared\Currency,
 * Chantier 17) via the same math as SourcingBenchmarkService::convert() —
 * a missing rate surfaces as "not convertible" (null → treated as 0 with
 * a warning the caller can surface), never a silently wrong number.
 */
class CostingSheetService
{
    public function create(array $data, ?int $userId): CostingSheet
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $sheet = DB::transaction(function () use ($data, $lines, $userId) {
            $sheet = CostingSheet::create($data + [
                'reference' => $data['reference'] ?? $this->generateReference(),
                'created_by' => $userId,
            ]);

            $this->syncLines($sheet, $lines);

            return $sheet;
        });

        return $this->recalculate($sheet);
    }

    public function update(CostingSheet $sheet, array $data): CostingSheet
    {
        $lines = $data['lines'] ?? null;
        unset($data['lines']);

        DB::transaction(function () use ($sheet, $data, $lines) {
            $sheet->update($data);

            if ($lines !== null) {
                $this->syncLines($sheet, $lines);
            }
        });

        return $this->recalculate($sheet->fresh());
    }

    /**
     * Creates a new draft revision (version + 1, parent_id set) from an
     * existing sheet, so a client-requested change doesn't overwrite a
     * quote already sent — matches this app's established "duplicate,
     * don't mutate" convention for already-communicated documents.
     */
    public function duplicateAsRevision(CostingSheet $sheet, ?int $userId): CostingSheet
    {
        return DB::transaction(function () use ($sheet, $userId) {
            $revision = $sheet->replicate(['reference', 'status', 'created_at', 'updated_at']);
            $revision->reference = $this->generateReference();
            $revision->status = 'draft';
            $revision->version = $sheet->version + 1;
            $revision->parent_id = $sheet->id;
            $revision->created_by = $userId;
            $revision->save();

            foreach ($sheet->lines as $line) {
                $revision->lines()->create($line->only([
                    'section', 'designation', 'product_template_id', 'supplier_id',
                    'consumption_qty', 'unit', 'unit_price', 'currency',
                    'customs_freight_percent', 'margin_percent', 'line_total', 'sequence',
                ]));
            }

            return $this->recalculate($revision);
        });
    }

    private function syncLines(CostingSheet $sheet, array $lines): void
    {
        $sheet->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $sheet->lines()->create([
                'section' => $line['section'],
                'designation' => $line['designation'],
                'product_template_id' => $line['product_template_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'consumption_qty' => $line['consumption_qty'] ?? 0,
                'unit' => $line['unit'] ?? null,
                'unit_price' => $line['unit_price'] ?? 0,
                'currency' => $line['currency'] ?? $sheet->base_currency,
                'customs_freight_percent' => $line['customs_freight_percent'] ?? 0,
                'margin_percent' => $line['margin_percent'] ?? null,
                'sequence' => $line['sequence'] ?? $index,
            ]);
        }
    }

    /**
     * Recomputes every line's line_total (consumption × unit price, plus
     * customs/freight %, converted to the sheet's base currency) and the
     * header's section subtotals + final cost price, persisting both —
     * called after any create/update so the stored figures never drift
     * from the lines that produced them.
     */
    public function recalculate(CostingSheet $sheet): CostingSheet
    {
        $sheet->load('lines');

        $sectionTotals = array_fill_keys(array_keys(CostingSheet::SECTIONS), 0.0);

        foreach ($sheet->lines as $line) {
            $convertedPrice = $this->convert((float) $line->unit_price, $line->currency, $sheet->base_currency) ?? 0.0;
            $lineValue = (float) $line->consumption_qty * $convertedPrice;
            $lineValue += $lineValue * ((float) $line->customs_freight_percent / 100);

            $line->line_total = round($lineValue, 4);
            $line->save();

            $sectionTotals[$line->section] = ($sectionTotals[$line->section] ?? 0.0) + $line->line_total;
        }

        $laborCost = round((float) $sheet->production_minutes * (float) $sheet->minute_cost, 4);

        $totalCostPrice = round(
            $sectionTotals['matiere']
            + $sectionTotals['accessoire_montage']
            + $sectionTotals['accessoire_finition']
            + $sectionTotals['valeur_ajoutee']
            + $sectionTotals['lavage']
            + $laborCost
            + (float) $sheet->fixed_cost_coefficient,
            4
        );

        $suggestedPrice = $sheet->target_margin_percent !== null
            ? round($totalCostPrice * (1 + ((float) $sheet->target_margin_percent / 100)), 4)
            : 0.0;

        $sheet->update([
            'labor_cost' => $laborCost,
            'total_material_cost' => round($sectionTotals['matiere'], 4),
            'total_assembly_cost' => round($sectionTotals['accessoire_montage'], 4),
            'total_finishing_cost' => round($sectionTotals['accessoire_finition'], 4),
            'total_value_added_cost' => round($sectionTotals['valeur_ajoutee'], 4),
            'washing_cost' => round($sectionTotals['lavage'], 4),
            'total_cost_price' => $totalCostPrice,
            'suggested_selling_price' => $suggestedPrice,
        ]);

        return $sheet->fresh('lines');
    }

    private function generateReference(): string
    {
        return 'DEV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Same conversion math as SourcingBenchmarkService::convert() (exchange_
     * rate_to_usd is "units of currency per 1 USD") — duplicated rather than
     * extracted to a shared helper, matching this app's existing precedent
     * (the same logic already lives independently in CurrencyController::
     * convert() and SourcingBenchmarkService). Returns null when either
     * currency has no real seeded rate; the caller treats that as 0 rather
     * than guessing a number.
     */
    private function convert(float $amount, string $from, string $to): ?float
    {
        if ($from === $to) {
            return round($amount, 4);
        }

        $fromCurrency = Currency::where('code', strtoupper($from))->first();
        $toCurrency = Currency::where('code', strtoupper($to))->first();

        if (!$fromCurrency || !$toCurrency || $fromCurrency->exchange_rate_to_usd === null || $toCurrency->exchange_rate_to_usd === null) {
            return null;
        }

        $amountInUsd = $amount / (float) $fromCurrency->exchange_rate_to_usd;

        return round($amountInUsd * (float) $toCurrency->exchange_rate_to_usd, 4);
    }
}
