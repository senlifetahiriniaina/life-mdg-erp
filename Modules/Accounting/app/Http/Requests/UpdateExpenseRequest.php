<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Expense;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('expense'));
    }

    public function rules(): array
    {
        return [
            'gl_account_id' => 'nullable|exists:acc_gl_accounts,id',
            'expense_date' => 'nullable|date',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
        ];
    }
}
