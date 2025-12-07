<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Mail\NewAccountMail;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:teacher,student,assistant,parent',
            'group_id' => 'required_if:role,student|exists:groups,id',
            'student_id' => 'required_if:role,parent|exists:users,id',
        ]);

        $center = $this->resolveCenter($request);

        if (!$center) {
            return $this->error('Center not found for this admin', 404);
        }

        DB::beginTransaction();
        try {
            $password = Str::random(10);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($password),
                'center_id' => $center->id,
                'status' => 'active',
            ]);

            $user->assignRole($validated['role']);

            // Link student to group
            if ($validated['role'] === 'student' && isset($validated['group_id'])) {
                $group = Group::where('center_id', $center->id)->where('id', $validated['group_id'])->first();
                if ($group) {
                    $user->groups()->attach($group->id, [
                        'status' => 'approved',
                        'joined_at' => now(),
                    ]);
                }
            }

            // Link parent to student
            if ($validated['role'] === 'parent' && isset($validated['student_id'])) {
                $student = User::where('center_id', $center->id)->find($validated['student_id']);
                if (!$student) {
                    throw new \Exception('Student does not belong to this center');
                }

                $user->children()->attach($student->id, [
                    'relationship' => 'parent',
                ]);
            }

            // Send email with credentials
            Mail::to($user->email)->send(new NewAccountMail($user, $password));

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
    public function destroyUser(Request $request, $userId)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return $this->error('User not found.', 404);
            }

            $center = User::find(Auth::id())->center;

            // dd($center, $user->center_id, Auth::id());

            if (!$center || $user->center_id !== $center->id) {
                return $this->error('Unauthorized', 403);
            }

            // If teacher, detach from groups in this center and optionally drop role if unused elsewhere
            if ($user->hasRole('teacher')) {
                $user->taughtGroups()
                    ->where('center_id', $center->id)
                    ->update(['teacher_id' => null]);
                $stillTeachingElsewhere = $user->taughtGroups()->where('center_id', '<>', $center->id)->exists();
                if (!$stillTeachingElsewhere) {
                    $user->removeRole('teacher');
                }
            }

            $user->delete();

            return $this->success(message:'User deleted successfully', status:204);
        } catch (\Throwable $e) {
            return $this->error(
                message: 'Failed to delete user.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}
