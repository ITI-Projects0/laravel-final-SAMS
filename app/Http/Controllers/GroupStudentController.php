<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class GroupStudentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Group $group)
    {
        if (!$group->exists) {
            $groupId = $request->route('group') ?? $request->input('group_id');
            $group = Group::with('center')->find($groupId);
            if (!$group) {
                return $this->error('Group not found.', 404);
            }
        }

        $this->authorize('view', $group);

        // Paginate approved students
        $approved = $group->students()
            ->select('users.id', 'users.name', 'users.email', 'users.phone')
            ->with('parents:id,name,email,phone')
            ->withPivot('status', 'joined_at', 'is_pay')
            ->paginate(20);

        // Keep pending as collection (usually small number)
        $pending = $group->pendingStudents()
            ->select('users.id', 'users.name', 'users.email', 'users.phone')
            ->withPivot('status', 'joined_at', 'is_pay')
            ->get();

        return $this->success([
            'approved' => $approved,
            'pending' => $pending,
        ], 'Group students retrieved successfully.');
    }

    public function store(Request $request, Group $group)
    {
        // Adding a student to a group is considered updating the group
        $this->authorize('update', $group);

        $data = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
        ]);

        /** @var User $student */
        $student = User::findOrFail($data['student_id']);
        if (!$student->hasRole('student')) {
            return $this->error('User is not a student.', 422);
        }

        $group->students()->syncWithoutDetaching([
            $student->id => [
                'status' => 'approved',
                'is_pay' => false,
                'joined_at' => now(),
            ],
        ]);

        return $this->success(null, 'Student added to group successfully.', 201);
    }

}
