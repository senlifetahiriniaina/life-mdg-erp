<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'created_at' => $this->created_at?->toISOString(), '_links' => ['self' => url("/api/v1/users/{$this->id}")]];
    }
}
