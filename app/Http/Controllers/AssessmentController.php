<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\UpdateAssessmentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AssessmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssessmentRequest $request, \App\Models\Lesson $lesson)
    {
        $this->authorize('create', Assessment::class);

        // Additional check: Ensure the user can actually add an assessment to THIS lesson's group
        // The 'create' policy is generic, so we might want to check if they can update the lesson or group
        // But for now, let's rely on the fact that if they can create, they are a Teacher/Assistant/Admin.
        // If Teacher, we must ensure the lesson belongs to their group.
        if (auth()->user()->hasRole('teacher')) {
            if ($lesson->group->teacher_id !== auth()->id()) {
                abort(403, 'You can only add assessments to your own groups.');
            }
        }
        // If Assistant, ensure lesson is in their center
        if (auth()->user()->hasRole('assistant')) {
            if ($lesson->group->center_id !== auth()->user()->center_id) {
                abort(403, 'You can only add assessments to groups in your center.');
            }
        }

        $data = $request->validated();
        $data['lesson_id'] = $lesson->id;
        $data['group_id'] = $lesson->group_id;

        // Infer center_id from group
        $group = $lesson->group;
        if ($group) {
            $data['center_id'] = $group->center_id;
        }

        if (!isset($data['max_score'])) {
            $data['max_score'] = 100;
        }

        $assessment = Assessment::create($data);

        return response()->json([
            'message' => 'Assessment created successfully.',
            'data' => $assessment
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Assessment $assessment)
    {
        $this->authorize('view', $assessment);

        $assessment->load(['results']);
        $group = $assessment->group;

        // Get all students in the group
        $students = $group->students()
            ->select('users.id', 'users.name', 'users.email')
            ->wherePivot('status', 'approved')
            ->get();

        // Attach results to students
        $resultsMap = $assessment->results->keyBy('student_id');
        $students->transform(function ($student) use ($resultsMap) {
            $result = $resultsMap->get($student->id);
            $student->score = $result ? $result->score : null;
            $student->feedback = $result ? $result->feedback : null;
            return $student;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'assessment' => $assessment,
                'students' => $students
            ]
        ]);
    }

    public function storeResult(\App\Http\Requests\StoreAssessmentResultRequest $request, Assessment $assessment)
    {
        // Grading is considered an update action on the assessment context
        $this->authorize('update', $assessment);

        $data = $request->validated();

        $result = $assessment->results()->updateOrCreate(
            ['student_id' => $data['student_id']],
            ['score' => $data['score'], 'feedback' => $data['feedback'] ?? null]
        );

        return response()->json([
            'success' => true,
            'message' => 'Grade saved successfully.',
            'data' => $result
        ]);
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment)
    {
        $this->authorize('update', $assessment);

        $data = $request->validated();

        if (!isset($data['max_score'])) {
            $data['max_score'] = $assessment->max_score;
        }

        $assessment->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Assessment updated successfully.',
            'data' => $assessment
        ]);
    }

    public function destroy(Assessment $assessment)
    {
        $this->authorize('delete', $assessment);

        $assessment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assessment deleted successfully.'
        ]);
    }
}
