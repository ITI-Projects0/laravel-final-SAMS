<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\UpdateAssessmentRequest;

class AssessmentController extends Controller
{
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
        // $this->authorize('create', Assessment::class); // Assuming policy exists or we skip for now

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
    /**
     * Display the specified resource.
     */
    public function show(Assessment $assessment)
    {
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

    public function storeResult(\Illuminate\Http\Request $request, Assessment $assessment)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'score' => 'required|numeric|min:0|max:' . $assessment->max_score,
            'feedback' => 'nullable|string'
        ]);

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
        $assessment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assessment deleted successfully.'
        ]);
    }
}
