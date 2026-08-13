<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\AccOpenBankingConnection;

/** @mixin AccOpenBankingConnection */
class OpenBankingConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'status' => $this->status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'external_account_ids' => $this->external_account_ids,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'feeds_count' => $this->whenCounted('feeds'),
        ];
    }
}
