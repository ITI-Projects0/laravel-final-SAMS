<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Http\Resources\GroupResource;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Notifications\NewGroupCreated;

class GroupController extends Controller
{
    public function index()
    {
        try {
            $query = Group::with(['teacher', 'center', 'students', 'pendingStudents'])
                ->withCount('students as students_count');

            $user = User::findOrFail(Auth::id());
            if (!$user?->hasRole('admin') && $user?->role !== 'admin') {
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
            // $this->authorize('create', Group::class);

            $allAdmins = User::role('admin')->get();

            $data = $request->validated();
            $data['teacher_id'] = Auth::id();

            $group = Group::create($data);
            $allAdmins->each(function ($admin) use ($group) {
                $admin->notify(new NewGroupCreated($group, $group->teacher));
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

            $group->update($request->validated());

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
