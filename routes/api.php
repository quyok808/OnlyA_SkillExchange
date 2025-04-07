<?php

use Illuminate\Http\Request; // Giữ lại
use Illuminate\Support\Facades\Route;

// --- Import Controllers ---
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController; // <<< Namespace Controller Report
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
// --- Import Middleware ---
use App\Http\Middleware\CheckIsAdmin; // <<< Import Middleware Admin
use App\Http\Middleware\IsAdmin;
use App\Models\User;

// Public routes
Route::post('/users/register', [UserController::class, 'register']); // Đăng ký
Route::post('/users/login', [UserController::class, 'login']); // Đăng nhập
Route::post('/users/forgot-password', [UserController::class, 'forgotPassword'])->name('password.email');  // Quên mật khẩu
Route::put('/users/reset-password/{token}', [UserController::class, 'resetPassword'])->name('reset.password'); // Đặt lại mật khẩu
Route::get('/users/verify-email/{token}', [UserController::class, 'verifyEmail'])->name('verify.email'); // Xác thực email

// Protected routes (yêu cầu JWT authentication)
Route::middleware('auth:api')->group(function () {
    Route::get('/users/name/{id}', [UserController::class, 'getNameById']);
    Route::get('/users/getUserID', [UserController::class, 'getRelatedUserIds']);
    Route::get('/users/search-user-in-network', [UserController::class, 'searchUserInNetwork']); // Tìm kiếm trong mạng lưới
    Route::get('/users/search', [UserController::class, 'searchUser']); // Tìm kiếm user
    Route::get('/users', [UserController::class, 'getAllUsers']); // Lấy tất cả users
    Route::get('/users/me', [UserController::class, 'me']); // Lấy thông tin user hiện tại
    Route::get('/users/{id}', [UserController::class, 'getUserById']); // Lấy thông tin user theo ID
    Route::post('/users/skills', [UserController::class, 'addSkillToUser']); // Thêm kỹ năng cho user
    Route::get('/users/profile/image', [UserController::class, 'getProfileImage']);
    Route::get('/users/profile/image/{id}', [UserController::class, 'getProfileImageById']);
    Route::put('/users/update-profile', [UserController::class, 'updateMe']); // Cập nhật thông tin user
    Route::post('/users/logout', [UserController::class, 'logout']); // Đăng xuất
    Route::put('/users/change-password', [UserController::class, 'changePassword']); // Đổi mật khẩu
    Route::post('/users/upload-photo', [UserController::class, 'uploadAvatar']); // Tải ảnh đại diện
    Route::put('/users/add-skill', [UserController::class, 'addSkillToUser']); // Thêm kỹ năng cho user

    //Routers connection
    // GET /api/connections (Lấy tất cả liên quan) - Tương đương getAllrequests
    Route::get('/connections', [ConnectionController::class, 'index']);
    // GET /api/connections/pending (Lấy đang chờ)
    Route::get('/connections/pending', [ConnectionController::class, 'pending']);
    // GET /api/connections/accepted (Lấy đã chấp nhận)
    Route::get('/connections/accepted', [ConnectionController::class, 'accepted']);
    // POST /api/connections (Gửi yêu cầu) - Tương đương sendRequest
    Route::post('/connections/request', [ConnectionController::class, 'store']);
    // GET /api/connections/status/{user} (Kiểm tra trạng thái với user cụ thể)
    Route::get('/connections/status/{userID}', [ConnectionController::class, 'status']); // {user} sẽ là ID của người kia
    // PATCH /api/connections/{connection}/accept (Chấp nhận yêu cầu)
    Route::put('/connections/{connection}/accept', [ConnectionController::class, 'accept']);
    // DELETE /api/connections/{connection}/reject (Từ chối yêu cầu - theo logic gốc là xóa)
    Route::put('/connections/{connection}/reject', [ConnectionController::class, 'reject']);
    // DELETE /api/connections/cancel/{receiver} (Hủy yêu cầu đã gửi)
    Route::delete('/connections/cancel/{receiver}', [ConnectionController::class, 'cancel']); // {receiver} là ID người nhận
    // DELETE /api/connections/disconnect (Hủy kết nối đã chấp nhận)
    Route::delete('/connections/disconnect', [ConnectionController::class, 'disconnect']); // Dùng request body như gốc

    //Appointment
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments', [AppointmentController::class, 'myAppointments'])->name('appointments.my');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::apiResource('appointments', AppointmentController::class)->except(['index']);

    // ==============================
    // --- Report Routes ---
    // ==============================
    Route::prefix('reports') // Tiền tố URL: /api/reports/...
        ->name('reports.') // Tiền tố tên route: reports....
        ->controller(ReportController::class) // Chỉ định Controller
        ->group(function () {

            // -- Routes cho mọi user đã đăng nhập --
            Route::post('/', 'store')->name('store');             // Tạo report
            Route::get('/get-warning', 'getWarning')->name('getWarning'); // Lấy cảnh báo
            Route::delete('/{report}', 'destroy')->name('destroy');      // Xóa report (Policy check quyền)

            // -- Routes chỉ dành cho Admin --
            // Áp dụng middleware CheckIsAdmin trực tiếp bằng tên class
            Route::middleware(CheckIsAdmin::class)->group(function () {

                Route::get('/', 'index')->name('index');                 // Lấy danh sách report
                Route::get('/{report}', 'show')->name('show');           // Xem chi tiết report
                Route::put('/change-status/{report}', 'changeStatus')->name('changeStatus'); // Đổi status (route riêng)
                Route::put('/{report}', 'update')->name('update');         // Update report (dùng UpdateReportRequest)
                // Xem xét chuyển Route::delete vào đây nếu chỉ Admin được xóa
                // Route::delete('/{report}', 'destroy')->name('destroy.admin'); // Đổi tên route nếu cần

            }); // Kết thúc nhóm middleware CheckIsAdmin

        }); // Kết thúc nhóm prefix 'reports'
});
// --- Admin Routes (Require JWT Auth + Admin Role) ---
// Áp dụng 'auth:api' trước, sau đó là 'admin' middleware đã tạo
Route::middleware(['auth:api', IsAdmin::class]) // <<<=== THAY ĐỔI Ở ĐÂY
    ->prefix('admins')
    ->name('api.admin.')
    ->group(function () {
        // GET /api/admin/users - Admin lấy TẤT CẢ người dùng
        Route::get('/', [AdminController::class, 'getAllUsers'])->name('users.index');

        // DELETE /api/admin/users/{id} - Admin xóa người dùng
        Route::delete('/{id}', [AdminController::class, 'deleteUser'])->name('users.delete');

        // PATCH /api/admin/users/{id}/lock - Admin khóa/mở khóa người dùng
        Route::put('/lock/{id}', [AdminController::class, 'lockUser'])->name('users.lock');

        // PATCH /api/admin/users/{id}/role - Admin thay đổi vai trò người dùng
        Route::put('/change-role/{id}', [AdminController::class, 'changeRole'])->name('users.role');

        // GET /api/admin/reports/connections - Admin lấy báo cáo kết nối (ví dụ)
        Route::get('/connection-report', [AdminController::class, 'getConnectionReports'])->name('reports.connections');

        // Thêm các route admin khác nếu cần...
    });

// Optional Fallback Route for unmatched API routes
Route::fallback(function () {
    return response()->json(['message' => 'API endpoint not found.'], 404);
});
