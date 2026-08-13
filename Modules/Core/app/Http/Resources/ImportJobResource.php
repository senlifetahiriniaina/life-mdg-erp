<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'file_type' => $this->file_type,
            'target_entity' => $this->target_entity,
            'status' => $this->status,
            'column_mapping' => $this->column_mapping,
            'total_rows' => $this->total_rows ?? 0,
            'processed_rows' => $this->processed_rows ?? 0,
            'failed_rows' => $this->failed_rows ?? 0,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
