<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $budgetedAmount = (float) ($this->total_revenue_budget ?? 0);
        $utilization = $budgetedAmount > 0
            ? round(((float) ($this->total_expense_budget ?? 0) / $budgetedAmount) * 100, 2)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'fiscal_year' => $this->fiscal_year,
            'fiscal_year_start' => $this->fiscal_year_start,
            'fiscal_year_end' => $this->fiscal_year_end,
            'status' => $this->status,
            'scenario' => $this->scenario,
            'parent_budget_id' => $this->parent_budget_id,
            'total_revenue_budget' => (float) ($this->total_revenue_budget ?? 0),
            'total_expense_budget' => (float) ($this->total_expense_budget ?? 0),
            'utilization_percent' => $utilization,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
