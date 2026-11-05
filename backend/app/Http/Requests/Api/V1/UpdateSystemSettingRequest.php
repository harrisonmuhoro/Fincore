<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_type' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'key' => ['sometimes', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'scope_id' => ['required', 'string', 'max:120'],
            'value' => ['present'],
            'value_type' => ['required', 'in:string,integer,boolean,number,object,array'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
