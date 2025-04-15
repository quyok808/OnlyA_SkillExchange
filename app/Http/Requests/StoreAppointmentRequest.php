<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth; /* ... use statements ... */

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'receiverId' => ['required', 'string', Rule::exists('users', 'id')],
            'startTime' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'endTime' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after:startTime'],
            'description' => ['required', 'string', 'max:65535'],
        ];
    }
}
