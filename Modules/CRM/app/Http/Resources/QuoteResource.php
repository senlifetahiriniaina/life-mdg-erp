<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Quote;

/** @mixin Quote */
class QuoteResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'total' => (string) $this->total,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'full_name' => trim($this->contact->first_name.' '.$this->contact->last_name),
                'email' => $this->contact->email,
            ] : null),

            'opportunity' => $this->whenLoaded('opportunity', fn () => $this->opportunity ? [
                'id' => $this->opportunity->id,
                'name' => $this->opportunity->name,
            ] : null),

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_bundle_id' => $line->product_bundle_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount_pct' => (string) $line->discount_pct,
                'line_total' => (string) $line->line_total,
                'product_bundle' => $line->productBundle ? [
                    'id' => $line->productBundle->id,
                    'name' => $line->productBundle->name,
                ] : null,
            ])),
        ];
    }
}
