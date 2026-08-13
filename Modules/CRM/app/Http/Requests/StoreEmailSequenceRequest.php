<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'trigger_event' => ['sometimes', 'string', 'in:manual,lead_created,lead_status_changed,opportunity_created,contact_created'],
            'trigger' => ['sometimes', 'string', 'in:manual,lead_created,lead_status_changed,opportunity_created'],
            'trigger_conditions' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'in:draft,active,paused,archived'],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer', 'min:1'],
            'steps.*.delay_days' => ['sometimes', 'integer', 'min:0'],
            'steps.*.delay_hours' => ['sometimes', 'integer', 'min:0'],
            'steps.*.subject' => ['required', 'string'],
            'steps.*.body_html' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'steps.required' => 'At least one sequence step is required.',
            'steps.*.subject.required' => 'Each step must have a subject.',
            'steps.*.body_html.required' => 'Each step must have a body.',
        ];
    }
}
