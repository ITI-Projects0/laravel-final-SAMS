<?php

namespace Database\Seeders;

class SeedBlueprints
{
    public static function centerSpecs(): array
    {
        return [
            [
                'name' => 'Demo Learning Hub',
                'subdomain' => 'demo-learning-hub',
                'admin_name' => 'Demo Center Admin',
                'admin_email' => 'center.admin@example.com',
                'primary_color' => '#1b5f9e',
                'secondary_color' => '#ffcf33',
                'logo_url' => 'https://assets.sams.com/logos/demo.png',
                'teacher_count' => 5,
                'assistant_count' => 3,
                'parent_count' => 12,
                'student_count' => 45,
                'extra_teachers' => [
                    ['name' => 'Demo Teacher', 'email' => 'teacher.demo@example.com'],
                ],
                'extra_assistants' => [
                    ['name' => 'Demo Assistant', 'email' => 'assistant.demo@example.com'],
                ],
                'extra_parents' => [
                    ['name' => 'Demo Parent 1', 'email' => 'parent1.demo@example.com'],
                    ['name' => 'Demo Parent 2', 'email' => 'parent2.demo@example.com'],
                    ['name' => 'Demo Parent 3', 'email' => 'parent3.demo@example.com'],
                ],
                'extra_students' => [
                    ['name' => 'Demo Student 1', 'email' => 'student1.demo@example.com'],
                    ['name' => 'Demo Student 2', 'email' => 'student2.demo@example.com'],
                    ['name' => 'Demo Student 3', 'email' => 'student3.demo@example.com'],
                    ['name' => 'Demo Student 4', 'email' => 'student4.demo@example.com'],
                    ['name' => 'Demo Student 5', 'email' => 'student5.demo@example.com'],
                ],
            ],
            [
                'name' => 'Nile Scholars Academy',
                'subdomain' => 'nile-scholars',
                'admin_name' => 'Nile Scholars Admin',
                'admin_email' => 'admin@nile-scholars.com',
                'primary_color' => '#0d3b66',
                'secondary_color' => '#f4d35e',
                'logo_url' => 'https://assets.sams.com/logos/nile.png',
                'teacher_count' => 6,
                'assistant_count' => 2,
                'parent_count' => 16,
                'student_count' => 52,
            ],
            [
                'name' => 'Future Minds Center',
                'subdomain' => 'future-minds',
                'admin_name' => 'Future Minds Admin',
                'admin_email' => 'admin@future-minds.com',
                'primary_color' => '#2e7f4d',
                'secondary_color' => '#f6a01a',
                'logo_url' => 'https://assets.sams.com/logos/future.png',
                'teacher_count' => 5,
                'assistant_count' => 3,
                'parent_count' => 14,
                'student_count' => 48,
            ],
        ];
    }

    public static function groupSubjects(): array
    {
        return [
            'Mathematics',
            'Arabic Language',
            'Science Explorations',
            'English Communication',
            'Digital Literacy',
            'Art & Design',
        ];
    }

    public static function scheduleBlocks(): array
    {
        return [
            ['days' => ['saturday', 'monday'], 'time' => '17:00:00'],
            ['days' => ['tuesday', 'thursday'], 'time' => '18:30:00'],
            ['days' => ['sunday', 'wednesday'], 'time' => '15:30:00'],
            ['days' => ['monday', 'thursday'], 'time' => '16:00:00'],
        ];
    }

    public static function resourceTypes(): array
    {
        return ['video', 'file', 'link'];
    }

    public static function attendanceStatuses(): array
    {
        return ['present', 'absent', 'late', 'excused'];
    }

    public static function assessmentTemplates(): array
    {
        return [
            'Progress Quiz',
            'Unit Review',
            'Project Presentation',
            'Skill Check',
        ];
    }

    public static function groupsPerCenter(): int
    {
        return 4;
    }

    public static function lessonsPerGroup(): int
    {
        return 6;
    }

    public static function assessmentsPerGroup(): int
    {
        return 3;
    }
}
