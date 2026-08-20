<?php

declare(strict_types=1);

namespace Modules\Sales\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Services\RecurringOrderService;

/**
 * Chantier 25 (volet E) — génère une vraie SalesOrder pour chaque modèle
 * de commande récurrente actif dont l'échéance est atteinte ou dépassée.
 */
class GenerateRecurringOrdersCommand extends Command
{
    protected $signature = 'sales:generate-recurring-orders';

    protected $description = 'Génère les commandes de vente dues depuis les modèles de commandes récurrentes actifs';

    public function handle(RecurringOrderService $service): int
    {
        $orders = $service->generateDueOrders();

        $this->info(count($orders) . ' commande(s) générée(s) depuis des modèles récurrents.');

        return self::SUCCESS;
    }
}
