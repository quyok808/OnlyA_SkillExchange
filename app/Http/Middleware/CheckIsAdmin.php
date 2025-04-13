<?php

namespace App\Http\Middleware; // <<< Namespace đúng

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // <<< Import Auth
use Symfony\Component\HttpFoundation\Response;

class CheckIsAdmin // <<< Tên class đúng
{
    /**
     * Handle an incoming request.
     * Kiểm tra xem người dùng đã đăng nhập có quyền admin hay không.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Kiểm tra đăng nhập (có thể thừa nếu 'auth:api' chạy trước, nhưng an toàn)
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. Kiểm tra vai trò Admin
        // !!! SỬA LẠI ĐIỀU KIỆN NÀY CHO PHÙ HỢP VỚI MODEL USER CỦA BẠN !!!
        // Ví dụ: Dựa vào cột 'role'
        if (Auth::user()->role !== 'admin') {
            // Nếu không phải admin, trả về lỗi 403 Forbidden
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        // Nếu là admin, cho phép request đi tiếp đến controller/route tiếp theo
        return $next($request);
    }
}