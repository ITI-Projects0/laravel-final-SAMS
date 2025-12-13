<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faker = fake();
        $statuses = SeedBlueprints::attendanceStatuses();

        foreach (Group::with(['lessons', 'students'])->get() as $group) {
            $pastLessons = $group->lessons->where('scheduled_at', '<', Carbon::now());
            
            if ($pastLessons->isEmpty()) {
                continue;
            }

            $assistants = User::role('assistant')
                ->where('center_id', $group->center_id)
                ->pluck('id')
                ->all();

            $staffPool = collect(array_merge([$group->teacher_id], $assistants))
                ->filter()
                ->values();

            if ($staffPool->isEmpty()) {
                continue;
            }

            foreach ($pastLessons as $lesson) {
                foreach ($group->students as $student) {
                    // 90% chance of being present
                    $status = $faker->boolean(90) ? 'present' : $faker->randomElement(['absent', 'late', 'excused']);
                    
                    Attendance::firstOrCreate([
                        'group_id' => $group->id,
                        'student_id' => $student->id,
                        'lesson_id' => $lesson->id,
                    ], [
                        'center_id' => $group->center_id,
                        'date' => $lesson->scheduled_at->toDateString(),
                        'status' => $status,
                        'marked_by' => $staffPool->random(),
                    ]);
                }
            }
        }
    }
}
