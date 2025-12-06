<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Center;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CenterAdminController extends Controller
{
    /**
     * Get the center for the authenticated center_admin.
     */
    protected function center(): ?Center
    {
        $user = Auth::user();

        if (!$user || !$user->center) {
            return null;
        }

        return $user->center;
    }

    /**
     * List all members (teachers, assistants, students, parents) in this center.
     */
    public function members()
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        $centerId = $center->id;

        // Teachers & assistants: any user teaching groups in this center.
        // Teachers
        $teachers = User::role('teacher')
            ->whereHas('taughtGroups', function ($q) use ($centerId) {
                $q->where('center_id', $centerId);
            })
            ->get(['id', 'name', 'email', 'phone', 'status']);

        // Assistants
        $assistants = User::role('assistant')
            ->whereHas('taughtGroups', function ($q) use ($centerId) {
                $q->where('center_id', $centerId);
            })
            ->get(['id', 'name', 'email', 'phone', 'status']);

        // Students: any user linked via group_students to groups in this center.
        $students = User::role('student')
            ->whereHas('taughtGroups', function ($q) use ($centerId) {
                $q->where('center_id', $centerId);
            })
            ->get(['id', 'name', 'email', 'phone', 'status']);

        // Parents (still need to be linked via students)
        $parents = User::role('parent')
            ->whereHas('parentLinks', function ($q) use ($centerId) {
                $q->whereHas('student.groups', function ($qq) use ($centerId) {
                    $qq->whereHas('taughtGroups', function ($q) use ($centerId) {
                $q->where('center_id', $centerId);
            });
                });
            })
            ->get(['id', 'name', 'email', 'phone', 'status']);

        return $this->success([
            'center' => [
                'id' => $center->id,
                'name' => $center->name,
                'is_active' => $center->is_active,
            ],
            'teachers' => UserResource::collection($teachers),
            'assistants' => UserResource::collection($assistants),
            'students' => UserResource::collection($students),
            'parents' => UserResource::collection($parents),
        ], 'Center members retrieved successfully.');
    }

    /**
     * List all groups in this center.
     */
    public function groups()
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        $groups = Group::with(['teacher:id,name,email'])
            ->where('center_id', $center->id)
            ->paginate(15);

        return $this->success($groups, 'Center groups retrieved successfully.');
    }

    /**
     * Create a new teacher assigned to this center.
     */
    public function storeTeacher(Request $request)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
            'role' => 'teacher',
        ]);
        $user->assignRole('teacher');
        $user->load('roles');

        return $this->success(
            new UserResource($user),
            'Teacher created successfully.',
            201
        );
    }

    /**
     * Create a new assistant assigned to this center.
     */
    public function storeAssistant(Request $request)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
            'role' => 'assistant',
        ]);
        $user->assignRole('assistant');
        $user->load('roles');

        return $this->success(
            new UserResource($user),
            'Assistant created successfully.',
            201
        );
    }

    /**
     * Create a new student assigned to this center (can be later added to groups).
     */
    public function storeStudent(Request $request)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
            'role' => 'student',
        ]);
        $user->assignRole('student');
        $user->load('roles');

        return $this->success(
            new UserResource($user),
            'Student created successfully.',
            201
        );
    }

    /**
     * Delete a teacher from this center (remove from all groups in this center).
     */
    public function destroyTeacher(User $user)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        if (!$user->hasRole('teacher') && $user->role !== 'teacher') {
            return $this->error('User is not a teacher.', 422);
        }

        // Check if teacher has groups in this center
        $hasGroupsInCenter = $user->taughtGroups()
            ->where('center_id', $center->id)
            ->exists();

        if ($hasGroupsInCenter) {
            // Remove teacher from all groups in this center
            $user->taughtGroups()
                ->where('center_id', $center->id)
                ->update(['teacher_id' => null]);
        }

        // Optionally: remove role if not teaching anywhere else
        // For now, we just remove from center groups

        return $this->success(
            null,
            'Teacher removed from center successfully.',
            204
        );
    }

    /**
     * Delete an assistant from this center (remove from all groups in this center).
     */
    public function destroyAssistant(User $user)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        if (!$user->hasRole('assistant') && $user->role !== 'assistant') {
            return $this->error('User is not an assistant.', 422);
        }

        // Remove assistant from all groups in this center
        $user->groups()
            ->where('center_id', $center->id)
            ->detach();

        return $this->success(
            null,
            'Assistant removed from center successfully.',
            204
        );
    }

    /**
     * Delete a student from this center (remove from all groups in this center).
     */
    public function destroyStudent(User $user)
    {
        $center = $this->center();
        if (!$center) {
            return $this->error('Center not found for this admin.', 404);
        }

        if (!$user->hasRole('student') && $user->role !== 'student') {
            return $this->error('User is not a student.', 422);
        }

        // Remove student from all groups in this center
        $groupIds = Group::where('center_id', $center->id)->pluck('id');
        $user->groups()
            ->whereIn('group_id', $groupIds)
            ->detach();

        return $this->success(
            null,
            'Student removed from center successfully.',
            204
        );
    }
}
