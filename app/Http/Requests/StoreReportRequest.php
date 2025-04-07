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
        return Auth::check(); // Cho phép mọi user đăng nhập tạo report
    }

    /**
     * Rules: Validate 'userId' (người bị báo cáo) và 'reason'.
     */
    public function rules(): array
    {
        $reporterId = Auth::id(); // Lấy ID người báo cáo

        return [
            // Key 'userId' trong request body là ID của người BỊ báo cáo
            'userId' => [
                'required',
                'string',           // UUID là string
                Rule::exists('users', 'id'), // Phải tồn tại user này
                function ($attribute, $value, $fail) use ($reporterId) {
                    if ($value == $reporterId) { // Không cho tự báo cáo
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