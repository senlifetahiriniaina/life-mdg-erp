<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\GLAccount;

class StoreGLAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', GLAccount::class);
    }

    public function rules(): array
    {
        return [
            'account_number' => 'required|string|unique:acc_gl_accounts,account_number',
            'account_name' => 'required|string',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'description' => 'nullable|string',
        ];
    }
}
