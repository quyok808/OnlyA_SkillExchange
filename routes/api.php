<?php

use Illuminate\Http\Request; // Giữ lại
use Illuminate\Support\Facades\Route;

// --- Import Controllers ---
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController; // <<< Namespace Controller Report

// --- Import Middleware ---
use App\Http\Middleware\CheckIsAdmin; // <<< Import Middleware Admin

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================================
// Public Routes
// ==================================
Route::post('/users/register', [UserController::class, 'register']);
Route::post('/users/login', [UserController::class, 'login']);
Route::post('/users/forgot-password', [UserController::class, 'forgotPassword']);
Route::post('/users/reset-password/{token}', [UserController::class, 'resetPassword']);
Route::get('/users/verify-email/{token}', [UserController::class, 'verifyEmail'])->name('verify.email');


// ===========================================
// Protected Routes (Yêu cầu JWT - auth:api)
// ===========================================
Route::middleware('auth:api')->group(function () { // Áp dụng 'auth:api' cho tất cả route bên trong

    // --- User Routes ---
    Route::get('/users', [UserController::class, 'getAllUsers']);
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me', [UserController::class, 'updateMe']);
    Route::post('/users/logout', [UserController::class, 'logout']);
    Route::post('/users/change-password', [UserController::class, 'changePassword']);
    Route::post('/users/upload-avatar', [UserController::class, 'uploadAvatar']);
    Route::get('/users/{id}', [UserController::class, 'get']);
    Route::get('/users/search', [UserController::class, 'searchUser']);
    Route::get('/users/network', [UserController::class, 'searchUserInNetwork']);
    Route::post('/users/skills', [UserController::class, 'addSkillToUser']);


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
        Route::middleware(CheckIsAdmin::class)->group(function() {

            Route::get('/', 'index')->name('index');                 // Lấy danh sách report
            Route::get('/{report}', 'show')->name('show');           // Xem chi tiết report
            Route::put('/change-status/{report}', 'changeStatus')->name('changeStatus'); // Đổi status (route riêng)
            Route::put('/{report}', 'update')->name('update');         // Update report (dùng UpdateReportRequest)
            // Xem xét chuyển Route::delete vào đây nếu chỉ Admin được xóa
            // Route::delete('/{report}', 'destroy')->name('destroy.admin'); // Đổi tên route nếu cần

        }); // Kết thúc nhóm middleware CheckIsAdmin

    }); // Kết thúc nhóm prefix 'reports'
    // --- Kết thúc Report Routes ---


    // --- Các route được bảo vệ khác ---


}); // Kết thúc nhóm middleware 'auth:api'