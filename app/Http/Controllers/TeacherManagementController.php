<?php

namespace App\Http\Controllers;

use App\Http\Resources\GroupResource;
use App\Http\Resources\UserResource;
use App\Mail\NewAccountMail;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeacherManagementController extends Controller
{
    protected $groupScheduleService;

    public function __construct(GroupScheduleService $groupScheduleService)
    {
        $this->groupScheduleService = $groupScheduleService;
    }

    /**
     * Create a new group with automatic lesson scheduling
     */
    public function storeGroup(Request $request)
    {
        $this->authorize('create', Group::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schedule_days' => 'required|array|min:1',
            'schedule_days.*' => 'string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'schedule_time' => 'required|date_format:H:i',
            'sessions_count' => 'required|integer|min:1|max:100',
        ]);

        $user = $request->user();

        if (!$user->center_id) {
            return $this->error('Teacher must belong to a center', 400);
        }

        DB::beginTransaction();
        try {
            $group = Group::create([
                'center_id' => $user->center_id,
                'teacher_id' => $user->id,
                'name' => $validated['name'],
                'subject' => $validated['subject'],
                'description' => $validated['description'] ?? null,
                'schedule_days' => $validated['schedule_days'],
                'schedule_time' => $validated['schedule_time'],
                'sessions_count' => $validated['sessions_count'],
                'is_active' => true,
            ]);

            // Generate lessons automatically
            $this->groupScheduleService->generateLessons($group);

            DB::commit();

            return $this->success(
                new GroupResource($group->load('lessons')),
                'Group created successfully with scheduled lessons',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new student or parent user
     */
    public function storeUser(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:student,parent',
            'group_id' => 'required_if:role,student|exists:groups,id',
            'student_id' => 'required_if:role,parent|exists:users,id',
        ]);

        $user = $request->user();

        if (!$user->center_id) {
            return $this->error('Teacher must belong to a center', 400);
        }

        DB::beginTransaction();
        try {
            $password = Str::random(10);

            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($password),
                'center_id' => $user->center_id,
                'status' => 'active',
            ]);

            $newUser->assignRole($validated['role']);

            // Link student to group
            if ($validated['role'] === 'student') {
                $group = Group::find($validated['group_id']);

                // Verify group belongs to same center
                if ($group->center_id !== $user->center_id) {
                    throw new \Exception('Group does not belong to this center');
                }

                $newUser->groups()->attach($group->id, [
                    'status' => 'approved',
                    'joined_at' => now(),
                ]);
            }

            // Link parent to student
            if ($validated['role'] === 'parent') {
                $student = User::find($validated['student_id']);

                // Verify student belongs to same center
                if ($student->center_id !== $user->center_id) {
                    throw new \Exception('Student does not belong to this center');
                }

                $newUser->children()->attach($student->id, [
                    'relationship' => 'parent',
                ]);
            }

            // Send email with credentials
            Mail::to($newUser->email)->send(new NewAccountMail($newUser, $password, config('app.frontend_url/login')));

            DB::commit();

            return $this->success(
                new UserResource($newUser->load('roles')),
                ucfirst($validated['role']) . ' created successfully. Credentials sent via email.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }
}
