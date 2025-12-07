<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            \Log::error('Attendance index error: ' . $e->getMessage());
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
            $entries = $data['entries'];
            $userId = Auth::id();

            foreach ($entries as $entry) {
                Attendance::updateOrCreate(
                    [
                        'center_id' => $group->center_id,
                        'group_id' => $group->id,
                        'student_id' => $entry['student_id'],
                        'date' => $date,
                    ],
                    [
                        'status' => $entry['status'],
                        'marked_by' => $userId,
                    ]
                );
            }

            return $this->success(null, 'Attendance saved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Group not found.', 404);
        } catch (\Exception $e) {
            \Log::error('Failed to save attendance: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to save attendance.', 500, config('app.debug') ? $e->getMessage() : null);
        }
    }
}
