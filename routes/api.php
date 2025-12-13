<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\GroupStudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherStatsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\CenterAdminController;
use App\Http\Controllers\TeacherManagementController;
use App\Http\Controllers\CenterAdminManagementController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AiInsightsController;
use App\Http\Controllers\Api\Ai\ParentAiController;
use App\Http\Controllers\Api\Ai\StudentAiController;
use App\Http\Controllers\Api\Ai\CenterAiController;


// ==============================================
// Public Endpoints
// ==============================================

// Contact form (public)
Route::post('/contact', [ContactController::class, 'store']); // Public contact form submission


// ==============================================
// Authentication Routes
// ==============================================
Route::prefix('auth')->group(function () {

    // ---- Public auth actions ----
    Route::post('/register', [AuthController::class, 'register']);     // User registration
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // Login with rate limiting
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']); // Verify email
    Route::post('/send-reset-code', [AuthController::class, 'sendResetCode']); // Send reset code
    Route::post('/validate-reset-code', [AuthController::class, 'validateResetCode']); // Validate secure reset code
    Route::post('/reset-password', [AuthController::class, 'resetPassword']); // Reset password
    Route::post('/exchange-token', [AuthController::class, 'exchangeToken']); // Short-lived token exchange

    // ---- Authenticated auth actions ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']); // Logout
    });
});


