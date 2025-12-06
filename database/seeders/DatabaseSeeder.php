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

        $centerAdmins = User::factory(3)->create([
            'status' => 'active',
        ])->each(function (User $user) {
            $user->assignRole('center_admin');
        });

        $teachers = User::factory(8)->create([
            'status' => 'active',
        ])->each(function (User $user) {
            $user->assignRole('teacher');
        });

        $assistants = User::factory(4)->create([
            'status' => 'active',
        ])->each(function (User $user) {
            $user->assignRole('assistant');
        });

        $parents = User::factory(6)->create([
            'status' => 'active',
        ])->each(function (User $user) {
            $user->assignRole('parent');
        });

        $students = User::factory(40)->create([
            'status' => 'active',
        ])->each(function (User $user) {
            $user->assignRole('student');
        });

        // ---------- ASSIGN ROLES TO SEEDED USERS ----------
        $centerAdmins->each(fn(User $user) => $user->assignRole($roleCenter));
        $teachers->each(fn(User $user) => $user->assignRole($roleTeacher));
        $assistants->each(fn(User $user) => $user->assignRole($roleAssistant));
        $parents->each(fn(User $user) => $user->assignRole($roleParent));
        $students->each(fn(User $user) => $user->assignRole($roleStudent));

        // ---------- CENTERS ----------
        $centers = collect();
        foreach ($centerAdmins as $centerAdmin) {
            $center = Center::factory()->create([
                'user_id' => $centerAdmin->id,
            ]);

            $centerAdmins->find($centerAdmin->id)?->update(['center_id' => $center->id]);
            $centers->push($center);
        }

        if ($centers->isNotEmpty()) {
            $primaryCenterId = $centers->first()->id;
            $studentUser->update(['center_id' => $primaryCenterId]);
            $parentUser->update(['center_id' => $primaryCenterId]);
        }

        // Demo center admin for testing
        $demoCenterAdmin = User::factory()->create([
            'name' => 'Demo Center Admin',
            'email' => 'center.admin@example.com',
            'status' => 'active',
        ]);
        $demoCenterAdmin->assignRole('center_admin');
        $demoCenter = Center::factory()->create([
            'user_id' => $demoCenterAdmin->id,
            'name' => 'Demo Center',
        ]);
        $demoCenterAdmin->update(['center_id' => $demoCenter->id]);
        $centers->push($demoCenter);
        $centerAdmins->push($demoCenterAdmin);

        // Deterministic staff & students for demo center (so the staff dashboard always has data)
        $demoTeacher = User::factory()->create([
            'name' => 'Demo Teacher',
            'email' => 'teacher.demo@example.com',
            'status' => 'active',
            'center_id' => $demoCenter->id,
        ]);
        $demoTeacher->assignRole($roleTeacher);

        $demoAssistant = User::factory()->create([
            'name' => 'Demo Assistant',
            'email' => 'assistant.demo@example.com',
            'status' => 'active',
            'center_id' => $demoCenter->id,
        ]);
        $demoAssistant->assignRole($roleAssistant);

        $demoStudents = collect();
        foreach (range(1, 8) as $index) {
            $student = User::factory()->create([
                'name' => "Demo Student {$index}",
                'email' => "student{$index}.demo@example.com",
                'status' => 'active',
                'center_id' => $demoCenter->id,
            ]);
            $student->assignRole($roleStudent);
            $demoStudents->push($student);
        }

        $demoParents = collect();
        foreach (range(1, 3) as $index) {
            $parent = User::factory()->create([
                'name' => "Demo Parent {$index}",
                'email' => "parent{$index}.demo@example.com",
                'status' => 'active',
                'center_id' => $demoCenter->id,
            ]);
            $parent->assignRole($roleParent);
            $demoParents->push($parent);
        }

        $demoGroups = collect();
        foreach (['Elite Physics Cohort', 'SAT Crash Course'] as $name) {
            $demoGroups->push(
                Group::factory()->create([
                    'center_id' => $demoCenter->id,
                    'teacher_id' => $demoTeacher->id,
                    'name' => $name,
                    'is_active' => true,
                ])
            );
        }

        foreach ($demoGroups as $group) {
            $assignedStudents = $demoStudents->shuffle()->take(6);

            foreach ($assignedStudents as $student) {
                GroupStudent::factory()->create([
                    'group_id' => $group->id,
                    'student_id' => $student->id,
                    'status' => 'approved',
                    'is_pay' => true,
                    'joined_at' => now()->subDays(random_int(5, 20)),
                ]);
            }

            Lesson::factory(4)->create([
                'group_id' => $group->id,
            ]);
        }

        foreach ($demoParents as $parent) {
            $child = $demoStudents->random();
            ParentStudentLink::factory()->create([
                'parent_id' => $parent->id,
                'student_id' => $child->id,
                'relationship' => $faker->randomElement(['father', 'mother', 'guardian']),
            ]);
        }

        foreach ($demoStudents as $student) {
            Attendance::factory()->create([
                'center_id' => $demoCenter->id,
                'group_id' => $demoGroups->random()->id,
                'student_id' => $student->id,
                'date' => now()->subDays(random_int(0, 7))->toDateString(),
                'status' => $faker->randomElement(['present', 'absent', 'late', 'excused']),
                'marked_by' => $demoTeacher->id,
            ]);
        }

        // ---------- GROUPS ----------
        $groups = collect();
        foreach ($centers as $center) {
            $centerTeachers = $teachers->where('center_id', $center->id);
            if ($centerTeachers->isEmpty()) {
                $teacher = $teachers->whereNull('center_id')->shift() ?? $teachers->random();
                $teacher->center_id = $center->id;
                $teacher->save();
                $centerTeachers = $teachers->where('center_id', $center->id);
            }

            $groupsForCenter = Group::factory(3)->create([
                'center_id' => $center->id,
                'teacher_id' => $centerTeachers->random()->id,
            ]);

            $groups = $groups->merge($groupsForCenter);
        }

        // Assign center_id to staff & students where missing
        foreach ($teachers as $teacher) {
            if (!$teacher->center_id) {
                $teacher->center_id = $centers->random()->id;
                $teacher->save();
            }
        }

        foreach ($assistants as $assistant) {
            $assistant->center_id = $centers->random()->id;
            $assistant->save();
        }

        foreach ($students as $student) {
            $student->center_id = $centers->random()->id;
            $student->save();
        }

        foreach ($parents as $parent) {
            $parent->center_id = $centers->random()->id;
            $parent->save();
        }

        // ---------- PARENT–STUDENT LINKS ----------
        $parentStudentLinks = collect();
        foreach ($parents as $parent) {
            $randomStudents = $students->where('center_id', $parent->center_id)->shuffle()->take(3);
            if ($randomStudents->isEmpty()) {
                $randomStudents = $students->shuffle()->take(3);
            }

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
            $centerStudents = $students->where('center_id', $group->center_id);
            $randomStudents = $centerStudents->isEmpty()
                ? $students->shuffle()->take(6)
                : $centerStudents->shuffle()->take(min(8, $centerStudents->count()));

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
