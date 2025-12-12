<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCenterTeacherRequest;
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
        $user = User::find(Auth::id());

        if (!$user) {
            return null;
        }

        $center = $user->center ?? $user->ownedCenter;

        if ($center && !$user->center_id) {
            $user->center_id = $center->id;
            $user->save();
        }

        return $center;
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

        $pageSize = (int) request('per_page', 5);
        $search = request('search');

        $applySearch = function ($query) use ($search) {
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            return $query;
        };

        $format = function ($paginator) use ($search) {
            return [
                'data' => UserResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'search' => $search,
                ],
            ];
        };

        $teachers = $format(
            $applySearch(User::role('teacher'))
                ->where('center_id', $centerId)
                ->orderBy('updated_at', 'desc')
                ->paginate($pageSize, ['id', 'name', 'email', 'phone', 'status'])
        );

        $assistants = $format(
            $applySearch(User::role('assistant'))
                ->where('center_id', $centerId)
                ->orderBy('updated_at', 'desc')
                ->paginate($pageSize, ['id', 'name', 'email', 'phone', 'status'])
        );

        $students = $format(
            $applySearch(User::role('student'))
                ->where('center_id', $centerId)
                ->with(['parents:id,name,email,phone'])
                ->orderBy('updated_at', 'desc')
                ->paginate($pageSize, ['id', 'name', 'email', 'phone', 'status'])
        );

        $parents = $format(
            $applySearch(User::role('parent'))
                ->where('center_id', $centerId)
                ->orderBy('updated_at', 'desc')
                ->paginate($pageSize, ['id', 'name', 'email', 'phone', 'status'])
        );

        return $this->success([
            'center' => [
                'id' => $center->id,
                'name' => $center->name,
                'is_active' => $center->is_active,
            ],
            'teachers' => $teachers,
            'assistants' => $assistants,
            'students' => $students,
            'parents' => $parents,
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

        $perPage = max(5, min(request()->integer('per_page', 10), 100));
        $search = request()->string('search')->toString();

        $query = Group::with(['teacher:id,name,email'])
            ->withCount('students')
            ->where('center_id', $center->id);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $groups = $query
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return $this->success(
            data: $groups,
            message: 'Center groups retrieved successfully.',
            meta: [
                'pagination' => [
                    'current_page' => $groups->currentPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                    'last_page' => $groups->lastPage(),
                ],
                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    /**
     * Create a new teacher assigned to this center.
     */
    public function storeTeacher(StoreCenterTeacherRequest $request)
    {
        try {
            $center = $this->center();
            if (!$center) {
                return $this->error('Center not found for this admin.', 404);
            }
            $centerId = $center->id;

            $validated = $request->validated();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'status' => 'active',
                'center_id' => $centerId,
            ]);
            $user->assignRole('teacher');
            $user->load('roles');

            return $this->success(
                new UserResource($user),
                'Teacher created successfully.',
                201
            );
        } catch (\Throwable $e) {
            return $this->error(
                message: 'Failed to create teacher.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
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
        $centerId = $center->id;

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
            'center_id' => $centerId,
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
        $centerId = $center->id;

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
            'center_id' => $centerId,
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
        try {
            $center = $this->center();
            if (!$center) {
                return $this->error('Center not found for this admin.', 404);
            }

            if (!$user->hasRole('teacher')) {
                return $this->error('User is not a teacher.', 422);
            }

            // Ensure teacher belongs to this center
            if ($user->center_id !== $center->id) {
                return $this->error('Teacher does not belong to this center.', 403);
            }

            // Remove teacher from all groups in this center
            $user->taughtGroups()
                ->where('center_id', $center->id)
                ->update(['teacher_id' => null]);

            // Optionally drop the role if not teaching elsewhere
            $stillTeachingElsewhere = $user->taughtGroups()->where('center_id', '<>', $center->id)->exists();
            if (!$stillTeachingElsewhere) {
                $user->removeRole('teacher');
            }

            return $this->success(
                null,
                'Teacher removed from center successfully.',
                204
            );
        } catch (\Throwable $e) {
            return $this->error(
                message: 'Failed to remove teacher.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
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

        if (!$user->hasRole('assistant')) {
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

        if (!$user->hasRole('student')) {
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
