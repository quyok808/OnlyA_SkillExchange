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
            // Assuming 'skills' is an array of IDs from the 'skills' table
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => [
                'required',
                'integer',
                'distinct', // Ensure IDs are unique within the array
                'exists:skills,id' // Check if each ID exists in the skills table
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
