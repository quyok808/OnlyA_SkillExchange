<?php

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AppointmentController;

// Public routes
Route::post('/users/register', [UserController::class, 'register']); // Đăng ký
Route::post('/users/login', [UserController::class, 'login']); // Đăng nhập
Route::post('/users/forgot-password', [UserController::class, 'forgotPassword'])->name('password.email');  // Quên mật khẩu
Route::put('/users/reset-password/{token}', [UserController::class, 'resetPassword'])->name('reset.password'); // Đặt lại mật khẩu
Route::get('/users/verify-email/{token}', [UserController::class, 'verifyEmail'])->name('verify.email'); // Xác thực email

// Protected routes (yêu cầu JWT authentication)
Route::middleware('auth:api')->group(function () {
    Route::get('/users/search', [UserController::class, 'searchUser']); // Tìm kiếm user
    Route::get('/users', [UserController::class, 'getAllUsers']); // Lấy tất cả users
    Route::get('/users/me', [UserController::class, 'me']); // Lấy thông tin user hiện tại
    Route::get('/users/{id}', [UserController::class, 'getUserById']); // Lấy thông tin user theo ID
    Route::get('/users/network', [UserController::class, 'searchUserInNetwork']); // Tìm kiếm trong mạng lưới
    Route::post('/users/skills', [UserController::class, 'addSkillToUser']); // Thêm kỹ năng cho user
    Route::get('users/profile/image', [UserController::class, 'getProfileImage']);
    Route::get('users/profile/image/{id}', [UserController::class, 'getProfileImageById']);
    Route::put('/users/update-profile', [UserController::class, 'updateMe']); // Cập nhật thông tin user
    Route::post('/users/logout', [UserController::class, 'logout']); // Đăng xuất
    Route::put('/users/change-password', [UserController::class, 'changePassword']); // Đổi mật khẩu
    Route::post('/users/upload-photo', [UserController::class, 'uploadAvatar']); // Tải ảnh đại diện
    Route::put('/users/add-skill', [UserController::class, 'addSkillToUser']); // Thêm kỹ năng cho user
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/my', [AppointmentController::class, 'myAppointments'])->name('appointments.my');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::apiResource('appointments', AppointmentController::class)->except(['index']);
});
