<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => [
                'required',
                'integer',
                'distinct',
                'exists:skills,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'skills.*.exists' => 'One or more selected skills are invalid.',
            'skills.*.distinct' => 'Duplicate skills are not allowed.',
        ];
    }
}
