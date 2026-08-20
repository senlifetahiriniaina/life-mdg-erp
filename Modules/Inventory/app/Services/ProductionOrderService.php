<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Str;
use Modules\Inventory\Models\ProductionOrder;

/**
 * Chantier 23 (volet C) — pipeline de statuts d'une commande de production
 * simplifiée. Le pipeline est linéaire (draft → materials_ready →
 * in_subcontracting → quality_check → ready_for_delivery → delivered),
 * avec 'cancelled' atteignable depuis n'importe quel statut non terminal
 * — pas de retour en arrière, pas de saut d'étape, pour rester fidèle au
 * flux métier réel (on ne redevient pas "matières réunies" une fois en
 * sous-traitance).
 */
class ProductionOrderService
{
    public function create(array $data, ?int $userId): ProductionOrder
    {
        return ProductionOrder::create($data + [
            'reference' => $data['reference'] ?? $this->generateReference(),
            'status' => $data['status'] ?? 'draft',
            'created_by' => $userId,
        ]);
    }

    public function update(ProductionOrder $order, array $data): ProductionOrder
    {
        $order->update($data);

        return $order->fresh();
    }

    /**
     * @throws \RuntimeException si la transition ne suit pas le pipeline linéaire.
     */
    public function transition(ProductionOrder $order, string $newStatus): ProductionOrder
    {
        if (! in_array($newStatus, ProductionOrder::STATUSES, true)) {
            throw new \RuntimeException("Statut inconnu : {$newStatus}.");
        }

        if ($order->status === 'delivered' || $order->status === 'cancelled') {
            throw new \RuntimeException("La commande {$order->reference} est déjà dans un état terminal ({$order->status}).");
        }

        if ($newStatus !== 'cancelled') {
            $pipeline = array_values(array_diff(ProductionOrder::STATUSES, ['cancelled']));
            $currentIndex = array_search($order->status, $pipeline, true);
            $targetIndex = array_search($newStatus, $pipeline, true);

            if ($targetIndex !== $currentIndex + 1) {
                throw new \RuntimeException(
                    "Transition invalide : {$order->status} → {$newStatus}. Le pipeline avance une étape à la fois."
                );
            }
        }

        $attributes = ['status' => $newStatus];

        if ($newStatus === 'in_subcontracting' && $order->started_at === null) {
            $attributes['started_at'] = now()->toDateString();
        }

        if ($newStatus === 'delivered') {
            $attributes['delivered_at'] = now()->toDateString();
        }

        $order->update($attributes);

        return $order->fresh();
    }

    private function generateReference(): string
    {
        return 'PRD-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
    }
}
