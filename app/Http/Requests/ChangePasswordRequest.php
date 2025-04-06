<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash; // Import Hash
use Illuminate\Validation\Rules\Password; // Import Password rule

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'passwordCurrent' => [
                'required',
                'string',
                // Custom rule to check if current password matches DB
                function ($attribute, $value, $fail) {
                    if (!Hash::check($value, auth('api')->user()->password)) {
                        $fail('The :attribute is incorrect.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                Password::min(8) // Use Laravel's built-in password rules (recommended)
                    ->mixedCase() // Optional: Require letters and numbers
                    ->numbers()   // Optional: Require numbers
                    ->symbols(),  // Optional: Require symbols
                'different:passwordCurrent',
            ],
            'confirmPassword' => 'required|string|same:password',
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_password.different' => 'The new password must be different from the current password.',
        ];
    }
}
