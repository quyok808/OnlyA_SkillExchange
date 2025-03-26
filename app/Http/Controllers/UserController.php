<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Carbon;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\RegisterRequest;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(RegisterRequest $request)
    {
        try {
            $result = $this->userService->register($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            Log::error('Registration error: ' . $e->getMessage(), ['code' => $statusCode]); // Log lỗi
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
    public function login(LoginRequest $request)
    {
        try {
            $token = $this->userService->login($request->validated());
            return response()->json(['status' => 'success', 'token' => $token], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            $result = $this->userService->getAllUsers($request->all());
            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], 400);
        }
    }


    public function verifyEmail($token)
    {
        try {
            $this->userService->verifyEmail($token);
            return response()->json(['status' => 'success', 'message' => 'Email verified successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = $this->userService->me();
            return response()->json([
                'status' => 'success',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], $e->getCode() ?: 400);
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->userService->logout();
            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadAvatar(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $photoUrl = $this->userService->uploadAvatar($user, $request->all());

            return response()->json(['status' => 'success', 'message' => 'Photo uploaded successfully.', 'photo_url' => $photoUrl], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;

        // Tạo token
        $token = Str::random(60);
        // Tìm user
        $user = User::where('email', $email)->first();
        // Cập nhật token và thời gian hết hạn vào DB
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()
        ]);
        // Gửi email cho user với token
        Mail::to($email)->send(new ResetPasswordMail($token, $email));

        return response()->json(['status' => 'success', 'message' => 'Reset password email sent.'], 200);
    }
    public function resetPassword(Request $request, string $token)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $email = $request->email;

        // Tìm user
        $user = User::where('email', $email)->first();
        // Tìm token trong DB
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset || !Hash::check($token, $passwordReset->token)) {
            return response()->json(['message' => 'Invalid reset password token'], 400);
        }

        // Cập nhật password cho user và xóa token
        $user->password = Hash::make($request->password);
        $user->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }
}
