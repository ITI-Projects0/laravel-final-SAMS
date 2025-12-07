<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\ParentAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentAiController extends Controller
{
    public function __construct(protected ParentAiService $service) {}

    public function weeklySummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $result = $this->service->weeklySummary((int) $data['student_id']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'summary' => '',
            ], 500);
        }

        return response()->json([
            'summary' => $result['summary'],
            'data' => $result['data'],
        ]);
    }

    public function explain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'text' => 'required|string|max:5000',
        ]);

        try {
            $reply = $this->service->explainReport((int) $data['student_id'], $data['text']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'summary' => '',
            ], 500);
        }

        return response()->json([
            'summary' => $reply,
        ]);
    }
}
