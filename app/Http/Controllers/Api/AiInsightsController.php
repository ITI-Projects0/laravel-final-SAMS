<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Services\AiClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiInsightsController extends Controller
{
    public function __construct(protected AiClient $ai)
    {
    }

    public function insights(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'class_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        try {
            $stats = $this->getStats($filters);
        } catch (\Throwable $e) {
            return $this->error(
                message: 'Failed to build insights.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }

        $prompt = $this->buildInsightsPrompt($stats);

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an assistant that summarizes attendance and grades insights for managers in concise English. Keep it under 6 lines.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        try {
            $reply = $this->ai->chat($messages);
        } catch (\Throwable $e) {
            return $this->error(
                message: 'AI service error.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }

        return $this->success([
            'insights' => $reply,
        ]);
    }

    /**
     * Collect core stats from attendance and grades.
     */
    protected function getStats(array $filters): array
    {
        $groupId = $filters['class_id'] ?? null;
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : null;

        $attendanceQuery = Attendance::query();
        if ($groupId) {
            $attendanceQuery->where('group_id', $groupId);
        }
        if ($from) {
            $attendanceQuery->whereDate('date', '>=', $from);
        }
        if ($to) {
            $attendanceQuery->whereDate('date', '<=', $to);
        }

        $attendanceRecords = (clone $attendanceQuery)->get(['date', 'status', 'group_id']);
        $attendanceTotal = $attendanceRecords->count();
        $presentCount = $attendanceRecords->where('status', 'present')->count();
        $avgAttendanceRate = $attendanceTotal > 0 ? round($presentCount / $attendanceTotal, 3) : 0.0;

        $dayStats = [];
        foreach ($attendanceRecords as $rec) {
            $dayName = Carbon::parse($rec->date)->format('l');
            $dayStats[$dayName] = $dayStats[$dayName] ?? ['present' => 0, 'total' => 0];
            $dayStats[$dayName]['total'] += 1;
            if ($rec->status === 'present') {
                $dayStats[$dayName]['present'] += 1;
            }
        }

        $lowestAttendanceDay = '';
        $lowestDayRate = null;
        foreach ($dayStats as $day => $data) {
            $rate = $data['total'] > 0 ? $data['present'] / $data['total'] : 0;
            if ($lowestDayRate === null || $rate < $lowestDayRate) {
                $lowestDayRate = $rate;
                $lowestAttendanceDay = $day;
            }
        }

        $groupStats = [];
        foreach ($attendanceRecords as $rec) {
            if (!$rec->group_id) {
                continue;
            }
            $groupStats[$rec->group_id] = $groupStats[$rec->group_id] ?? ['present' => 0, 'total' => 0];
            $groupStats[$rec->group_id]['total'] += 1;
            if ($rec->status === 'present') {
                $groupStats[$rec->group_id]['present'] += 1;
            }
        }

        $groupBreakdown = collect($groupStats)
            ->map(function ($data, $gId) {
                $rate = $data['total'] > 0 ? $data['present'] / $data['total'] : 0;
                return ['group_id' => $gId, 'rate' => $rate];
            });

        $groupNames = Group::whereIn('id', $groupBreakdown->pluck('group_id')->filter())->pluck('name', 'id');
        $groupsLowAttendance = $groupBreakdown
            ->filter(fn($g) => $g['rate'] < 0.75)
            ->pluck('group_id')
            ->map(fn($id) => $groupNames[$id] ?? "Group #{$id}")
            ->values()
            ->all();

        $totalStudentsQuery = GroupStudent::query()->where('status', 'approved');
        if ($groupId) {
            $totalStudentsQuery->where('group_id', $groupId);
        }
        $totalStudents = (int) $totalStudentsQuery->distinct('student_id')->count('student_id');

        $gradesQuery = AssessmentResult::query()
            ->join('assessments', 'assessment_results.assessment_id', '=', 'assessments.id')
            ->join('groups', 'assessments.group_id', '=', 'groups.id');

        if ($groupId) {
            $gradesQuery->where('assessments.group_id', $groupId);
        }
        if ($from) {
            $gradesQuery->whereDate('assessments.scheduled_at', '>=', $from);
        }
        if ($to) {
            $gradesQuery->whereDate('assessments.scheduled_at', '<=', $to);
        }

        $avgGrade = (float) $gradesQuery->avg('assessment_results.score');

        $subjectStats = (clone $gradesQuery)
            ->selectRaw('COALESCE(groups.subject, "عام") as subject')
            ->selectRaw('AVG(assessment_results.score) as avg_score')
            ->groupBy('subject')
            ->orderBy('avg_score', 'asc')
            ->limit(3)
            ->get();

        $lowestSubjects = $subjectStats->pluck('subject')->all();
        $improvingGroups = [];
        $recentStart = Carbon::now()->subDays(14)->startOfDay();
        $previousStart = Carbon::now()->subDays(28)->startOfDay();
        $previousEnd = $recentStart->copy()->subDay();
        $recentRates = Attendance::query()
            ->selectRaw('group_id')
            ->selectRaw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) as rate')
            ->when($groupId, fn($q) => $q->where('group_id', $groupId))
            ->whereDate('date', '>=', $recentStart)
            ->groupBy('group_id')
            ->pluck('rate', 'group_id');
        $previousRates = Attendance::query()
            ->selectRaw('group_id')
            ->selectRaw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) as rate')
            ->when($groupId, fn($q) => $q->where('group_id', $groupId))
            ->whereBetween('date', [$previousStart, $previousEnd])
            ->groupBy('group_id')
            ->pluck('rate', 'group_id');
        foreach ($recentRates as $gId => $rate) {
            $prev = $previousRates[$gId] ?? null;
            if ($prev !== null && $rate > $prev + 0.05) {
                $improvingGroups[] = $groupNames[$gId] ?? "Group #{$gId}";
            }
        }

        return [
            'total_students' => $totalStudents,
            'avg_attendance_rate' => $avgAttendanceRate,
            'lowest_attendance_day' => $lowestAttendanceDay,
            'groups_low_attendance' => $groupsLowAttendance,
            'avg_grade' => round($avgGrade, 2),
            'lowest_subjects' => $lowestSubjects,
            'improving_groups' => $improvingGroups,
        ];
    }

    protected function buildInsightsPrompt(array $stats): string
    {
        return <<<TXT
Give a short summary (under 6 lines) for the school manager in clear English:
- Attendance issues: attendance rate {$stats['avg_attendance_rate']}, weakest day: {$stats['lowest_attendance_day']}, weak groups: {$this->listToString($stats['groups_low_attendance'])}
- Weak subjects: {$this->listToString($stats['lowest_subjects'])}
- Positive trend (groups improving attendance): {$this->listToString($stats['improving_groups'])}
Additional info: total students: {$stats['total_students']}, average grade: {$stats['avg_grade']}
Be concise: 2-3 lines per bullet, max 6 lines total.
TXT;
    }

    protected function listToString(array $items): string
    {
        if (empty($items)) {
            return 'لا يوجد بيانات';
        }
        return implode(', ', $items);
    }
}