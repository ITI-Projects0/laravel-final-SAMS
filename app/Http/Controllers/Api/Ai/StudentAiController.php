<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\StudentAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAiController extends Controller
{
    public function __construct(protected StudentAiService $service) {}

    public function generateQuiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_title' => 'required|string|max:255',
            'number_of_questions' => 'nullable|integer|min:1|max:10',
        ]);
        $count = $data['number_of_questions'] ?? 5;

        try {
            $quiz = $this->service->generateQuiz($data['lesson_title'], $count);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'quiz' => [],
            ], 500);
        }

        return response()->json($quiz);
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_text' => 'required|string|max:5000',
        ]);

        try {
            $summary = $this->service->lessonSummary($data['lesson_text']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'summary' => '',
            ], 500);
        }

        return response()->json([
            'summary' => $summary,
        ]);
    }

    public function studyPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $plan = $this->service->studyPlan((int) $data['student_id']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'plan' => '',
            ], 500);
        }

        return response()->json([
            'plan' => $plan,
        ]);
    }
}
