<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ensure the user is authenticated
        return auth('api')->check();
    }

    public function rules(): array
    {
        // Define rules for fields allowed in updateMe
        // Avoid allowing email/password changes here
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:10', 'min:10'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
