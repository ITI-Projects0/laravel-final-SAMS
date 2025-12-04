<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Lesson;
use Carbon\Carbon;

class GroupScheduleService
{
    /**
     * Generate lessons for a group based on its schedule configuration
     */
    public function generateLessons(Group $group): void
    {
        // Validate schedule settings
        if (!$group->schedule_days || !$group->schedule_time || !$group->sessions_count) {
            return;
        }

        // Delete existing lessons to regenerate
        $group->lessons()->delete();

        $days = $group->schedule_days; // ['Saturday', 'Monday', 'Wednesday']
        $time = $group->schedule_time; // '10:00:00'
        $sessionsCount = $group->sessions_count; // 12

        $currentDate = Carbon::now()->startOfDay();
        $lessonsCreated = 0;

        while ($lessonsCreated < $sessionsCount) {
            $dayName = $currentDate->format('l'); // 'Saturday', 'Monday', etc.

            if (in_array($dayName, $days)) {
                $scheduledAt = $currentDate->copy()->setTimeFromTimeString($time);

                // Skip if scheduled time is in the past
                if ($scheduledAt->isPast()) {
                    $currentDate->addDay();
                    continue;
                }

                Lesson::create([
                    'group_id' => $group->id,
                    'title' => 'Lesson ' . ($lessonsCreated + 1),
                    'scheduled_at' => $scheduledAt,
                ]);

                $lessonsCreated++;
            }

            $currentDate->addDay();

            // Safety check to prevent infinite loops
            if ($currentDate->diffInDays(Carbon::now()) > 365) {
                break;
            }
        }
    }
}
