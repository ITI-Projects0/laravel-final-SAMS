<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (Center::all() as $center) {
            $teacherPool = User::role('teacher')->where('center_id', $center->id)->get();
            if ($teacherPool->isEmpty()) {
                continue;
            }

            $subjects = SeedBlueprints::groupSubjects();
            $scheduleBlocks = SeedBlueprints::scheduleBlocks();

            foreach (range(1, SeedBlueprints::groupsPerCenter()) as $index) {
                $schedule = $scheduleBlocks[array_rand($scheduleBlocks)];
                $subject = $subjects[array_rand($subjects)];
                $teacher = $teacherPool->random();

                Group::create([
                    'name' => "{$subject} Cohort {$index}",
                    'description' => "A practical {$subject} cohort that meets twice a week.",
                    'subject' => $subject,
                    'center_id' => $center->id,
                    'teacher_id' => $teacher->id,
                    'academic_year' => '2025/2026',
                    'schedule_days' => $schedule['days'],
                    'schedule_time' => $schedule['time'],
                    'sessions_count' => 30,
                    'is_active' => true,
                ]);
            }
        }
    }
}
