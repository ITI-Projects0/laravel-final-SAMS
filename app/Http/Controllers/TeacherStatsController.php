<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherStatsController extends Controller
{
    public function stats()
    {
        $user = auth()->user();

        // Only teachers, assistants, and center_admins can access stats
        if (!$user->hasAnyRole(['teacher', 'assistant', 'center_admin'], 'api')) {
            return $this->error('Unauthorized', 403);
        }

        // Get groups count for this teacher/center_admin
        $groupsQuery = Group::query();

        if ($user->hasRole('center_admin')) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            if ($centerId) {
                $groupsQuery->where('center_id', $centerId);
            }
        } elseif ($user->hasRole('teacher')) {
            $groupsQuery->where('teacher_id', $user->id);
        } elseif ($user->hasRole('assistant') && $user->center_id) {
            $groupsQuery->where('center_id', $user->center_id);
        }

        $groupsCount = $groupsQuery->count();

        // Get students count from groups
        $groupIds = $groupsQuery->pluck('id');

        $studentsCount = DB::table('group_students')
            ->whereIn('group_id', $groupIds)
            ->where('status', 'approved')
            ->distinct('student_id')
            ->count('student_id');

        // Get attendance today
        $attendanceToday = Attendance::whereDate('date', today())
            ->whereIn('group_id', $groupIds)
            ->count();

        // Get lessons count (if lessons table exists)
        $lessonsCount = 0;
        if (Schema::hasTable('lessons')) {
            $lessonsCount = DB::table('lessons')
                ->whereIn('group_id', $groupIds)
                ->count();
        }

        // Recent activities (last 5 groups)
        $recentActivities = Group::whereIn('id', $groupIds)
            ->with('center:id,name')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'created_at', 'center_id'])
            ->map(function ($group) {
                return [
                    'title' => $group->name,
                    'titleKey' => null,
                    'time' => $group->created_at->diffForHumans(),
                    'created_at' => $group->created_at->toISOString(),
                ];
            });

        return $this->success([
            'stats' => [
                'groups' => $groupsCount,
                'students' => $studentsCount,
                'attendanceToday' => $attendanceToday,
                'lessons' => $lessonsCount,
            ],
            'recent' => $recentActivities
        ], 'Teacher stats retrieved successfully.');
    }
}
