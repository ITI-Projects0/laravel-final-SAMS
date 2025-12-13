<?php

namespace Database\Seeders;

use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ParentStudentLinkSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $faker = fake();
        $parents = User::role('parent')->get();

        foreach ($parents as $parent) {
            $students = User::role('student')
                ->where('center_id', $parent->center_id)
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $children = $students->shuffle()->take($faker->numberBetween(2, 4));

            foreach ($children as $child) {
                ParentStudentLink::firstOrCreate([
                    'parent_id' => $parent->id,
                    'student_id' => $child->id,
                ], [
                    'relationship' => $faker->randomElement(['father', 'mother', 'guardian']),
                ]);
            }
        }
    }
}
