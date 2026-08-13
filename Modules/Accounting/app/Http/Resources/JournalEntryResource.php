<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\JournalEntry;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'description' => $this->description,
            'entry_date' => $this->entry_date?->toDateString(),
            'status' => $this->status,
            'total_debit' => $this->total_debit,
            'total_credit' => $this->total_credit,
            'journal' => $this->whenLoaded('journal', fn () => $this->journal ? [
                'id' => $this->journal->id,
                'name' => $this->journal->name,
                'code' => $this->journal->code,
            ] : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'id' => $l->id,
                'account_id' => $l->account_id,
                'description' => $l->description,
                'debit' => $l->debit,
                'credit' => $l->credit,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
