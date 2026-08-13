<?php

declare(strict_types=1);

namespace Modules\BI\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'status' => $this->status, 'created_at' => $this->created_at?->toISOString(), '_links' => ['self' => url("/api/v1/bi/dashboards/{$this->id}")]];
    }
}
