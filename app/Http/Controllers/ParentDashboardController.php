<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;

class ParentDashboardController extends Controller
{
    use ApiResponse;

    public function overview(): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $childIds = $this->childIds($parent);

        $upcomingClasses = Lesson::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours(48))
            ->whereHas('group.students', fn($q) => $q->whereIn('users.id', $childIds))
            ->with('group:id,name,subject')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'group_id', 'title', 'scheduled_at']);

        $notifications = $parent->notifications()
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'data', 'read_at', 'created_at']);

        return $this->success([
            'children_total' => $childIds->count(),
            'unread_notifications_count' => $parent->unreadNotifications()->count(),
            'upcoming_classes_preview' => $upcomingClasses,
            'recent_notifications' => $notifications,
        ], 'Parent overview retrieved successfully.');
    }

    public function children(Request $request): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->get('page', 1));

        $children = $parent->children()
            ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.center_id')
            ->with('center:id,name')
            ->paginate($perPage, ['*'], 'page', $page);

        $children->getCollection()->transform(function ($child) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'email' => $child->email,
                'avatar' => $child->avatar,
                'center' => $child->center ? [
                    'id' => $child->center->id,
                    'name' => $child->center->name,
                ] : null,
            ];
        });

        return $this->success($children->items(), 'Children list retrieved successfully.', meta: $this->meta($children));
    }

    public function childShow(User $child): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();

        if (!$parent->children()->where('users.id', $child->id)->exists()) {
            return $this->error('This student is not linked to your account.', 403);
        }

        $child->load([
            'center:id,name',
            'courses:id,name,subject,teacher_id',
            'courses.teacher:id,name',
        ]);

        $attendance = Attendance::query()
            ->where('student_id', $child->id)
            ->selectRaw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count, COUNT(*) as total_count')
            ->first();

        $attendanceRate = $attendance && $attendance->total_count > 0
            ? round(($attendance->present_count / $attendance->total_count) * 100, 1)
            : 0;

        $upcomingClasses = Lesson::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours(48))
            ->whereHas('group.students', fn($q) => $q->where('users.id', $child->id))
            ->with('group:id,name,subject')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'group_id', 'title', 'scheduled_at']);

        $pendingAssignments = Assessment::query()
            ->where('scheduled_at', '>=', now())
            ->whereHas('group.students', fn($q) => $q->where('users.id', $child->id))
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'group_id', 'title', 'max_score', 'scheduled_at']);

        $recentGrades = AssessmentResult::query()
            ->where('student_id', $child->id)
            ->with(['assessment:id,title,max_score'])
            ->latest('id')
            ->limit(3)
            ->get(['id', 'assessment_id', 'student_id', 'score', 'feedback']);

        $avgGrade = $recentGrades->count() > 0
            ? round($recentGrades->avg(function ($result) {
                return $result->score;
            }), 1)
            : null;

        return $this->success([
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
                'email' => $child->email,
                'avatar' => $child->avatar,
                'center' => $child->center ? [
                    'id' => $child->center->id,
                    'name' => $child->center->name,
                ] : null,
            ],
            'stats' => [
                'overall_attendance_rate' => $attendanceRate,
                'missed_days' => $attendance?->absent_count ?? 0,
                'active_classes' => $child->courses->count(),
                'pending_assignments_count' => $pendingAssignments->count(),
                'avg_grade' => $avgGrade,
            ],
            'classes' => $child->courses->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'subject' => $course->subject,
                    'teacher' => $course->teacher ? [
                        'id' => $course->teacher->id,
                        'name' => $course->teacher->name,
                    ] : null,
                ];
            })->values(),
            'upcoming_classes' => $upcomingClasses,
            'pending_assignments' => $pendingAssignments,
            'recent_grades' => $recentGrades->map(function ($result) {
                return [
                    'assessment_id' => $result->assessment_id,
                    'title' => $result->assessment->title ?? null,
                    'score' => $result->score,
                    'max_score' => $result->assessment->max_score ?? null,
                    'feedback' => $result->feedback,
                ];
            }),
        ], 'Child dashboard details retrieved successfully.');
    }

    /**
     * Weekly summary without AI: attendance, missed days, recent grades (last 7 days).
     */
    public function childWeeklySummary(User $child): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        if (!$parent->children()->where('users.id', $child->id)->exists()) {
            return $this->error('This student is not linked to your account.', 403);
        }

        $start = Carbon::now()->subDays(7)->startOfDay();

        $attendance = Attendance::query()
            ->where('student_id', $child->id)
            ->whereDate('date', '>=', $start)
            ->selectRaw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count, COUNT(*) as total_count')
            ->first();

        $attendanceRate = $attendance && $attendance->total_count > 0
            ? round(($attendance->present_count / $attendance->total_count) * 100, 1)
            : 0;

        $missedDays = $attendance?->absent_count ?? 0;

        $recentGrades = AssessmentResult::query()
            ->where('student_id', $child->id)
            ->whereHas('assessment', fn($q) => $q->whereDate('scheduled_at', '>=', $start))
            ->with('assessment:id,title,max_score,scheduled_at')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'assessment_id', 'student_id', 'score', 'feedback']);

        $avgGrade = $recentGrades->count() > 0
            ? round($recentGrades->avg(fn($result) => $result->score), 1)
            : null;

        return $this->success([
            'student' => [
                'id' => $child->id,
                'name' => $child->name,
            ],
            'period' => [
                'from' => $start->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ],
            'stats' => [
                'attendance_rate' => $attendanceRate,
                'missed_days' => $missedDays,
                'avg_grade' => $avgGrade,
            ],
            'recent_grades' => $recentGrades->map(function ($result) {
                return [
                    'assessment_id' => $result->assessment_id,
                    'title' => $result->assessment->title ?? null,
                    'score' => $result->score,
                    'max_score' => $result->assessment->max_score ?? null,
                    'scheduled_at' => $result->assessment->scheduled_at ?? null,
                    'feedback' => $result->feedback,
                ];
            }),
        ], 'Weekly summary retrieved successfully.');
    }

    public function upcomingClasses(Request $request): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $childIds = $this->childIds($parent);

        if ($childIds->isEmpty()) {
            return $this->success([], 'No linked students found.');
        }

        $childId = $request->integer('child_id');
        if ($childId && !$childIds->contains($childId)) {
            return $this->error('Selected child is not linked to your account.', 403);
        }

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->get('page', 1));
        $hours = max(1, min((int) $request->get('hours', 48), 168));
        $now = Carbon::now();
        $endWindow = $now->copy()->addHours($hours);

        $lessons = Lesson::query()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$now, $endWindow])
            ->whereHas('group.students', function ($query) use ($childIds, $childId) {
                $query->whereIn('users.id', $childIds);
                if ($childId) {
                    $query->where('users.id', $childId);
                }
            })
            ->with([
                'group:id,name,subject,center_id,teacher_id',
                'group.center:id,name',
                'group.teacher:id,name',
                'group.students' => function ($query) use ($childIds, $childId) {
                    $query->whereIn('users.id', $childId ? [$childId] : $childIds)
                        ->select('users.id', 'users.name');
                },
            ])
            ->orderBy('scheduled_at')
            ->paginate($perPage, ['id', 'group_id', 'title', 'description', 'scheduled_at'], 'page', $page);

        $lessons->getCollection()->transform(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $lesson->scheduled_at,
                'group' => $lesson->group ? [
                    'id' => $lesson->group->id,
                    'name' => $lesson->group->name,
                    'subject' => $lesson->group->subject,
                    'center_name' => $lesson->group->center->name ?? null,
                    'teacher_name' => $lesson->group->teacher->name ?? null,
                ] : null,
                'students' => $lesson->group && $lesson->group->relationLoaded('students')
                    ? $lesson->group->students->map(fn ($student) => [
                        'id' => $student->id,
                        'name' => $student->name,
                    ])->values()
                    : [],
            ];
        });

        return $this->success($lessons->items(), 'Upcoming classes retrieved successfully.', meta: $this->meta($lessons));
    }

    public function attendance(Request $request): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $childIds = $this->childIds($parent);

        if ($childIds->isEmpty()) {
            return $this->success([], 'No linked students found.');
        }

        $childId = $request->integer('child_id');
        if ($childId && !$childIds->contains($childId)) {
            return $this->error('Selected child is not linked to your account.', 403);
        }

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->get('page', 1));
        $status = $request->get('status');
        $date = $request->get('date');
        $subject = $request->get('subject');

        $query = Attendance::query()
            ->whereIn('student_id', $childIds)
            ->with([
                'student:id,name',
                'group:id,name,subject',
                'lesson:id,group_id,scheduled_at',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($childId) {
            $query->where('student_id', $childId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($subject) {
            $query->whereHas('group', function ($q) use ($subject) {
                $q->where('subject', 'like', '%' . $subject . '%');
            });
        }

        $attendance = $query->paginate($perPage, ['id', 'student_id', 'group_id', 'lesson_id', 'date', 'status'], 'page', $page);

        $attendance->getCollection()->transform(function ($record) {
            return [
                'id' => $record->id,
                'date' => optional($record->date)->toDateString(),
                'status' => $record->status,
                'student' => $record->student ? [
                    'id' => $record->student->id,
                    'name' => $record->student->name,
                ] : null,
                'group' => $record->group ? [
                    'id' => $record->group->id,
                    'name' => $record->group->name,
                    'subject' => $record->group->subject,
                ] : null,
                'lesson' => $record->lesson ? [
                    'id' => $record->lesson->id,
                    'scheduled_at' => $record->lesson->scheduled_at,
                ] : null,
            ];
        });

        return $this->success($attendance->items(), 'Attendance retrieved successfully.', meta: $this->meta($attendance));
    }

    public function notifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->get('page', 1));

        $notifications = $parent->notifications()
            ->latest()
            ->paginate($perPage, ['id', 'type', 'data', 'read_at', 'created_at'], 'page', $page);

        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        return $this->success($notifications->items(), 'Notifications retrieved successfully.', meta: $this->meta($notifications));
    }

    public function summary(): \Illuminate\Http\JsonResponse
    {
        $parent = $this->parent();
        $childIds = $this->childIds($parent);

        if ($childIds->isEmpty()) {
            return $this->success([
                'children_total' => 0,
                'average_attendance_rate' => 0,
                'active_classes' => 0,
                'pending_assignments_count' => 0,
                'upcoming_classes_count' => 0,
            ], 'No linked students found.');
        }

        $attendanceTotals = Attendance::query()
            ->whereIn('student_id', $childIds)
            ->selectRaw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count, COUNT(*) as total_count')
            ->first();

        $averageAttendanceRate = ($attendanceTotals && $attendanceTotals->total_count > 0)
            ? round(($attendanceTotals->present_count / $attendanceTotals->total_count) * 100, 1)
            : 0;

        $activeClasses = \App\Models\Group::query()
            ->whereHas('students', fn($q) => $q->whereIn('users.id', $childIds))
            ->count();

        $pendingAssignmentsCount = Assessment::query()
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '>=', now());
            })
            ->whereHas('group.students', fn($q) => $q->whereIn('users.id', $childIds))
            ->count();

        $upcomingClassesCount = Lesson::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->whereHas('group.students', fn($q) => $q->whereIn('users.id', $childIds))
            ->count();

        return $this->success([
            'children_total' => $childIds->count(),
            'average_attendance_rate' => $averageAttendanceRate,
            'active_classes' => $activeClasses,
            'pending_assignments_count' => $pendingAssignmentsCount,
            'upcoming_classes_count' => $upcomingClassesCount,
        ], 'Parent summary retrieved successfully.');
    }

    private function perPage(Request $request, int $default = 10): int
    {
        return max(1, min((int) $request->get('per_page', $default), 100));
    }

    private function parent(): User
    {
        return User::findOrFail(Auth::id());
    }

    private function childIds(User $parent)
    {
        return $parent->children()->pluck('users.id');
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
