<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Journal;

class UpdateJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('journal'));
    }

    public function rules(): array
    {
        $journalId = $this->route('journal')?->id ?? 0;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', "unique:acc_journals,code,{$journalId}"],
            'type' => ['sometimes', 'in:sale,purchase,cash,bank,general'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_account_id' => ['nullable', 'exists:acc_chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
