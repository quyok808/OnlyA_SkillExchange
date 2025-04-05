<?php

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController; 
use App\Http\Middleware\IsAdmin;
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
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/my', [AppointmentController::class, 'myAppointments'])->name('appointments.my');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::apiResource('appointments', AppointmentController::class)->except(['index']);
});
// --- Admin Routes (Require JWT Auth + Admin Role) ---
// Áp dụng 'auth:api' trước, sau đó là 'admin' middleware đã tạo
Route::middleware(['auth:api', IsAdmin::class]) // <<<=== THAY ĐỔI Ở ĐÂY
      ->prefix('admin')
      ->name('api.admin.')
      ->group(function () {
    // GET /api/admin/users - Admin lấy TẤT CẢ người dùng
    Route::get('/users', [AdminController::class, 'getAllUsers'])->name('users.index');

    // DELETE /api/admin/users/{id} - Admin xóa người dùng
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('users.delete');

    // PATCH /api/admin/users/{id}/lock - Admin khóa/mở khóa người dùng
    Route::patch('/users/{id}/lock', [AdminController::class, 'lockUser'])->name('users.lock');

    // PATCH /api/admin/users/{id}/role - Admin thay đổi vai trò người dùng
    Route::patch('/users/{id}/role', [AdminController::class, 'changeRole'])->name('users.role');

    // GET /api/admin/reports/connections - Admin lấy báo cáo kết nối (ví dụ)
    Route::get('/reports/connections', [AdminController::class, 'getConnectionReports'])->name('reports.connections');

    // Thêm các route admin khác nếu cần...
});

// Optional Fallback Route for unmatched API routes
Route::fallback(function(){
    return response()->json(['message' => 'API endpoint not found.'], 404);
});
