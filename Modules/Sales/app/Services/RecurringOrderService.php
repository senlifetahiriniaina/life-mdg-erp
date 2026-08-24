<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Sales\Models\RecurringOrderTemplate;
use Modules\Sales\Models\RecurringOrderTemplateLine;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 25 (volet E de la feuille de route Chantier 21) — commandes
 * récurrentes. Génère de vraies SalesOrder via le vrai
 * SalesService::createOrder() (pas un chemin de données parallèle), à
 * échéance régulière (hebdomadaire/mensuelle/trimestrielle) — le cas
 * courant d'un client répétitif en confection/textile qui n'a pas besoin
 * de ressaisir sa commande à chaque cycle.
 */
class RecurringOrderService
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    public function create(array $data, int $tenantId, ?int $userId): RecurringOrderTemplate
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $lines, $tenantId, $userId) {
            $template = RecurringOrderTemplate::create($data + [
                'tenant_id' => $tenantId,
                'reference' => $data['reference'] ?? $this->generateReference(),
                'created_by' => $userId,
            ]);

            $this->syncLines($template, $lines);

            return $template->load('lines');
        });
    }

    public function update(RecurringOrderTemplate $template, array $data): RecurringOrderTemplate
    {
        $lines = $data['lines'] ?? null;
        unset($data['lines']);

        DB::transaction(function () use ($template, $data, $lines) {
            $template->update($data);

            if ($lines !== null) {
                $this->syncLines($template, $lines);
            }
        });

        return $template->fresh('lines');
    }

    /**
     * Appelée par la commande planifiée (`sales:generate-recurring-orders`).
     * Parcourt tous les modèles actifs de tous les tenants dont l'échéance
     * est atteinte ou dépassée.
     *
     * Chantier 32.16 (Sales deep 14-layer audit, layer 8 — business
     * validation): sequential re-invocation is safe by construction
     * (calculateNextRun() always anchors on Carbon::today() when the
     * previous next_run_at was in the past, so next_run_at is guaranteed
     * strictly after today once a template is processed — locked in by the
     * pre-existing "a second call must not regenerate" test in
     * Chantier25RecurringOrderTest.php). What was NOT guarded before this
     * fix was true concurrency — two overlapping invocations of this method
     * (a manual `php artisan sales:generate-recurring-orders` racing the
     * scheduled one, which withoutOverlapping() only protects at the
     * scheduler level, not at this method's own level) could both fetch the
     * same due template before either wrote its next_run_at, both generate
     * a real order for the same due cycle. Fixed with a per-template row
     * lock + a re-check of the due condition after acquiring it — a
     * template already advanced past due by a concurrent run is silently
     * skipped rather than double-processed.
     *
     * @return SalesOrder[]
     */
    public function generateDueOrders(): array
    {
        $ids = RecurringOrderTemplate::due()->pluck('id');

        $orders = [];
        foreach ($ids as $id) {
            $order = DB::transaction(function () use ($id) {
                $template = RecurringOrderTemplate::whereKey($id)->lockForUpdate()->first();

                if ($template === null || ! $template->is_active || $template->next_run_at->gt(Carbon::today())) {
                    return null;
                }

                $template->load('lines');

                return $this->generateOrderFromTemplate($template);
            });

            if ($order !== null) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Déclenchement manuel ("Générer maintenant"), indépendamment de la
     * date d'échéance — utile pour un premier envoi immédiat ou un test.
     */
    public function runNow(RecurringOrderTemplate $template): SalesOrder
    {
        return $this->generateOrderFromTemplate($template->load('lines'));
    }

    private function generateOrderFromTemplate(RecurringOrderTemplate $template): SalesOrder
    {
        $order = $this->salesService->createOrder([
            'tenant_id' => $template->tenant_id,
            'contact_id' => $template->contact_id,
            'account_id' => $template->account_id,
            'currency' => $template->currency,
            'notes' => "Générée automatiquement depuis le modèle récurrent {$template->reference}",
            'created_by' => $template->created_by,
            'lines' => $template->lines->map(fn (RecurringOrderTemplateLine $line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_percent' => (float) $line->discount_percent,
                'tax_rate' => (float) $line->tax_rate,
            ])->values()->all(),
        ]);

        $template->update([
            'last_run_at' => now()->toDateString(),
            'next_run_at' => $this->calculateNextRun($template->next_run_at, $template->recurrence),
        ]);

        return $order;
    }

    private function calculateNextRun(Carbon $from, string $recurrence): Carbon
    {
        // Ancré sur l'échéance courante plutôt que sur "aujourd'hui" pour
        // ne pas glisser progressivement si une exécution est manuelle
        // ("Générer maintenant") avant la date prévue.
        $base = $from->isPast() ? Carbon::today() : $from->copy();

        return match ($recurrence) {
            'weekly' => $base->addWeek(),
            'quarterly' => $base->addMonthsNoOverflow(3),
            default => $base->addMonthNoOverflow(), // monthly
        };
    }

    private function syncLines(RecurringOrderTemplate $template, array $lines): void
    {
        $template->lines()->delete();

        foreach ($lines as $sequence => $line) {
            RecurringOrderTemplateLine::create($line + [
                'recurring_order_template_id' => $template->id,
                'sequence' => $sequence,
            ]);
        }
    }

    private function generateReference(): string
    {
        return 'REC-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
    }
}
