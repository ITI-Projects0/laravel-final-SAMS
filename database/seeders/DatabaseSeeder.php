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
            'use ai analysis',
        ]);
        $roleTeacher->givePermissionTo([
            'manage groups',
            'manage attendance',
            'manage grades',
            'view student performance',
            'use ai analysis',
        ]);
        $roleAssistant->givePermissionTo(['manage attendance', 'manage groups']);
        $roleParent->givePermissionTo(['view student performance']);

        // ---------- FIXED USERS ----------
        $admin = User::factory()->create([
            'name' => 'SAMS Super Admin',
            'email' => 'ahmed.alla56756@gmail.com',
            'status' => 'active',
        ]);
        $admin->assignRole($roleAdmin);

        $studentUser = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student.user@example.com',
            'status' => 'active',
        ]);
        $studentUser->assignRole($roleStudent);

        $parentUser = User::factory()->create([
            'name' => 'Parent User',
            'email' => 'parent.user@example.com',
            'status' => 'active',
        ]);
        $parentUser->assignRole($roleParent);

        // ---------- BULK DATA COUNTERS ----------
        $centersToSeed = 5;
        $groupsPerCenter = 6;
        $lessonsPerGroup = 6;
        $assessmentsPerGroup = 3;

        $centers = collect();
        $groups = collect();
        $groupStudents = collect();
        $lessons = collect();
        $assessments = collect();
        $teachers = collect();
        $assistants = collect();
        $students = collect();
        $parents = collect();

        $resourceTypes = ['video', 'file', 'link'];

        // ---------- CENTERS + PEOPLE + GROUP STRUCTURE ----------
        foreach (range(1, $centersToSeed) as $index) {
            $centerAdminData = [
                'status' => 'active',
            ];
            if ($index === 1) {
                $centerAdminData['name'] = 'Demo Center Admin';
                $centerAdminData['email'] = 'center.admin@example.com';
            }

            $centerAdmin = User::factory()->create($centerAdminData);
            $centerAdmin->assignRole($roleCenter);

            $center = Center::factory()->create([
                'user_id' => $centerAdmin->id,
                'name' => $index === 1 ? 'Demo Center' : "Center {$index} Academy",
            ]);
            $centerAdmin->update(['center_id' => $center->id]);
            $centers->push($center);

            $centerTeachers = User::factory($faker->numberBetween(5, 7))->create([
                'status' => 'active',
                'center_id' => $center->id,
            ]);
            $centerTeachers->each(fn(User $user) => $user->assignRole($roleTeacher));

            if ($index === 1) {
                $demoTeacher = User::factory()->create([
                    'name' => 'Demo Teacher',
                    'email' => 'teacher.demo@example.com',
                    'status' => 'active',
                    'center_id' => $center->id,
                ]);
                $demoTeacher->assignRole($roleTeacher);
                $centerTeachers->push($demoTeacher);
            }

            $centerAssistants = User::factory($faker->numberBetween(2, 4))->create([
                'status' => 'active',
                'center_id' => $center->id,
            ]);
            $centerAssistants->each(fn(User $user) => $user->assignRole($roleAssistant));

            if ($index === 1) {
                $demoAssistant = User::factory()->create([
                    'name' => 'Demo Assistant',
                    'email' => 'assistant.demo@example.com',
                    'status' => 'active',
                    'center_id' => $center->id,
                ]);
                $demoAssistant->assignRole($roleAssistant);
                $centerAssistants->push($demoAssistant);
            }

            $centerParents = User::factory($faker->numberBetween(10, 14))->create([
                'status' => 'active',
                'center_id' => $center->id,
            ]);
            $centerParents->each(fn(User $user) => $user->assignRole($roleParent));

            if ($index === 1) {
                foreach (range(1, 3) as $parentIndex) {
                    $demoParent = User::factory()->create([
                        'name' => "Demo Parent {$parentIndex}",
                        'email' => "parent{$parentIndex}.demo@example.com",
                        'status' => 'active',
                        'center_id' => $center->id,
                    ]);
                    $demoParent->assignRole($roleParent);
                    $centerParents->push($demoParent);
                }
            }

            $centerStudents = User::factory($faker->numberBetween(35, 50))->create([
                'status' => 'active',
                'center_id' => $center->id,
            ]);
            $centerStudents->each(fn(User $user) => $user->assignRole($roleStudent));

            if ($index === 1) {
                foreach (range(1, 8) as $studentIndex) {
                    $demoStudent = User::factory()->create([
                        'name' => "Demo Student {$studentIndex}",
                        'email' => "student{$studentIndex}.demo@example.com",
                        'status' => 'active',
                        'center_id' => $center->id,
                    ]);
                    $demoStudent->assignRole($roleStudent);
                    $centerStudents->push($demoStudent);
                }
            }

            foreach ($centerParents as $parent) {
                $children = $centerStudents->shuffle()->take($faker->numberBetween(2, 4));
                foreach ($children as $child) {
                    ParentStudentLink::factory()->create([
                        'parent_id' => $parent->id,
                        'student_id' => $child->id,
                        'relationship' => $faker->randomElement(['father', 'mother', 'guardian']),
                    ]);
                }
            }

            $groupsForCenter = collect();
            foreach (range(1, $groupsPerCenter) as $groupIndex) {
                $groupsForCenter->push(
                    Group::factory()->create([
                        'center_id' => $center->id,
                        'teacher_id' => $centerTeachers->random()->id,
                        'is_active' => true,
                    ])
                );
            }

            foreach ($groupsForCenter as $group) {
                $groupMembers = $centerStudents
                    ->shuffle()
                    ->take($faker->numberBetween(12, min(20, $centerStudents->count())));

                foreach ($groupMembers as $student) {
                    $groupStudents->push(
                        GroupStudent::factory()->create([
                            'group_id' => $group->id,
                            'student_id' => $student->id,
                            'status' => 'approved',
                            'is_pay' => $faker->boolean(80),
                            'joined_at' => $faker->dateTimeBetween('-3 months', 'now'),
                        ])
                    );
                }

                $lessonsForGroup = Lesson::factory($lessonsPerGroup)->create([
                    'group_id' => $group->id,
                ]);
                $lessons = $lessons->merge($lessonsForGroup);

                foreach ($lessonsForGroup as $lesson) {
                    foreach ($resourceTypes as $type) {
                        LessonResource::factory()->create([
                            'lesson_id' => $lesson->id,
                            'type' => $type,
                        ]);
                    }
                }

                $assessmentsForGroup = Assessment::factory($assessmentsPerGroup)->create([
                    'center_id' => $center->id,
                    'group_id' => $group->id,
                ]);
                $assessments = $assessments->merge($assessmentsForGroup);

                foreach ($assessmentsForGroup as $assessment) {
                    foreach ($groupMembers as $student) {
                        AssessmentResult::factory()->create([
                            'assessment_id' => $assessment->id,
                            'student_id' => $student->id,
                            'score' => $faker->randomFloat(2, 40, 100),
                            'feedback' => $faker->sentence(),
                        ]);
                    }
                }
            }

            $teachers = $teachers->merge($centerTeachers);
            $assistants = $assistants->merge($centerAssistants);
            $students = $students->merge($centerStudents);
            $parents = $parents->merge($centerParents);
            $groups = $groups->merge($groupsForCenter);
        }

        if ($centers->isNotEmpty()) {
            $primaryCenterId = $centers->first()->id;
            $studentUser->update(['center_id' => $primaryCenterId]);
            $parentUser->update(['center_id' => $primaryCenterId]);

            ParentStudentLink::factory()->create([
                'parent_id' => $parentUser->id,
                'student_id' => $students->first()?->id ?? $studentUser->id,
                'relationship' => 'guardian',
            ]);
        }

        // ---------- ATTENDANCE ----------
        $staffPool = $teachers->concat($assistants);
        $groupLookup = $groups->keyBy('id');

        foreach ($groupStudents as $membership) {
            $group = $groupLookup->get($membership->group_id);
            if (!$group) {
                continue;
            }

            foreach (range(1, $faker->numberBetween(2, 5)) as $i) {
                Attendance::factory()->create([
                    'center_id' => $group->center_id,
                    'group_id' => $group->id,
                    'student_id' => $membership->student_id,
                    'date' => now()->subDays($faker->numberBetween(0, 14))->toDateString(),
                    'status' => $faker->randomElement(['present', 'absent', 'late', 'excused']),
                    'marked_by' => $staffPool->random()->id,
                ]);
            }
        }

        // ---------- AI STUDENT ANALYSIS ----------
        foreach ($students->take(min(20, $students->count())) as $student) {
            $groupRecord = $groupStudents->firstWhere('student_id', $student->id);
            if (!$groupRecord) {
                continue;
            }

            $group = $groupLookup->get($groupRecord->group_id);
            if (!$group) {
                continue;
            }

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
