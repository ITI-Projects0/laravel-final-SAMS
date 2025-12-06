<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupStudentController extends Controller
{
    public function canManageGroup(Group $group): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Broad permissions first (super-admin or explicit permissions)
        if ($user->hasRole('admin') || $user->can('manage groups') || $user->can('manage attendance')) {
            return true;
        }

        $group->loadMissing('center');
        $userCenterId = $user->center_id ?? $user->ownedCenter?->id;

        // Teacher directly assigned to the group
        if ($user->hasRole('teacher') && $group->teacher_id === $user->id) {
            return true;
        }

        // Center admin over the group center
        if ($user->hasRole('center_admin') && $userCenterId && $group->center_id === $userCenterId) {
            return true;
        }

        // Assistant working within the same center
        if ($user->hasRole('assistant') && $userCenterId && $group->center_id === $userCenterId) {
            return true;
        }

        return false;
    }

    public function index(Request $request, Group $group)
    {
        if (!$group->exists) {
            $groupId = $request->route('group') ?? $request->input('group_id');
            $group = Group::with('center')->find($groupId);
            if (!$group) {
                return $this->error('Group not found.', 404);
            }
        }

        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $group->load([
            'students' => fn($q) => $q->select('users.id', 'users.name', 'users.email', 'users.phone')
                ->withPivot('status', 'joined_at', 'is_pay'),
            'pendingStudents' => fn($q) => $q->select('users.id', 'users.name', 'users.email', 'users.phone')
                ->withPivot('status', 'joined_at', 'is_pay'),
        ]);

        return $this->success([
            'approved' => $group->students,
            'pending' => $group->pendingStudents,
        ], 'Group students retrieved successfully.');
    }

    public function store(Request $request, Group $group)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

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
