<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Http\Resources\GroupResource;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::where('teacher_id', Auth::id())
            ->with(['teacher', 'center', 'students', 'pendingStudents'])
            ->withCount('students as students_count')
            ->paginate(15);

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request)
    {
        $this->authorize('create', Group::class);

        $data = $request->validated();
        $data['teacher_id'] = Auth::id();

        $group = Group::create($data);

        return $this->show($group);
    }

    public function show(Group $group)
    {
        return new GroupResource(
            $group->load(['teacher', 'center', 'students', 'pendingStudents'])
        );
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $this->authorize('update', $group);

        $group->update($request->validated());

        return $this->show($group);
    }

    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->json(['message' => 'Group deleted successfully']);
    }
}
