<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->with(['group:id,name', 'results' => function ($query) use ($student) {
                $query->where('student_id', $student->id);
            }])
            ->latest('scheduled_at')
            ->get()
            ->map(function ($assignment) {
                $result = $assignment->results->first();
                return [
                    'center_id' => $assignment->center_id,
                    'group_id' => $assignment->group_id,
                    'title' => $assignment->title,
                    'max_score' => $assignment->max_score,
                    'scheduled_at' => $assignment->scheduled_at,
                    'score' => $result ? $result->score : null,
                    'feedback' => $result ? $result->feedback : null,
                ];
            });

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
            ->with(['results' => function ($query) use ($student) {
                $query->where('student_id', $student->id);
            }])
            ->orderBy('scheduled_at')
            ->get(['id', 'title', 'max_score', 'scheduled_at', 'center_id', 'group_id'])
            ->map(function ($assessment) {
                $result = $assessment->results->first();
                $assessment->score = $result ? $result->score : null;
                $assessment->feedback = $result ? $result->feedback : null;
                unset($assessment->results);
                return $assessment;
            });

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
                        ->select('id', 'assessment_id', 'student_id', 'score', 'feedback');
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
                    'feedback' => $result?->feedback,
                ];
            });

        return $this->success($assignments, message: 'Student assignments retrieved successfully.');
    }

    /**
     * Paginated attendance records for the authenticated student with optional filters.
     */
    public function studentAttendance()
    {
        $student = User::findOrFail(Auth::id());

        $perPage = max(5, min((int) request('per_page', 10), 100));
        $page = max(1, (int) request('page', 1));
        $dateFilter = request('date');
        $subjectFilter = request('subject');

        $query = $student->attendances()
            ->with([
                'group:id,name,subject',
                'lesson:id,group_id,scheduled_at',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($dateFilter) {
            $query->whereDate('date', $dateFilter);
        }

        if ($subjectFilter && strtolower($subjectFilter) !== 'all') {
            $query->whereHas('group', function ($q) use ($subjectFilter) {
                $q->where('subject', 'like', '%' . $subjectFilter . '%');
            });
        }

        /** @var LengthAwarePaginator $attendance */
        $attendance = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $attendance->getCollection()->map(function ($record) {
            return [
                'id' => $record->id,
                'date' => optional($record->date)->toDateString(),
                'status' => $record->status,
                'group_id' => $record->group_id,
                'group_name' => $record->group->name ?? null,
                'subject' => $record->group->subject ?? null,
                'lesson_id' => $record->lesson_id,
                'lesson_date' => optional($record->lesson?->scheduled_at)->toDateTimeString(),
            ];
        });

        return $this->success([
            'data' => $data,
            'meta' => [
                'current_page' => $attendance->currentPage(),
                'per_page' => $attendance->perPage(),
                'total' => $attendance->total(),
                'last_page' => $attendance->lastPage(),
                'filters' => [
                    'date' => $dateFilter,
                    'subject' => $subjectFilter,
                ],
            ],
        ], 'Student attendance retrieved successfully.');
    }
}
