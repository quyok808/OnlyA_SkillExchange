<?php

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ConnectionController;

// Public routes
Route::post('/users/register', [UserController::class, 'register']); // Đăng ký
Route::post('/users/login', [UserController::class, 'login']); // Đăng nhập
Route::post('/users/forgot-password', [UserController::class, 'forgotPassword']); // Quên mật khẩu
Route::post('/users/reset-password/{token}', [UserController::class, 'resetPassword']); // Đặt lại mật khẩu
Route::get('/users/verify-email/{token}', [UserController::class, 'verifyEmail'])->name('verify.email'); // Xác thực email
// Protected routes (yêu cầu JWT authentication)
Route::middleware('auth:api')->group(function () {
    Route::get('/users', [UserController::class, 'getAllUsers']); // Lấy tất cả users
    Route::get('/users/me', [UserController::class, 'me']); // Lấy thông tin user hiện tại
    Route::put('/users/me', [UserController::class, 'updateMe']); // Cập nhật thông tin user
    Route::post('/users/logout', [UserController::class, 'logout']); // Đăng xuất
    Route::post('/users/change-password', [UserController::class, 'changePassword']); // Đổi mật khẩu
    Route::post('/users/upload-avatar', [UserController::class, 'uploadAvatar']); // Tải ảnh đại diện
    Route::get('/users/{id}', [UserController::class, 'get']); // Lấy thông tin user theo ID
    Route::get('/users/search', [UserController::class, 'searchUser']); // Tìm kiếm user
    Route::get('/users/network', [UserController::class, 'searchUserInNetwork']); // Tìm kiếm trong mạng lưới
    Route::post('/users/skills', [UserController::class, 'addSkillToUser']); // Thêm kỹ năng cho user

    //Routers connection
    Route::post('/connections/send-request', [ConnectionController::class, 'sendRequest']); // Gửi yêu cầu kết bạn
    Route::post('/connections/accept-request/{connection_id}', [ConnectionController::class, 'acceptRequest']); // Chấp nhận kết bạn
    Route::post('/connections/decline-request/{connection_id}', [ConnectionController::class, 'declineRequest']); // Từ chối kết bạn
    Route::post('/connections/cancel-request', [ConnectionController::class, 'cancelRequest']); // Hủy yêu cầu kết bạn
    Route::post('/connections/remove-friend', [ConnectionController::class, 'removeFriend']); // Xóa bạn
});
