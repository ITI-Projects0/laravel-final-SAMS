<?php

use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\NotificationController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/send-reset-code', [AuthController::class, 'sendResetCode']);
    Route::post('/validate-reset-code', [AuthController::class, 'validateResetCode']); // New secure endpoint
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/exchange-token', [AuthController::class, 'exchangeToken']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);


    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::get('/{user}', [TeacherController::class, 'show']);
    });

    // User management routes
    Route::apiResource('users', UserController::class)->middleware('role:admin');
    Route::apiResource('centers', CenterController::class)->middleware('role:admin');
    Route::apiResource('groups', GroupController::class);
    Route::get('/admin/stats', [AdminDashboardController::class, 'index'])->middleware('role:admin');

    // adding or removing roles from user
    Route::post('/users/{user}/roles', [UserController::class, 'assignRole']);
    Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole'])->middleware('role:admin');

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/latest', [NotificationController::class, 'latest']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
    });
});
