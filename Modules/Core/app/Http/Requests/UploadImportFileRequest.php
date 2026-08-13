<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,xlsx,pdf,png,jpg,jpeg'],
            'target_entity' => ['required', 'string', 'in:contact,lead,product,employee,supplier,invoice'],
        ];
    }
}
