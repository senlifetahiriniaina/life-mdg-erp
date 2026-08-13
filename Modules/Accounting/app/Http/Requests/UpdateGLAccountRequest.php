<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\GLAccount;

class UpdateGLAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('glAccount'));
    }

    public function rules(): array
    {
        return [
            'account_number' => 'nullable|string|unique:acc_gl_accounts,account_number,'.$this->glAccount->id,
            'account_name' => 'nullable|string',
            'account_type' => 'nullable|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'nullable|in:debit,credit',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
