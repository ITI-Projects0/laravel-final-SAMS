<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupStudentController extends Controller
{
    protected function canManageGroup(Group $group): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Admin can manage all
        if ($user->hasRole('admin') || $user->role === 'admin') {
            return true;
        }

        // Group teacher
        if ($group->teacher_id === $user->id) {
            return true;
        }

        // Center admin for this group center
        if (($user->hasRole('center_admin') || $user->role === 'center_admin') && $group->center?->user_id === $user->id) {
            return true;
        }

        // Assistant within same center (enrolled in any group of this center)
        if ($user->hasRole('assistant') || $user->role === 'assistant') {
            return $user->groups()->where('center_id', $group->center_id)->exists();
        }

        return false;
    }

    public function index(Group $group)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $students = $group->students()
            ->select('users.id', 'users.name', 'users.email', 'users.phone')
            ->get();

        return $this->success($students, 'Group students retrieved successfully.');
    }

    public function requests(Group $group)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $students = $group->pendingStudents()
            ->select('users.id', 'users.name', 'users.email', 'users.phone')
            ->get();

        return $this->success($students, 'Group join requests retrieved successfully.');
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
        if (!$student->hasRole('student') && $student->role !== 'student') {
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

    public function approve(Group $group, User $student)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $exists = DB::table('group_students')
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->exists();

        if (!$exists) {
            return $this->error('Membership not found.', 404);
        }

        DB::table('group_students')
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->update([
                'status' => 'approved',
                'joined_at' => now(),
            ]);

        return $this->success(null, 'Join request approved.');
    }

    public function reject(Group $group, User $student)
    {
        if (!$this->canManageGroup($group)) {
            return $this->error('Unauthorized.', 403);
        }

        $exists = DB::table('group_students')
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->exists();

        if (!$exists) {
            return $this->error('Membership not found.', 404);
        }

        DB::table('group_students')
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->update(['status' => 'rejected']);

        return $this->success(null, 'Join request rejected.');
    }
}


