<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\GroupStudent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssessmentResultSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faker = fake();

        // Only generate results for past assessments
        $pastAssessments = Assessment::where('scheduled_at', '<', now())->get();

        foreach ($pastAssessments as $assessment) {
            $memberIds = GroupStudent::where('group_id', $assessment->group_id)
                ->pluck('student_id');

            foreach ($memberIds as $studentId) {
                AssessmentResult::firstOrCreate([
                    'assessment_id' => $assessment->id,
                    'student_id' => $studentId,
                ], [
                    'score' => $faker->numberBetween(45, 100),
                    'feedback' => $faker->sentence(12),
                ]);
            }
        }
    }
}
