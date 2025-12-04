<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;

class StudentDashboardController extends Controller
{
    use ApiResponse;

    public function overView()
    {
        $student = User::findOrFail(Auth::id());

        $enrolledCourses = $student->courses()->count();

        $upcomingLessons = $student->groups()
            ->with(['lessons' => function ($query) {
                $query->where('scheduled_at', '>=', now())
                    ->orderBy('scheduled_at');
            }])
            ->get()
            ->flatMap->lessons
            ->filter(fn($lesson) => $lesson->scheduled_at !== null)
            ->sortBy('scheduled_at')
            ->values()
            ->take(3);

        $assignments = $student->assignments()
            ->with('group:id,name')
            ->latest('scheduled_at')
            ->get()
            ->map
            ->only(['center_id', 'group_id', 'title', 'max_score', 'scheduled_at']);

        $upcomingAssignments = $assignments->filter(function ($assignment) {
            return $assignment['scheduled_at'] !== null && $assignment['scheduled_at'] >= now();
        })->values();

        $attendanceRate = $student->attendances()->count() > 0
            ? ($student->attendances()->where('status', 'present')->count() / $student->attendances()->count()) * 100
            : 0;

        return $this->success([
            'enrolled_courses' => $enrolledCourses,
            'assignments' => $assignments,
            'upcoming_assignments' => $upcomingAssignments,
            'upcoming_lessons' => $upcomingLessons,
            'attendance_rate' => $attendanceRate,
        ]);
    }

    public function studentGroups()
    {
        $student = User::findOrFail(Auth::id());

        $groups = $student->courses()
            ->with(['teacher:id,name,email', 'center:id,name'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'subject' => $group->subject,
                    'center' => $group->center,
                    'teacher' => $group->teacher,
                ];
            });

        return $this->success($groups, message: 'Student groups retrieved successfully.');
    }

    public function groupOverview(Request $request, Group $group)
    {
        $student = User::findOrFail(Auth::id());

        if (!$student->groups()->where('groups.id', $group->id)->exists()) {
            return $this->error('You are not enrolled in this course.', 403);
        }

        $month = $request->input('month');
        $monthDate = $month ? Carbon::parse($month)->startOfMonth() : now()->startOfMonth();
        $monthStart = $monthDate->copy();
        $monthEnd = $monthDate->copy()->endOfMonth();

        $nextClass = $group->lessons()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();

        $monthlySchedule = $group->lessons()
            ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
            ->orderBy('scheduled_at')
            ->get(['id', 'title', 'description', 'scheduled_at']);

        $lessonsWithResources = $group->lessons()
            ->with(['resources:id,lesson_id,type,title,url'])
            ->orderBy('scheduled_at')
            ->get(['id', 'title', 'description', 'scheduled_at', 'group_id']);

        $assignments = $group->assessments()
            ->orderBy('scheduled_at')
            ->get(['id', 'title', 'max_score', 'scheduled_at', 'center_id', 'group_id']);

        return $this->success([
            'course' => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'description' => $group->description,
                'teacher' => $group->teacher ? [
                    'id' => $group->teacher->id,
                    'name' => $group->teacher->name,
                ] : null,
            ],
            'next_class' => $nextClass,
            'monthly_schedule' => $monthlySchedule,
            'lessons' => $lessonsWithResources,
            'assignments' => $assignments,
        ]);
    }

    public function studentAssignments()
    {
        $student = User::findOrFail(Auth::id());

        $assignments = $student->assignments()
            ->with([
                'group:id,name',
                'assessment_results' => function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                        ->select('id', 'assessment_id', 'student_id', 'score', 'remarks');
                },
            ])
            ->orderByDesc('scheduled_at')
            ->get([
                'assessments.id',
                'assessments.center_id',
                'assessments.group_id',
                'assessments.title',
                'assessments.max_score',
                'assessments.scheduled_at',
            ])
            ->map(function ($assignment) {
                $result = $assignment->assessment_results->first();

                return [
                    'id' => $assignment->id,
                    'center_id' => $assignment->center_id,
                    'group_id' => $assignment->group_id,
                    'group_name' => $assignment->group->name ?? null,
                    'title' => $assignment->title,
                    'max_score' => $assignment->max_score,
                    'scheduled_at' => $assignment->scheduled_at,
                    'score' => $result?->score,
                    'remarks' => $result?->remarks,
                ];
            });

        return $this->success($assignments, message: 'Student assignments retrieved successfully.');
    }
}
