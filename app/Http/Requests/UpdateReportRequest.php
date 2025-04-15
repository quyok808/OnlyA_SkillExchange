<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Models\Report;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');
        return Gate::allows('update', $report);
    }

    /**
     * Rules: Chỉ cho phép update status.
     */
    public function rules(): array
    {
        $validStatuses = ['pending', 'processing', 'reviewed', 'resolved', 'rejected'];

        return [
            'status' => [
                'required',
                'string',
                Rule::in($validStatuses)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng cung cấp trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
