<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\CenterAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CenterAiController extends Controller
{
    public function __construct(protected CenterAiService $service) {}

    public function insights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
        ]);

        try {
            $insights = $this->service->insights($data['class_id'] ?? null, $data['group_id'] ?? null);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'insights' => '',
            ], 500);
        }

        return response()->json([
            'insights' => $insights,
        ]);
    }

    public function attendanceForecast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
        ]);

        try {
            $forecast = $this->service->attendanceForecast($data['class_id'] ?? null, $data['group_id'] ?? null);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'AI service error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'forecast' => '',
            ], 500);
        }

        return response()->json([
            'forecast' => $forecast,
        ]);
    }
}
