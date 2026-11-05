<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:200'],
            'steps' => ['required', 'array', 'min:1', 'max:30'],
            'steps.*.name' => ['required', 'string', 'max:160'],
            'steps.*.permission' => ['required', 'string', 'max:120', 'exists:iam_permissions,code'],
        ];
    }
}
