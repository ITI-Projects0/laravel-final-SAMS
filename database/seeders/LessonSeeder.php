<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Lesson;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LessonSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $startDate = Carbon::create(2025, 7, 1);
        $endDate = Carbon::create(2025, 12, 30);

        foreach (Group::all() as $group) {
            $currentDate = $startDate->copy();
            $lessonIndex = 1;

            $scheduleDays = $group->schedule_days ?? ['monday', 'thursday']; // Default if null
            // Normalize days to lowercase
            $scheduleDays = array_map('strtolower', $scheduleDays);
            
            $timeParts = explode(':', $group->schedule_time ?? '10:00:00');
            $hour = (int) $timeParts[0];
            $minute = (int) $timeParts[1];

            while ($currentDate->lte($endDate)) {
                $dayName = strtolower($currentDate->format('l'));

                if (in_array($dayName, $scheduleDays)) {
                    Lesson::create([
                        'group_id' => $group->id,
                        'title' => "{$group->subject} Session {$lessonIndex}",
                        'description' => "Lesson {$lessonIndex} explores key {$group->subject} topics.",
                        'scheduled_at' => $currentDate->copy()->setTime($hour, $minute, 0),
                        'video_url' => "https://www.youtube.com/embed/dQw4w9WgXcQ",
                    ]);
                    $lessonIndex++;
                }

                $currentDate->addDay();
            }
        }
    }
}
