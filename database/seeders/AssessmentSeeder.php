<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Group;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AssessmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (Group::with([
            'lessons' => function ($q) {
                $q->orderBy('scheduled_at');
            }
        ])->get() as $group) {
            $lessons = $group->lessons;
            if ($lessons->isEmpty()) {
                continue;
            }

            // Create an assessment roughly every 4 lessons
            foreach ($lessons as $index => $lesson) {
                if (($index + 1) % 4 === 0) {
                    $template = $this->pickTemplate();
                    // Schedule assessment 2 days after the lesson
                    $scheduledAt = $lesson->scheduled_at->copy()->addDays(2);

                    Assessment::create([
                        'center_id' => $group->center_id,
                        'group_id' => $group->id,
                        'lesson_id' => $lesson->id,
                        'title' => "{$template} - " . ($index + 1),
                        'max_score' => 100,
                        'scheduled_at' => $scheduledAt,
                    ]);
                }
            }
        }
    }

    private function pickTemplate(): string
    {
        $templates = SeedBlueprints::assessmentTemplates();
        return $templates[array_rand($templates)];
    }
}
