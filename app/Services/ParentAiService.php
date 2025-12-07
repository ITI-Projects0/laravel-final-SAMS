<?php

namespace App\Services;

use App\Models\AssessmentResult;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class ParentAiService
{
    public function __construct(protected AiClient $ai) {}

    public function weeklySummary(int $studentId): array
    {
        $student = User::findOrFail($studentId);
        $from = Carbon::now()->subDays(7)->startOfDay();

        $attendance = Attendance::query()
            ->where('student_id', $studentId)
            ->whereDate('date', '>=', $from)
            ->get(['date', 'status']);

        $attTotal = $attendance->count();
        $attPresent = $attendance->where('status', 'present')->count();
        $attRate = $attTotal > 0 ? round($attPresent / $attTotal, 3) : 0.0;
        $missed = $attendance->where('status', 'absent')
            ->sortByDesc('date')
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->all();

        $grades = AssessmentResult::query()
            ->join('assessments', 'assessment_results.assessment_id', '=', 'assessments.id')
            ->where('assessment_results.student_id', $studentId)
            ->whereDate('assessments.scheduled_at', '>=', $from)
            ->selectRaw('assessment_results.score as score, assessments.title as title')
            ->get();

        $avgGrade = (float) $grades->avg('score');
        $recentGrades = $grades->map(fn($g) => "{$g->title}: {$g->score}")->take(5)->all();

        $payload = [
            'student' => $student->only(['id', 'name']),
            'attendance_rate' => $attRate,
            'missed_days' => $missed,
            'avg_grade' => round($avgGrade, 2),
            'recent_grades' => $recentGrades,
        ];

        $prompt = <<<TXT
أنت مساعد تكتب ملخص أسبوعي لولي الأمر بالعربية المصرية.
البيانات:
- اسم الطالب: {$student->name}
- معدل الحضور آخر ٧ أيام: {$attRate}
- أيام الغياب: {$this->list($missed)}
- متوسط الدرجات آخر ٧ أيام: {$payload['avg_grade']}
- درجات حديثة: {$this->list($recentGrades)}

المطلوب:
- 3 إلى 5 نقاط مختصرة توضح التحسن أو التراجع أو الواجبات الناقصة أو غياب غير منتظم.
- اقتراح 1 أو 2 خطوة للولي.
- لغة بسيطة وواضحة.
TXT;

        $summary = $this->ai->chat([
            ['role' => 'system', 'content' => 'أنت مساعد يكتب ملخص أداء أسبوعي لولي الأمر بالعربية المصرية، مختصر وواضح.'],
            ['role' => 'user', 'content' => $prompt],
        ]);

        return ['summary' => $summary, 'data' => $payload];
    }

    public function explainReport(int $studentId, string $text): string
    {
        $student = User::findOrFail($studentId);
        $prompt = <<<TXT
فسّر التقرير التالي لولي الأمر بلغة بسيطة بالعربية المصرية، واذكر 2-3 إجراءات مقترحة:
اسم الطالب: {$student->name}
النص:
{$text}
TXT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'أنت مساعد يشرح تقارير الدرجات/التقييم لولي الأمر بالعربية المصرية بلغة بسيطة.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    protected function list(array $items): string
    {
        return empty($items) ? 'لا يوجد' : implode(', ', $items);
    }
}
