<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Mail\NewAccountMail;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\StudentAccountCreated;
use App\Notifications\StudentAddedToGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CenterAdminManagementController extends Controller
{
    /**
     * Resolve the center for the current user.
     */
    protected function resolveCenter(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $center = $user->ownedCenter ?? $user->center;
        if ($center && !$user->center_id) {
            $user->center_id = $center->id;
            $user->save();
        }

        return $center;
    }

    /**
     * Get comprehensive center statistics
     */
    public function stats(Request $request)
    {
        $center = $this->resolveCenter($request);

        if (!$center) {
            return $this->error('Center not found for this admin', 404);
        }

        $stats = [
            'teachers_count' => User::role('teacher')->where('center_id', $center->id)->count(),
            'students_count' => User::role('student')->where('center_id', $center->id)->count(),
            'assistants_count' => User::role('assistant')->where('center_id', $center->id)->count(),
            'parents_count' => User::role('parent')->where('center_id', $center->id)->count(),
            'groups_count' => Group::where('center_id', $center->id)->count(),
            'lessons_count' => Lesson::whereHas('group', fn($q) => $q->where('center_id', $center->id))->count(),
            'active_groups' => Group::where('center_id', $center->id)->where('is_active', true)->count(),
            'total_users' => User::where('center_id', $center->id)->count(),
        ];

        return $this->success($stats, 'Statistics retrieved successfully');
    }

    /**
     * Get users with optional role filter
     */
    public function getUsers(Request $request)
    {
        $center = $this->resolveCenter($request);

        if (!$center) {
            return $this->error('Center not found for this admin', 404);
        }

        $role = $request->query('role');

        $query = User::where('center_id', $center->id)->with('roles');

        if ($role && in_array($role, ['teacher', 'student', 'assistant', 'parent'])) {
            $query->role($role);
        }

        $users = $query->paginate(15);

        return $this->success(UserResource::collection($users), 'Users retrieved successfully');
    }

    /**
     * Create a new user (any role)
     */
    public function storeUser(Request $request)
    {
        $this->authorize('create', User::class);

        $center = $this->resolveCenter($request);

        if (!$center) {
            return $this->error('Center not found for this admin', 404);
        }

        $existingUser = User::where('email', $request->input('email'))->first();
        $validated = $this->validateStoreUser($request, $existingUser);

        DB::beginTransaction();
        try {
            if ($validated['role'] === 'student' && $existingUser) {
                $existingUser = $this->reuseStudent($existingUser, $validated, $center, $request->user());

                DB::commit();

                return $this->success(
                    new UserResource($existingUser->load('roles')),
                    'Existing student linked to group successfully.',
                    200
                );
            }

            if ($validated['role'] === 'parent' && $existingUser) {
                $existingUser = $this->reuseParent($existingUser, $validated, $center);

                DB::commit();

                return $this->success(
                    new UserResource($existingUser->load('roles')),
                    'Existing parent linked to student(s) successfully.',
                    200
                );
            }

            $password = Str::random(10);

            $user = $this->createNewUser($validated, $center, $password);

            $user->assignRole($validated['role']);

            if ($validated['role'] === 'student' && isset($validated['group_id'])) {
                $group = $this->attachStudentToGroup($user, $validated['group_id'], $center);
                $user->notify(new StudentAccountCreated($password, $request->user(), $group));
            }

            if ($validated['role'] === 'parent') {
                $studentIds = $this->collectParentStudentIds($validated);
                $this->assertStudentsBelongToCenter($studentIds, $center);
                $this->attachParentToStudents($user, $studentIds);
            }

            // Send email with credentials for non-student roles
            if ($validated['role'] !== 'student') {
                Mail::to($user->email)->send(new NewAccountMail($user, $password, $this->frontendLoginUrl()));
            }

            DB::commit();

            return $this->success(
                new UserResource($user->load('roles')),
                ucfirst($validated['role']) . ' created successfully. Credentials sent via email.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    protected function validateStoreUser(Request $request, ?User $existingUser): array
    {
        $emailRule = Rule::unique('users', 'email');

        // Allow reusing an existing student/parent account.
        if (in_array($request->input('role'), ['student', 'parent'], true) && $existingUser) {
            $emailRule = $emailRule->ignore($existingUser->id);
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $emailRule],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:teacher,student,assistant,parent',
            'group_id' => 'required_if:role,student|exists:groups,id',
            'student_id' => [
                Rule::requiredIf(fn() => $request->input('role') === 'parent' && !$request->filled('student_ids')),
                'nullable',
                'exists:users,id',
            ],
            'student_ids' => [
                Rule::requiredIf(fn() => $request->input('role') === 'parent' && !$request->filled('student_id')),
                'array',
                'min:1',
            ],
            'student_ids.*' => 'exists:users,id',
        ]);
    }

    protected function reuseStudent(User $student, array $validated, $center, User $actor): User
    {
        $student->fill([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? $student->phone,
            // If the student was in another center, move them to the current one.
            'center_id' => $center->id,
            'status' => 'active',
        ])->save();

        if (!$student->hasRole('student')) {
            $student->assignRole('student');
        }

        $group = null;
        if (isset($validated['group_id'])) {
            $group = $this->attachStudentToGroup($student, $validated['group_id'], $center);
        }

        $student->notify(new StudentAddedToGroup($actor, $group));

        return $student;
    }

    protected function reuseParent(User $parent, array $validated, $center): User
    {
        $parent->fill([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? $parent->phone,
            'center_id' => $center->id,
            'status' => 'active',
        ])->save();

        if (!$parent->hasRole('parent')) {
            $parent->assignRole('parent');
        }

        $studentIds = $this->collectParentStudentIds($validated);
        $this->assertStudentsBelongToCenter($studentIds, $center);
        $this->attachParentToStudents($parent, $studentIds);

        return $parent;
    }

    protected function createNewUser(array $validated, $center, string $password): User
    {
        return User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($password),
            'center_id' => $center->id,
            'status' => 'active',
        ]);
    }

    protected function attachStudentToGroup(User $student, int $groupId, $center): ?Group
    {
        $group = Group::where('center_id', $center->id)->where('id', $groupId)->first();
        if (!$group) {
            return null;
        }

        $alreadyInGroup = $student->groups()
            ->where('group_id', $group->id)
            ->exists();

        $pivotData = [
            'status' => 'approved',
            'joined_at' => now(),
        ];

        if ($alreadyInGroup) {
            $student->groups()->updateExistingPivot($group->id, $pivotData);
        } else {
            $student->groups()->attach($group->id, $pivotData);
        }

        return $group;
    }

    protected function collectParentStudentIds(array $validated): \Illuminate\Support\Collection
    {
        return collect($validated['student_ids'] ?? [])
            ->when(isset($validated['student_id']), fn($c) => $c->push($validated['student_id']))
            ->unique()
            ->values();
    }

    protected function assertStudentsBelongToCenter($studentIds, $center): void
    {
        $students = User::where('center_id', $center->id)
            ->whereIn('id', $studentIds)
            ->get();

        if ($students->count() !== $studentIds->count()) {
            throw new \Exception('One or more students do not belong to this center');
        }
    }

    protected function attachParentToStudents(User $parent, $studentIds): void
    {
        $pivotData = collect($studentIds)->mapWithKeys(fn($id) => [
            $id => ['relationship' => 'parent'],
        ])->toArray();

        $parent->children()->syncWithoutDetaching($pivotData);
    }

    /**
     * Update user details
     */
    public function updateUser(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $center = $request->user()->ownedCenter;
        $center = $center ?: $this->resolveCenter($request);

        if (!$center || $user->center_id !== $center->id) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $user->update($validated);

        return $this->success(
            new UserResource($user->load('roles')),
            'User updated successfully'
        );
    }

    /**
     * Delete user
     */
    public function destroyUser(User $user)
    {
        try {
            if (!$user) {
                return $this->error('User not found.', 404);
            }
            $authUser = User::findOrFail(Auth::id());
            $center = $authUser->ownedCenter;

            if (!$center || $authUser->center_id !== $user->center_id) {
                return $this->error('Unauthorized', 403);
            }

            $user->delete();
            return $this->success(null, 'User deleted successfully.', 200);
        } catch (\Throwable $e) {
            return $this->error(
                message: 'Failed to delete user.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}
