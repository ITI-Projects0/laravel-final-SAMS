<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class GroupStudentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faker = fake();

        foreach (Group::all() as $group) {
            $allStudents = User::role('student')->where('center_id', $group->center_id)->get();
            $studentCount = $allStudents->count();
            if ($studentCount === 0) {
                continue;
            }

            $maxMembers = min(30, $studentCount);
            $take = $maxMembers <= 15 ? $maxMembers : $faker->numberBetween(15, $maxMembers);

            $students = $allStudents->shuffle()->take($take);

            if ($students->isEmpty()) {
                continue;
            }

            foreach ($students as $student) {
                GroupStudent::firstOrCreate([
                    'group_id' => $group->id,
                    'student_id' => $student->id,
                ], [
                    'status' => 'approved',
                    'is_pay' => $faker->boolean(85),
                    'joined_at' => Carbon::create(2025, 7, 1)->addDays($faker->numberBetween(0, 14))->toDateString(),
                ]);
            }
        }
    }
}
