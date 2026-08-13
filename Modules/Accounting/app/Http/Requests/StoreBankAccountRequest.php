<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\BankAccount;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', BankAccount::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        return [
            'name' => array_filter([$isUpdate ? 'sometimes' : null, 'required', 'string', 'max:255']),
            'bank_name' => array_filter([$isUpdate ? 'sometimes' : null, 'required', 'string', 'max:255']),
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'currency' => ['nullable', 'string', 'size:3'],
            'gl_account_id' => ['nullable', 'exists:acc_chart_of_accounts,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
