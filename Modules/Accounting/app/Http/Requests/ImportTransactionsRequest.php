<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportTransactionsRequest extends FormRequest
{

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.description' => ['required', 'string'],
            'transactions.*.amount' => ['required', 'numeric'],
            'transactions.*.type' => ['nullable', 'in:debit,credit'],
            'transactions.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
