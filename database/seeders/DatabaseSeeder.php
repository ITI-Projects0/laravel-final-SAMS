<?php

namespace Database\Seeders;

use App\Models\AiStudentAnalysis;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = fake();

        // ---------- PERMISSIONS ----------
        Permission::firstOrCreate(['name' => 'manage centers', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage teachers', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage students', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage groups', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage attendance', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage grades', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'view student performance', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'use ai analysis', 'guard_name' => 'api']);

        // ---------- ROLES ----------
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $roleCenter = Role::firstOrCreate(['name' => 'center_admin', 'guard_name' => 'api']);
        $roleTeacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'api']);
        $roleAssistant = Role::firstOrCreate(['name' => 'assistant', 'guard_name' => 'api']);
        $roleStudent = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
        $roleParent = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'api']);

        // ---------- ASSIGN PERMISSIONS TO ROLES ----------
        $roleAdmin->givePermissionTo(Permission::all());
        $roleCenter->givePermissionTo([
            'manage centers',
            'manage teachers',
            'manage students',
            'manage groups',
            'manage attendance',
            'manage grades',
            'view student performance',
            'use ai analysis'
        ]);
        $roleTeacher->givePermissionTo([
            'manage groups',
            'manage attendance',
            'manage grades',
            'view student performance',
            'use ai analysis'
        ]);
        $roleAssistant->givePermissionTo(['manage attendance', 'manage groups']);
        $roleParent->givePermissionTo(['view student performance']);

        // ---------- USERS ----------
        $admin = User::factory()->create([
            'name' => 'SAMS Super Admin',
            'email' => 'ahmed.alla56756@gmail.com',
            'status' => 'active',
        ]);
        $admin->assignRole($roleAdmin);

        // ---------- STUDENT USER ----------
        $studentUser = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student.user@example.com',
            'status' => 'active',
        ]);
        $studentUser->assignRole('student');

        // ---------- PARENT USER ----------
        $parentUser = User::factory()->create([
            'name' => 'Parent User',
            'email' => 'parent.user@example.com',
            'status' => 'active',
        ]);
        $parentUser->assignRole('parent');

        $centerAdmins = User::factory(3)->create();
        $teachers = User::factory(6)->create();
        $assistants = User::factory(3)->create();
        $parents = User::factory(5)->create();
        $students = User::factory(20)->create();

        // ---------- CENTERS ----------
        $centers = collect();
        foreach ($centerAdmins as $centerAdmin) {
            $centers->push(
                Center::factory()->create([
                    'user_id' => $centerAdmin->id,
                ])
            );
        }

        // ---------- GROUPS ----------
        $groups = collect();
        foreach ($centers as $center) {
            $groupsForCenter = Group::factory(3)->create([
                'center_id' => $center->id,
                'teacher_id' => $teachers->random()->id,
            ]);

            $groups = $groups->merge($groupsForCenter);
        }

        // ---------- PARENT–STUDENT LINKS ----------
        $parentStudentLinks = collect();
        foreach ($parents as $parent) {
            $randomStudents = $students->random(3);
            foreach ($randomStudents as $student) {
                $parentStudentLinks->push(
                    ParentStudentLink::factory()->create([
                        'parent_id' => $parent->id,
                        'student_id' => $student->id,
                        'relationship' => $faker->randomElement(['father', 'mother', 'guardian']),
                    ])
                );
            }
        }

        // ---------- GROUP–STUDENT ENROLLMENT ----------
        $groupStudents = collect();
        foreach ($groups as $group) {
            $randomStudents = $students->random(6);
            foreach ($randomStudents as $student) {
                $groupStudents->push(
                    GroupStudent::factory()->create([
                        'group_id' => $group->id,
                        'student_id' => $student->id,
                        'status' => 'approved',
                        'is_pay' => $faker->boolean(70),
                        'joined_at' => $faker->dateTimeBetween('-2 months', 'now'),
                    ])
                );
            }
        }

        // ---------- LESSONS + RESOURCES ----------
        $lessons = collect();
        foreach ($groups as $group) {
            $lessonsForGroup = Lesson::factory(4)->create([
                'group_id' => $group->id,
            ]);
            $lessons = $lessons->merge($lessonsForGroup);
        }

        $resourceTypes = ['video', 'file', 'link'];
        foreach ($lessons as $lesson) {
            foreach ($resourceTypes as $type) {
                LessonResource::factory()->create([
                    'lesson_id' => $lesson->id,
                    'type' => $type,
                ]);
            }
        }

        // ---------- ASSESSMENTS + RESULTS ----------
        $assessments = collect();
        foreach ($groups as $group) {
            $assessmentsForGroup = Assessment::factory(2)->create([
                'center_id' => $group->center_id,
                'group_id' => $group->id,
            ]);
            $assessments = $assessments->merge($assessmentsForGroup);
        }

        $enrollmentsByGroup = $groupStudents->groupBy('group_id');
        foreach ($assessments as $assessment) {
            $studentsInGroup = $enrollmentsByGroup->get($assessment->group_id, collect());
            foreach ($studentsInGroup->pluck('student_id')->unique() as $studentId) {
                AssessmentResult::factory()->create([
                    'assessment_id' => $assessment->id,
                    'student_id' => $studentId,
                    'score' => $faker->randomFloat(2, 40, 100),
                    'remarks' => $faker->sentence(),
                ]);
            }
        }

        // ---------- ATTENDANCE ----------
        $staffPool = $teachers->concat($assistants);
        $groupLookup = $groups->keyBy('id');
        foreach ($groupStudents as $membership) {
            $group = $groupLookup->get($membership->group_id);
            if (!$group)
                continue;
            Attendance::factory()->create([
                'center_id' => $group->center_id,
                'group_id' => $group->id,
                'student_id' => $membership->student_id,
                'date' => now()->subDays($faker->numberBetween(0, 10))->toDateString(),
                'status' => $faker->randomElement(['present', 'absent', 'late', 'excused']),
                'marked_by' => $staffPool->random()->id,
            ]);
        }

        // ---------- AI STUDENT ANALYSIS ----------
        foreach ($students->take(5) as $student) {
            $groupRecord = $groupStudents->firstWhere('student_id', $student->id);
            if (!$groupRecord)
                continue;
            $group = $groupLookup->get($groupRecord->group_id);
            AiStudentAnalysis::factory()->create([
                'center_id' => $group->center_id,
                'student_id' => $student->id,
                'requested_by' => $staffPool->random()->id,
                'summary' => "Performance summary for {$student->name}",
                'details' => $faker->paragraph(),
            ]);
        }
    }
}
