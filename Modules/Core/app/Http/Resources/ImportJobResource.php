<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportJobResource extends JsonResource
{
    /**
     * Chantier 32.1: used to read $this->processed_rows/failed_rows/
     * error_message — none of which have ever existed as real attributes on
     * ImportJob (the real underlying model attributes are processed/failed/
     * errors, see ImportJob's own docblock) — Eloquent's magic __get()
     * silently returns null for an unknown attribute rather than throwing,
     * so this always resolved through the `?? 0` fallback and every real
     * job's progress bar/counts have always shown 0/0 regardless of actual
     * progress. The JSON key names below (processed_rows/failed_rows/
     * error_message) are kept as-is — that's the real, already-built
     * frontend's contract (resources/js/Pages/Import/Index.vue) — only the
     * source attribute read on the right-hand side changed. ai_suggestions
     * is newly exposed here since ExtractAndMapImportJob now actually
     * populates it (see that job's own docblock).
     *
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
            'ai_suggestions' => $this->ai_suggestions,
            'total_rows' => $this->total_rows ?? 0,
            'processed_rows' => $this->processed ?? 0,
            'failed_rows' => $this->failed ?? 0,
            'error_message' => $this->errors,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
