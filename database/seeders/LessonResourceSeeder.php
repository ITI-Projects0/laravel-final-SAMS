<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LessonResourceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (Lesson::all() as $lesson) {
            foreach (SeedBlueprints::resourceTypes() as $type) {
                $title = ucfirst($type) . " resource for {$lesson->title}";

                $url = match ($type) {
                    'file' => "https://assets.sams.com/files/lesson-{$lesson->id}.pdf",
                    'link' => "https://learning.sams.com/resource/{$lesson->id}",
                    default => $lesson->video_url,
                };

                LessonResource::firstOrCreate([
                    'lesson_id' => $lesson->id,
                    'type' => $type,
                ], [
                    'title' => $title,
                    'url' => $url,
                ]);
            }
        }
    }
}
