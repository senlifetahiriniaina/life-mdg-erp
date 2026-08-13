<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\AccBankFeedTransaction;

/** @mixin AccBankFeedTransaction */
class BankFeedTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feed_id' => $this->feed_id,
            'external_id' => $this->external_id,
            'date' => $this->date->toDateString(),
            'amount' => $this->amount,
            'description' => $this->description,
            'category' => $this->category,
            'merchant' => $this->merchant,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'ai_category_suggestion' => $this->ai_category_suggestion,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
