<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Group;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $centersCount = Center::count();
        $activeCenters = Center::where('is_active', true)->count();

        $groupsCount = Group::count();
        $activeGroups = Group::where('is_active', true)->count();

        $teachersCount = User::role('teacher')->count();
        $studentsCount = User::role('student')->count();

        $today = Carbon::today();
        $attendanceToday = Attendance::whereDate('created_at', $today)->count();

        // Paginate recent activity
        $perPage = request('per_page', 10);
        $recentQuery = \App\Models\ActivityLog::latest()
            ->select(['id', 'description as name', 'created_at']); // Mapping description to name for frontend compatibility

        $recentPaginated = $recentQuery->paginate($perPage);

        return $this->success([
            'stats' => [
                'centers' => $centersCount,
                'paidCenters' => $activeCenters,
                'unpaidCenters' => max($centersCount - $activeCenters, 0),
                'courses' => $groupsCount,
                'activeCourses' => $activeGroups,
                'teachers' => $teachersCount,
                'onlineTeachers' => 0,
                'students' => $studentsCount,
                'attendanceToday' => $attendanceToday,
            ],
            'recent' => [
                'data' => $recentPaginated->items(),
                'current_page' => $recentPaginated->currentPage(),
                'per_page' => $recentPaginated->perPage(),
                'total' => $recentPaginated->total(),
                'last_page' => $recentPaginated->lastPage(),
            ],
        ], 'Admin stats retrieved successfully.');
    }
}