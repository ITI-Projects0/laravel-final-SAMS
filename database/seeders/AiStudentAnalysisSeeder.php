<?php

namespace Database\Seeders;

use App\Models\AiStudentAnalysis;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiStudentAnalysisSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faker = fake();
        $staffPool = User::role('teacher')->get()->concat(User::role('assistant')->get());

        if ($staffPool->isEmpty()) {
            return;
        }

        $students = User::role('student')->take(20)->get();

        foreach ($students as $student) {
            $membership = GroupStudent::where('student_id', $student->id)->first();
            if (!$membership) {
                continue;
            }

            $group = Group::find($membership->group_id);
            if (!$group) {
                continue;
            }

            AiStudentAnalysis::firstOrCreate([
                'center_id' => $group->center_id,
                'student_id' => $student->id,
            ], [
                'requested_by' => $staffPool->random()->id,
                'summary' => "Performance summary for {$student->name}",
                'details' => $faker->paragraph(),
            ]);
        }
    }
}
