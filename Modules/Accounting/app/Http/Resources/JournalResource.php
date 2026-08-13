<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\Journal;

/** @mixin Journal */
class JournalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'currency' => $this->currency,
            'default_account_id' => $this->default_account_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
