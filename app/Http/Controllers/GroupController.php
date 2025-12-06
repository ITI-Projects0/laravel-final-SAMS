<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Http\Resources\GroupResource;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\User;
use App\Models\Lesson;
use App\Notifications\NewGroupCreated;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class GroupController extends Controller
{
    public function index()
    {
        try {
            $query = Group::with(['teacher', 'center', 'students', 'pendingStudents'])
                ->withCount('students as students_count');

            $user = User::findOrFail(Auth::id());
            if ($user?->hasRole('center_admin')) {
                $centerId = $user->center_id ?? $user->ownedCenter?->id;
                if ($centerId) {
                    $query->where('center_id', $centerId);
                }
            } elseif (!$user?->hasRole('admin')) {
                $query->where('teacher_id', $user?->id);
            }

            $groups = $query->paginate(15);

            return $this->success(
                data: $groups,
                message: 'Groups retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to retrieve groups.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    public function store(StoreGroupRequest $request)
    {
        try {
            $this->authorize('create', Group::class);

            $allAdmins = User::role('admin')->get();

            $data = $request->validated();
            $data['teacher_id'] = Auth::id();

            $group = Group::create($data);
            $group->load('teacher');
            $teacher = $group->teacher ?? User::find($data['teacher_id']);

            // Auto-create lessons based on sessions_count & schedule_days
            $sessionCount = (int) ($data['sessions_count'] ?? 0);
            $scheduleDays = Arr::wrap($data['schedule_days'] ?? []);
            $scheduleTime = $data['schedule_time'] ?? null;

            if ($sessionCount > 0 && count($scheduleDays) > 0) {
                $cursor = Carbon::now()->startOfDay();
                foreach (range(1, $sessionCount) as $index) {
                    $dayName = $scheduleDays[($index - 1) % count($scheduleDays)];
                    $cursor = $cursor->next($dayName);
                    $scheduledAt = $scheduleTime ? $cursor->copy()->setTimeFromTimeString($scheduleTime) : $cursor->copy();

                    Lesson::create([
                        'group_id' => $group->id,
                        'title' => "Lesson {$index}",
                        'description' => null,
                        'scheduled_at' => $scheduledAt,
                    ]);
                }
            }

            $allAdmins->each(function ($admin) use ($group, $teacher) {
                if ($teacher) {
                    $admin->notify(new NewGroupCreated($group, $teacher));
                }
            });
            return $this->success(
                data: $group,
                message: 'Group created successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to create group.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    public function show(Group $group)
    {
        return $this->success(
            data: $group,
            message: 'Group retrieved successfully.'
        );
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        try {
            $this->authorize('update', $group);

            $data = $request->validated();
            $group->update($data);

            // Regenerate lessons if sessions_count or schedule changes are provided
            $sessionCount = $data['sessions_count'] ?? null;
            $scheduleDays = $data['schedule_days'] ?? null;

            if (!is_null($sessionCount) && !is_null($scheduleDays)) {
                // Remove existing lessons and recreate based on new schedule
                $group->lessons()->delete();

                $sessionCount = (int) $sessionCount;
                $scheduleDays = Arr::wrap($scheduleDays);
                $scheduleTime = $data['schedule_time'] ?? null;

                if ($sessionCount > 0 && count($scheduleDays) > 0) {
                    $cursor = Carbon::now()->startOfDay();
                    foreach (range(1, $sessionCount) as $index) {
                        $dayName = $scheduleDays[($index - 1) % count($scheduleDays)];
                        $cursor = $cursor->next($dayName);
                        $scheduledAt = $scheduleTime ? $cursor->copy()->setTimeFromTimeString($scheduleTime) : $cursor->copy();

                        Lesson::create([
                            'group_id' => $group->id,
                            'title' => "Lesson {$index}",
                            'description' => null,
                            'scheduled_at' => $scheduledAt,
                        ]);
                    }
                }
            }

            return $this->success(
                data: $group,
                message: 'Group updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to update group.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }

    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        try {
            $group->delete();

            return $this->success(
                message: 'Group deleted successfully.',
                status: 204
            );
        } catch (\Exception $e) {
            return $this->error(
                message: 'Failed to delete group.',
                status: 500,
                errors: $e->getMessage(),
            );
        }
    }
}
