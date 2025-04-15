<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckIsAdmin
{
    /**
     * Handle an incoming request.
     * Kiểm tra xem người dùng đã đăng nhập có quyền admin hay không.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Kiểm tra đăng nhập
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. Kiểm tra vai trò Admin
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        return $next($request);
    }
}
