<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupStudentController extends Controller
{
    protected function canManageGroup(Group $group): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Admin can manage all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Group teacher
        if ($group->teacher_id === $user->id) {
            return true;
        }

        // Center admin for this group center
        if ($user->hasRole('center_admin')) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            if ($group->center?->user_id === $user->id) {
                return true;
            }
            if ($centerId && $group->center_id === $centerId) {
                return true;
            }
        }

        // Assistant within same center (enrolled in any group of this center)
        if ($user->hasRole('assistant')) {
            return $user->center_id === $group->center_id
                || $user->groups()->where('center_id', $group->center_id)->exists();
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
