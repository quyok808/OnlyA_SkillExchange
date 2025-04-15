<?php

use Illuminate\Http\Request; // Giữ lại
use Illuminate\Support\Facades\Route;

// --- Import Controllers ---
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\MessageController;
// --- Import Middleware ---
use App\Http\Middleware\CheckIsAdmin;
use App\Http\Middleware\IsAdmin;

// Public routes
Route::post('/users/register', [UserController::class, 'register']);
Route::post('/users/login', [UserController::class, 'login']);
Route::post('/users/forgot-password', [UserController::class, 'forgotPassword'])->name('password.email');
Route::put('/users/reset-password/{token}', [UserController::class, 'resetPassword'])->name('reset.password');
Route::get('/users/verify-email/{token}', [UserController::class, 'verifyEmail'])->name('verify.email');

// Protected routes (yêu cầu JWT authentication)
Route::middleware('auth:api')->group(function () {
    Route::get('/users/name/{id}', [UserController::class, 'getNameById']);
    Route::get('/users/getUserID', [UserController::class, 'getRelatedUserIds']);
    Route::get('/users/search-user-in-network', [UserController::class, 'searchUserInNetwork']);
    Route::get('/users/search', [UserController::class, 'searchUser']);
    Route::get('/users', [UserController::class, 'getAllUsers']);
    Route::get('/users/me', [UserController::class, 'me']);
    Route::get('/users/{id}', [UserController::class, 'getUserById']);
    Route::post('/users/skills', [UserController::class, 'addSkillToUser']);
    Route::get('/users/profile/image', [UserController::class, 'getProfileImage']);
    Route::get('/users/profile/image/{id}', [UserController::class, 'getProfileImageById']);
    Route::put('/users/update-profile', [UserController::class, 'updateMe']);
    Route::post('/users/logout', [UserController::class, 'logout']);
    Route::put('/users/change-password', [UserController::class, 'changePassword']);
    Route::post('/users/upload-photo', [UserController::class, 'uploadAvatar']);
    Route::put('/users/add-skill', [UserController::class, 'addSkillToUser']);

    //Connection
    Route::get('/connections', [ConnectionController::class, 'index']);
    Route::get('/connections/pending', [ConnectionController::class, 'pending']);
    Route::get('/connections/accepted', [ConnectionController::class, 'accepted']);
    Route::post('/connections/request', [ConnectionController::class, 'store']);
    Route::get('/connections/status/{userID}', [ConnectionController::class, 'status']); // {user} sẽ là ID của người kia
    Route::put('/connections/{connection}/accept', [ConnectionController::class, 'accept']);
    Route::put('/connections/{connection}/reject', [ConnectionController::class, 'reject']);
    Route::delete('/connections/cancel/{receiver}', [ConnectionController::class, 'cancel']);
    Route::delete('/connections/disconnect', [ConnectionController::class, 'disconnect']);

    //Appointment
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments', [AppointmentController::class, 'myAppointments'])->name('appointments.my');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::apiResource('appointments', AppointmentController::class)->except(['index']);

    // Message routes
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::get('/messages/{chatRoomId}', [MessageController::class, 'getMessages']);

    // ==============================
    // --- Report Routes ---
    // ==============================
    Route::prefix('reports')
        ->name('reports.')
        ->controller(ReportController::class)
        ->group(function () {

            // -- Routes cho mọi user đã đăng nhập --
            Route::post('/', 'store')->name('store');
            Route::get('/get-warning', 'getWarning')->name('getWarning');
            Route::delete('/{report}', 'destroy')->name('destroy');
            Route::put('/change-status/{report}', 'changeStatus')->name('changeStatus');
            // -- Routes chỉ dành cho Admin --
            // Áp dụng middleware CheckIsAdmin trực tiếp bằng tên class
            Route::middleware(CheckIsAdmin::class)->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/{report}', 'show')->name('show');

                Route::put('/{report}', 'update')->name('update');
            }); // Kết thúc nhóm middleware CheckIsAdmin

        });
});
// --- Admin Routes (Require JWT Auth + Admin Role) ---
// Áp dụng 'auth:api' trước, sau đó là 'admin' middleware đã tạo
Route::middleware(['auth:api', IsAdmin::class])
    ->prefix('admins')
    ->name('api.admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'getAllUsers'])->name('users.index');
        Route::delete('/{id}', [AdminController::class, 'deleteUser'])->name('users.delete');
        Route::put('/lock/{id}', [AdminController::class, 'lockUser'])->name('users.lock');
        Route::put('/change-role/{id}', [AdminController::class, 'changeRole'])->name('users.role');
        Route::get('/connection-report', [AdminController::class, 'getConnectionReports'])->name('reports.connections');
    });

Route::fallback(function () {
    return response()->json(['message' => 'API endpoint not found.'], 404);
});
