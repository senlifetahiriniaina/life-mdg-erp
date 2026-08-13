<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchTransactionRequest extends FormRequest
{

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'exists:acc_bank_transactions,id'],
            'journal_entry_id' => ['required', 'exists:acc_journal_entries,id'],
        ];
    }
}
