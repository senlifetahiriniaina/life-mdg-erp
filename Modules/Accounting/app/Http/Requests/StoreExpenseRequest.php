<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Expense;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'expense_reference' => 'required|string|unique:acc_expenses,expense_reference',
            'gl_account_id' => 'required|exists:acc_gl_accounts,id',
            'expense_date' => 'required|date',
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
        ];
    }
}
