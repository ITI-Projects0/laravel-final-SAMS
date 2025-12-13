<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Group;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    /**
     * List lessons for a given group (teacher/assistant/admin).
     */
    public function index(Group $group)
    {
        // Ensure user can view the group first (scoping check)
        $this->authorize('view', $group);
        $this->authorize('viewAny', Lesson::class);

        $lessons = Lesson::where('group_id', $group->id)
            ->with('group')
            ->withCount('resources')
            ->orderBy('scheduled_at', 'asc')
            ->paginate(10);

        return $this->success(LessonResource::collection($lessons), 'Lessons retrieved successfully.');
    }

    /**
     * Store a new lesson for a group.
     */
    public function store(StoreLessonRequest $request, Group $group)
    {
        // Ensure user can update the group (teacher/assistant of this group)
        $this->authorize('update', $group);
        $this->authorize('create', Lesson::class);

        $data = $request->validated();
        $data['group_id'] = $group->id;

        $lesson = Lesson::create($data);
        $lesson->load('group');

        return $this->success(new LessonResource($lesson), 'Lesson created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson)
    {
        $this->authorize('view', $lesson);
        $lesson->load(['group', 'resources', 'attendances.student', 'assessments']);

        return $this->success(new LessonResource($lesson), 'Lesson retrieved successfully.');
    }

    /**
     * Update the specified resource.
     */
    public function update(\App\Http\Requests\UpdateLessonRequest $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson);

        $lesson->update($request->validated());
        $lesson->load('group');

        return $this->success(new LessonResource($lesson), 'Lesson updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson)
    {
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return $this->success(null, 'Lesson deleted successfully.', 204);
    }
}