// ==============================================
// Authenticated Routes
// ==============================================
Route::middleware('auth:sanctum')->group(function () {

    // ==========================================
    // Profile Management
    // ==========================================
    Route::get('/me', [AuthController::class, 'me']);                     // Get current user
    Route::put('/me', [AuthController::class, 'updateProfile']);          // Update profile
    Route::put('/me/password', [AuthController::class, 'updatePassword']); // Update password


    // ==========================================
    // AI Features
    // ==========================================
    Route::post('/ai/chat', [AiChatController::class, 'chat']);                // General AI assistant
    Route::get('/ai/insights', [AiInsightsController::class, 'insights']);     // AI analytics insights
    Route::get('/ai/parent/weekly-summary', [ParentAiController::class, 'weeklySummary']); // Parent weekly summary
    Route::post('/ai/parent/explain', [ParentAiController::class, 'explain']); // Explain student performance
    Route::post('/ai/student/generate-quiz', [StudentAiController::class, 'generateQuiz']); // AI quiz
    Route::post('/ai/student/summary', [StudentAiController::class, 'summary']); // Lesson summary
    Route::post('/ai/student/study-plan', [StudentAiController::class, 'studyPlan']); // Study plan
    Route::get('/ai/center/insights', [CenterAiController::class, 'insights']); // Center analytics
    Route::get('/ai/center/attendance-forecast', [CenterAiController::class, 'attendanceForecast']); // Attendance prediction


    // ==========================================
    // Teacher Stats
    // ==========================================
    Route::get('/teacher/stats', [TeacherStatsController::class, 'stats'])
        ->middleware('role:teacher|assistant|center_admin'); // Stats for teaching roles


    // ==========================================
    // Teacher Directory (Read-only)
    // ==========================================
    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']); // List all teachers
        Route::get('/{user}', [TeacherController::class, 'show']); // View teacher details
    });


    // ==========================================
    // Admin: Users & Centers Management
    // ==========================================
    Route::middleware('role:admin')->group(function () {

        Route::apiResource('users', UserController::class); // Full user CRUD (admin only)
        Route::apiResource('centers', CenterController::class); // Full center CRUD
        Route::patch('centers/{center}/toggle-status', [CenterController::class, 'toggleStatus']); // Activate/deactivate center
        Route::get('/admin/stats', [AdminDashboardController::class, 'index']); // Admin dashboard stats

        // Admin review center applications
        Route::get('/admin/pending-centers', [App\Http\Controllers\AdminCenterApprovalController::class, 'index']);
        Route::post('/admin/centers/{user}/approve', [App\Http\Controllers\AdminCenterApprovalController::class, 'approve']);
        Route::post('/admin/centers/{user}/reject', [App\Http\Controllers\AdminCenterApprovalController::class, 'reject']);

        // Manage roles assigned to a user
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole']);
        Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole']);
    });


    // ==========================================
    // Group Management (Shared by multiple roles)
    // ==========================================
    Route::apiResource('groups', GroupController::class); // CRUD groups


    // ==========================================
    // Lessons & Assessments
    // ==========================================
    Route::get('/groups/{group}/lessons', [LessonController::class, 'index']); // Lessons in a group
    Route::post('/groups/{group}/lessons', [LessonController::class, 'store']); // Create lesson
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);        // View lesson
    Route::put('/lessons/{lesson}', [LessonController::class, 'update']);      // Update lesson
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);  // Delete lesson

    // Assessments
    Route::post('/lessons/{lesson}/assessments', [App\Http\Controllers\AssessmentController::class, 'store']);
    Route::get('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'show']);
    Route::put('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'update']);
    Route::delete('/assessments/{assessment}', [App\Http\Controllers\AssessmentController::class, 'destroy']);
    Route::post('/assessments/{assessment}/results', [App\Http\Controllers\AssessmentController::class, 'storeResult']);


    // ==========================================
    // Group Students
    // ==========================================
    Route::get('/groups/{group}/students', [GroupStudentController::class, 'index']); // Group student list
    Route::post('/groups/{group}/students', [GroupStudentController::class, 'store']); // Add student to group
    Route::delete('/groups/{group}/students/{user}', [GroupStudentController::class, 'destroy']); // Remove student from group


    // ==========================================
    // Attendance
    // ==========================================
    Route::get('/groups/{group}/attendance', [AttendanceController::class, 'index']); // List attendance
    Route::post('/groups/{group}/attendance', [AttendanceController::class, 'store']); // Record attendance


    // ==========================================
    // Teacher Management (Teacher + Assistant only)
    // ==========================================
    Route::prefix('teacher-management')->middleware('role:teacher|assistant')->group(function () {
        Route::post('/groups', [TeacherManagementController::class, 'storeGroup']); // Create groups
        Route::post('/users', [TeacherManagementController::class, 'storeUser']);   // Create student/parent
    });


    // ==========================================
    // Center Admin Management
    // ==========================================
    Route::prefix('center-admin/management')->middleware('role:center_admin')->group(function () {
        Route::get('/stats', [CenterAdminManagementController::class, 'stats']); // Center stats
        Route::get('/users', [CenterAdminManagementController::class, 'getUsers']); // Center staff/users list
        Route::post('/users', [CenterAdminManagementController::class, 'storeUser']); // Add staff
        Route::put('/users/{user}', [CenterAdminManagementController::class, 'updateUser']); // Edit staff
        Route::delete('/users/{user}', [CenterAdminManagementController::class, 'destroyUser']); // Delete staff
    });


    // ==========================================
    // Center Admin (Existing Scoped APIs)
    // ==========================================
    Route::prefix('center-admin')->middleware('role:center_admin|teacher|assistant')->group(function () {
        Route::get('/members', [CenterAdminController::class, 'members']); // Full members list
    });

    Route::prefix('center-admin')->middleware('role:center_admin')->group(function () {
        Route::get('/groups', [CenterAdminController::class, 'groups']);   // All groups for center
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']); // Delete group

        // Create staff
        Route::post('/teachers', [CenterAdminController::class, 'storeTeacher']);
        Route::post('/assistants', [CenterAdminController::class, 'storeAssistant']);
        Route::post('/students', [CenterAdminController::class, 'storeStudent']);

        // Delete staff
        Route::delete('/teachers/{user}', [CenterAdminController::class, 'destroyTeacher']);
        Route::delete('/assistants/{user}', [CenterAdminController::class, 'destroyAssistant']);
        Route::delete('/students/{user}', [CenterAdminController::class, 'destroyStudent']);
    });


    // ==========================================
    // Student Dashboard
    // ==========================================
    Route::middleware('role:student')->prefix('dashboard/student')->group(function () {
        Route::get('/home', [StudentDashboardController::class, 'overView']);         // Main dashboard
        Route::get('/groups', [StudentDashboardController::class, 'studentGroups']);  // Student groups
        Route::get('/groups/{group}', [StudentDashboardController::class, 'groupOverview']); // Group details
        Route::get('/assessments', [StudentDashboardController::class, 'studentAssignments']); // Assignments
    });


    // ==========================================
    // Notifications
    // ==========================================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);          // List notifications
        Route::get('/latest', [NotificationController::class, 'latest']);   // Latest notification
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']); // Unread badge count
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']); // Mark all read
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);   // Mark one read
        Route::delete('/{id}', [NotificationController::class, 'destroy']); // Delete one
        Route::delete('/', [NotificationController::class, 'destroyAll']);  // Delete all
    });

});
