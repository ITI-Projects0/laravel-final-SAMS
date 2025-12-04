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

    public function index(Group $group, Request $request)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $query = Attendance::where('group_id', $group->id);

        if ($request->filled('date')) {
            $query->whereDate('date', $request->string('date')->toString());
        }

        $records = $query->with(['student', 'group', 'markedBy'])->get();

        return $this->success(AttendanceResource::collection($records), 'Attendance records retrieved successfully.');
    }

    public function store(StoreAttendanceRequest $request, Group $group)
    {
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
    }
}


