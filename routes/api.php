<?php

use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\CenterAdminController;
use App\Http\Controllers\TeacherManagementController;
use App\Http\Controllers\CenterAdminManagementController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\GroupStudentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\TeacherStatsController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AiInsightsController;
use App\Http\Controllers\Api\Ai\ParentAiController;
use App\Http\Controllers\Api\Ai\StudentAiController;
use App\Http\Controllers\Api\Ai\CenterAiController;
use App\Http\Controllers\ContactController;

// Public contact route (no auth required)
Route::post('/contact', [ContactController::class, 'store']);

// Admin contacts route (requires auth + admin role)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/contacts', [ContactController::class, 'index']);

    // Center Admin Approval Management
    Route::get('/admin/pending-centers', [App\Http\Controllers\AdminCenterApprovalController::class, 'index']);
    Route::post('/admin/centers/{user}/approve', [App\Http\Controllers\AdminCenterApprovalController::class, 'approve']);
    Route::post('/admin/centers/{user}/reject', [App\Http\Controllers\AdminCenterApprovalController::class, 'reject']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
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
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    // AI helpers
    Route::post('/ai/chat', [AiChatController::class, 'chat']);
    Route::get('/ai/insights', [AiInsightsController::class, 'insights']);
    Route::get('/ai/parent/weekly-summary', [ParentAiController::class, 'weeklySummary']);
    Route::post('/ai/parent/explain', [ParentAiController::class, 'explain']);
    Route::post('/ai/student/generate-quiz', [StudentAiController::class, 'generateQuiz']);
    Route::post('/ai/student/summary', [StudentAiController::class, 'summary']);
    Route::post('/ai/student/study-plan', [StudentAiController::class, 'studyPlan']);
    Route::get('/ai/center/insights', [CenterAiController::class, 'insights']);
    Route::get('/ai/center/attendance-forecast', [CenterAiController::class, 'attendanceForecast']);

    // Teacher/Center Admin Stats
    Route::get('/teacher/stats', [TeacherStatsController::class, 'stats'])
        ->middleware('role:teacher|assistant|center_admin');

    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::get('/{user}', [TeacherController::class, 'show']);
    });

    // User management routes
    Route::apiResource('users', UserController::class)->middleware('role:admin');
    Route::apiResource('centers', CenterController::class)->middleware('role:admin');
    Route::patch('centers/{center}/toggle-status', [CenterController::class, 'toggleStatus'])
        ->middleware('role:admin');
    Route::apiResource('groups', GroupController::class);
    Route::get('/admin/stats', [AdminDashboardController::class, 'index'])->middleware('role:admin');

    // Student & Parent dashbaord
    Route::middleware('role:student')->controller(StudentDashboardController::class)->prefix('dashboard/student/')->group(function () {
        Route::get('home', 'overView');
        Route::get('groups', 'studentGroups');
        Route::get('groups/{group}', 'groupOverview');
        Route::get('assessments', 'studentAssignments');
    });

    // adding or removing roles from user
    Route::post('/users/{user}/roles', [UserController::class, 'assignRole'])->middleware('role:admin');
    Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole'])->middleware('role:admin');

    // Lessons within groups (teachers/assistants/center_admin/admin)
    Route::get('/groups/{group}/lessons', [LessonController::class, 'index']);
    Route::post('/groups/{group}/lessons', [LessonController::class, 'store']);
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    Route::put('/lessons/{lesson}', [LessonController::class, 'update']);
    Route::patch('/lessons/{lesson}', [LessonController::class, 'update']);
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
    Route::post('/lessons/{lesson}/assessments', [App\Http\Controllers\AssessmentController::class, 'store']);
    Route::get('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'show']);
    Route::put('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'update']);
    Route::delete('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'destroy']);
    Route::post('/assessments/{assessment}/results', [App\Http\Controllers\AssessmentController::class, 'storeResult']);

    // Group students management (teachers/assistants/center_admin/admin)
    Route::get('/groups/{group}/students', [GroupStudentController::class, 'index']);
    Route::post('/groups/{group}/students', [GroupStudentController::class, 'store']);

    // Attendance management
    Route::get('/groups/{group}/attendance', [AttendanceController::class, 'index']);
    Route::post('/groups/{group}/attendance', [AttendanceController::class, 'store']);

    // Teacher Management Routes (NEW)
    Route::prefix('teacher-management')->middleware('role:teacher|assistant')->group(function () {
        Route::post('/groups', [TeacherManagementController::class, 'storeGroup']);
        Route::post('/users', [TeacherManagementController::class, 'storeUser']);
    });

    // Center Admin Management Routes (NEW)
    Route::prefix('center-admin/management')->middleware('role:center_admin')->group(function () {
        Route::get('/stats', [CenterAdminManagementController::class, 'stats']);
        Route::get('/users', [CenterAdminManagementController::class, 'getUsers']);
        Route::post('/users', [CenterAdminManagementController::class, 'storeUser']);
        Route::put('/users/{user}', [CenterAdminManagementController::class, 'updateUser']);
        Route::delete('/users/{user}', [CenterAdminManagementController::class, 'destroyUser']);
    });

    // Center Admin scoped APIs (EXISTING)
    Route::prefix('center-admin')->middleware('role:center_admin')->group(function () {
        // All members in this center (teachers, assistants, students, parents)
        Route::get('/members', [CenterAdminController::class, 'members']);

        // Center groups
        Route::get('/groups', [CenterAdminController::class, 'groups']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

        // Create staff & students for this center
        Route::post('/teachers', [CenterAdminController::class, 'storeTeacher']);
        Route::post('/assistants', [CenterAdminController::class, 'storeAssistant']);
        Route::post('/students', [CenterAdminController::class, 'storeStudent']);

        // Delete staff & students from this center
        Route::delete('/teachers/{user}', [CenterAdminController::class, 'destroyTeacher']);
        Route::delete('/assistants/{user}', [CenterAdminController::class, 'destroyAssistant']);
        Route::delete('/students/{user}', [CenterAdminController::class, 'destroyStudent']);
    });

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
