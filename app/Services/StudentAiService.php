<?php

namespace App\Services;

use App\Models\AssessmentResult;
use App\Models\Attendance;
use App\Models\GroupStudent;
use Carbon\Carbon;

class StudentAiService
{
    public function __construct(protected AiClient $ai) {}

    public function generateQuiz(string $lessonTitle, int $count = 5): array
    {
        $prompt = <<<TXT
Generate {$count} multiple-choice questions with 4 options each and mark the correct answer.
Lesson: {$lessonTitle}
Return as lines in format: Q: ... | A: [options] | Correct: option text
TXT;

        $reply = $this->ai->chat([
            ['role' => 'system', 'content' => 'You generate concise MCQs with 4 options and show the correct answer.'],
            ['role' => 'user', 'content' => $prompt],
        ]);

        return ['quiz' => $this->parseQuiz($reply)];
    }

    public function lessonSummary(string $text): string
    {
        $prompt = <<<TXT
Summarize the following lesson in English:
- 3 to 5 bullet points
- Add 2 review questions

Lesson text:
{$text}
TXT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You summarize lessons with bullets and add 2 review questions.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    public function studyPlan(int $studentId): string
    {
        $weakSubjects = $this->guessWeakSubjects($studentId);
        $attendanceIssues = $this->recentAttendanceIssues($studentId);

        $prompt = <<<TXT
Create a simple 7-day study plan for the student.
- Weak subjects: {$this->list($weakSubjects)}
- Attendance issues: {$this->list($attendanceIssues)}
- Keep it concise and practical (bullets per day), in English.
TXT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You create practical 7-day study plans for students.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    protected function parseQuiz(string $text): array
    {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)));
        $quiz = [];
        foreach ($lines as $line) {
            // crude parse: Q: ... | A: opt1; opt2; opt3; opt4 | Correct: ...
            $parts = array_map('trim', explode('|', $line));
            $q = $parts[0] ?? '';
            $optsPart = $parts[1] ?? '';
            $corrPart = $parts[2] ?? '';
            $question = preg_replace('/^Q\\s*:\\s*/i', '', $q);
            $optionsRaw = preg_replace('/^A\\s*:\\s*/i', '', $optsPart);
            $options = array_filter(array_map('trim', preg_split('/[,;]+/', $optionsRaw)));
            $correct = trim(preg_replace('/^Correct\\s*:\\s*/i', '', $corrPart));
            if ($question) {
                $quiz[] = [
                    'question' => $question,
                    'options' => array_values($options),
                    'correct' => $correct,
                ];
            }
        }
        return $quiz;
    }

    protected function guessWeakSubjects(int $studentId): array
    {
        $results = AssessmentResult::query()
            ->join('assessments', 'assessment_results.assessment_id', '=', 'assessments.id')
            ->where('assessment_results.student_id', $studentId)
            ->selectRaw('COALESCE(assessments.title, "General") as subject, AVG(assessment_results.score) as avg_score')
            ->groupBy('subject')
            ->orderBy('avg_score', 'asc')
            ->limit(3)
            ->pluck('subject')
            ->all();
        return $results;
    }

    protected function recentAttendanceIssues(int $studentId): array
    {
        $from = Carbon::now()->subDays(30)->startOfDay();
        $records = Attendance::query()
            ->where('student_id', $studentId)
            ->whereDate('date', '>=', $from)
            ->where('status', 'absent')
            ->orderByDesc('date')
            ->take(5)
            ->get(['date']);
        return $records->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->all();
    }

    protected function list(array $items): string
    {
        return empty($items) ? 'none' : implode(', ', $items);
    }
}
