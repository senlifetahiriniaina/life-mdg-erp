<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Journal;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', Journal::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:acc_journals,code'],
            'type' => ['required', 'in:sale,purchase,cash,bank,general'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_account_id' => ['nullable', 'exists:acc_chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
