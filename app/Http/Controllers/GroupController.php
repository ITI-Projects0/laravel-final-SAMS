<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\User;
use App\Models\Lesson;
use App\Notifications\GroupUpdated;
use App\Notifications\NewGroupCreated;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    use \App\Traits\ApiResponse;

    public function index()
    {
        try {
            $perPage = max(5, min(request()->integer('per_page', 15), 100));
            $page = max(1, request()->integer('page', 1));
            $search = request()->string('search')->toString();
            $sortBy = request()->string('sort_by')->toString() ?: 'created_at';
            $sortDir = strtolower(request()->string('sort_dir')->toString()) === 'asc' ? 'asc' : 'desc';
            $allowedSorts = ['created_at', 'name', 'subject', 'students_count', 'lessons_count'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'created_at';
            }

            $query = Group::with(['teacher', 'center'])
                ->withCount('lessons')
                ->addSelect([
                    'students_count' => DB::table('group_students')
                        ->selectRaw('count(*)')
                        ->whereColumn('group_students.group_id', 'groups.id')
                        ->where('group_students.status', 'approved')
                ]);

            $user = User::findOrFail(Auth::id());
            if ($user?->hasRole('center_admin')) {
                $centerId = $user->center_id ?? $user->ownedCenter?->id;
                if ($centerId) {
                    $query->where('center_id', $centerId);
                }
            } elseif (!$user?->hasRole('admin')) {
                $query->where('teacher_id', $user?->id);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('center', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%"));
                });
            }

            $groups = $query
                ->orderBy($sortBy, $sortDir)
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->success(
                data: $groups,
                message: 'Groups retrieved successfully.',
                meta: [
                    'pagination' => [
                        'current_page' => $groups->currentPage(),
                        'per_page' => $groups->perPage(),
                        'total' => $groups->total(),
                        'last_page' => $groups->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search,
                        'sort_by' => $sortBy,
                        'sort_dir' => $sortDir,
                    ],
                ]
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

            $group->load(['teacher', 'center']);
            $group->loadCount('lessons');

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
        try {
            $group->load(['teacher', 'center']);

            // Count lessons directly (simple hasMany)
            $group->loadCount('lessons');

            // Count students separately due to complex relationship constraints
            $studentsCount = DB::table('group_students')
                ->where('group_id', $group->id)
                ->where('status', 'approved')
                ->count();

            $group->setAttribute('students_count', $studentsCount);

            return $this->success(
                data: $group,
                message: 'Group retrieved successfully.'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve group: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error(
                message: 'Failed to retrieve group.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null,
            );
        }
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        try {
            $this->authorize('update', $group);

            $data = $request->validated();
            $group->update($data);

            $changedFields = array_intersect_key(
                $group->getChanges(),
                array_flip(['name','description','subject','schedule_days','schedule_time','sessions_count','is_active','academic_year','teacher_id',])
            );

            // Regenerate lessons if sessions_count or schedule changes are provided
            $sessionCount = $data['sessions_count'] ?? null;
            $scheduleDays = $data['schedule_days'] ?? null;

            if (!is_null($sessionCount) && !is_null($scheduleDays)) {
                // Remove existing lessons and recreate based on new schedule
                $group->lessons()->delete();

                $sessionCount = (int) $sessionCount;
                $scheduleDays = Arr::wrap($scheduleDays);
                $scheduleTime = $data['schedule_time'] ?? $group->schedule_time;

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

            // Reload the group with relationships
            $group->refresh();
            $group->load(['teacher', 'center']);
            $group->loadCount('lessons');

            // Count students separately due to complex relationship constraints
            $studentsCount = DB::table('group_students')
                ->where('group_id', $group->id)
                ->where('status', 'approved')
                ->count();
            $group->setAttribute('students_count', $studentsCount);

            if (!empty($changedFields)) {
                $group->students()->each(function ($student) use ($group, $changedFields) {
                    $student->notify(new GroupUpdated($group, $changedFields));
                });
            }

            return $this->success(
                data: $group,
                message: 'Group updated successfully.'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error(
                message: 'You are not authorized to update this group.',
                status: 403,
            );
        } catch (\Exception $e) {
            Log::error('Failed to update group: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error(
                message: 'Failed to update group.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null,
            );
        }
    }

    public function destroy(Group $group)
    {
        try {
            $this->authorize('delete', $group);

            $groupName = $group->name;
            $group->delete();

            return $this->success(
                message: "Group '{$groupName}' deleted successfully.",
                status: 200
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error(
                message: 'You are not authorized to delete this group.',
                status: 403,
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete group: ' . $e->getMessage(), [
                'group_id' => $group->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error(
                message: 'Failed to delete group.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null,
            );
        }
    }
}
