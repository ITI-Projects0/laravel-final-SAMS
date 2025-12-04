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

        $recent = Group::with(['center:id,name', 'teacher:id,name'])
            ->latest()
            ->get(['id', 'name', 'created_at', 'center_id', 'teacher_id']);

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
            'recent' => $recent,
        ], 'Admin stats retrieved successfully.');
    }
}