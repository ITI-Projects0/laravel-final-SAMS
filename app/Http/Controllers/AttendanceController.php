<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\User;
use App\Notifications\StudentAbsent;
use App\Notifications\StudentLate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    protected function canManageGroup(Group $group): bool
    {
        $groupStudentController = new GroupStudentController();
        return $groupStudentController->canManageGroup($group);
    }

    public function index(Request $request, $groupId)
    {
        try {
            $group = Group::findOrFail($groupId);

            if (!$this->canManageGroup($group)) {
                return $this->error('Unauthorized.', 403);
            }

            $query = Attendance::where('group_id', $group->id);

            if ($request->filled('date')) {
                $query->where('date', $request->string('date')->toString());
            }

            $records = $query->with(['student', 'group', 'markedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->success(AttendanceResource::collection($records), 'Attendance records retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Group not found.', 404);
        } catch (\Throwable $e) {
            Log::error('Attendance index error: ' . $e->getMessage());
            return $this->error(
                message: 'Failed to retrieve attendance records.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    public function store(StoreAttendanceRequest $request, $groupId)
    {
        try {
            $group = Group::findOrFail($groupId);

            if (!$this->canManageGroup($group)) {
                return $this->error('Unauthorized.', 403);
            }

            $data = $request->validated();
            $date = $data['date'];
            $lessonId = $data['lesson_id'] ?? null;
            $entries = $data['entries'];
            $userId = Auth::id();

            $notificationsToSend = [];

            foreach ($entries as $entry) {
                $matchAttributes = [
                    'center_id' => $group->center_id,
                    'group_id' => $group->id,
                    'student_id' => $entry['student_id'],
                ];

                // If lesson_id is provided, include it in the match attributes to ensure uniqueness per lesson
                if ($lessonId) {
                    $matchAttributes['lesson_id'] = $lessonId;
                } else {
                    // Fallback to date-based uniqueness if no lesson_id (legacy behavior)
                    $matchAttributes['date'] = $date;
                }

                // Check if this is a new record or status changed
                $existingRecord = Attendance::where($matchAttributes)->first();
                $isNewOrChanged = !$existingRecord || $existingRecord->status !== $entry['status'];

                Attendance::updateOrCreate(
                    $matchAttributes,
                    [
                        'date' => $date,
                        'lesson_id' => $lessonId,
                        'status' => $entry['status'],
                        'marked_by' => $userId,
                    ]
                );

                // Collect notifications for absent/late students (only if new or changed)
                if ($isNewOrChanged && in_array($entry['status'], ['absent', 'late'])) {
                    $notificationsToSend[] = [
                        'student_id' => $entry['student_id'],
                        'status' => $entry['status'],
                        'minutes_late' => $entry['minutes_late'] ?? 0,
                    ];
                }
            }

            // Send notifications to parents after all attendance records are saved
            foreach ($notificationsToSend as $notification) {
                $student = User::find($notification['student_id']);
                if ($student) {
                    $parents = $student->parents;
                    foreach ($parents as $parent) {
                        if ($notification['status'] === 'absent') {
                            $parent->notify(new StudentAbsent($student, $group, $date));
                        } elseif ($notification['status'] === 'late') {
                            $parent->notify(new StudentLate($student, $group, $date, $notification['minutes_late']));
                        }
                    }
                }
            }

            return $this->success(null, 'Attendance saved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Group not found.', 404);
        } catch (\Exception $e) {
            Log::error('Failed to save attendance: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to save attendance.', 500, config('app.debug') ? $e->getMessage() : null);
        }
    }
}
