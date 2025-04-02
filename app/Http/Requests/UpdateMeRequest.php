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
            'phone' => ['nullable', 'string', 'unique:users,phone', 'max:10', 'min:10'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Số điện thoại này đã được sử dụng bởi người khác.',
            'phone.min' => 'Số điện thoại phải có độ dài 10 số.',
            'phone.max' => 'Số điện thoại phải có độ dài 10 số.',
        ];
    }
}
