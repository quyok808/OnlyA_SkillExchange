<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User; // Import User

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Rules: Validate 'userId' (người bị báo cáo) và 'reason'.
     */
    public function rules(): array
    {
        $reporterId = Auth::id();

        return [
            'userId' => [
                'required',
                'string',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) use ($reporterId) {
                    if ($value == $reporterId) {
                        $fail('Bạn không thể tự báo cáo chính mình.');
                    }
                },
            ],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'userId.required' => 'Vui lòng cung cấp ID người dùng bị báo cáo.',
            'userId.string' => 'ID người dùng không hợp lệ.',
            'userId.exists' => 'Người dùng bị báo cáo không tồn tại.',
            'reason.required' => 'Vui lòng nhập lý do báo cáo.',
            'reason.max' => 'Lý do không được vượt quá :max ký tự.',
        ];
    }
}
