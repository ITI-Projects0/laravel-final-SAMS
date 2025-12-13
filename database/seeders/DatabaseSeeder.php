<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CenterSeeder::class,
            TeacherSeeder::class,
            AssistantSeeder::class,
            ParentSeeder::class,
            StudentSeeder::class,
            StaticAccountsSeeder::class,
            ParentStudentLinkSeeder::class,
            GroupSeeder::class,
            GroupStudentSeeder::class,
            LessonSeeder::class,
            LessonResourceSeeder::class,
            AssessmentSeeder::class,
            AssessmentResultSeeder::class,
            AttendanceSeeder::class,
            AiStudentAnalysisSeeder::class,
        ]);
    }
}
