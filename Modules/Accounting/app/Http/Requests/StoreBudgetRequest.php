<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Budget;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', Budget::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'fiscal_year' => 'required|integer|min:2000',
            'fiscal_year_start' => 'required|date',
            'fiscal_year_end' => 'required|date|after:fiscal_year_start',
            'status' => 'nullable|string|in:draft,active,locked,archived',
            'scenario' => 'nullable|string|in:base,optimistic,pessimistic,stress',
            'parent_budget_id' => 'nullable|integer|exists:acc_budgets,id',
            'total_revenue_budget' => 'nullable|numeric|min:0',
            'total_expense_budget' => 'nullable|numeric|min:0',
        ];
    }
}
