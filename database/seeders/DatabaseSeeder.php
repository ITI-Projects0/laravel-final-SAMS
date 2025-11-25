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
use App\Models\Notification;
use App\Models\ParentStudentLink;
use App\Models\User;
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
        $faker = fake();

        // ---------- USERS ----------
        $admin = User::factory()->create([
            'name'  => 'SAMS Super Admin',
            'email' => 'ahmed.alla56756@gmail.com',
            'role'  => 'admin',
        ]);

        $centerAdmins = User::factory(3)->create(['role' => 'center_admin']);
        $teachers     = User::factory(6)->create(['role' => 'teacher']);
        $assistants   = User::factory(3)->create(['role' => 'assistant']);
        $parents      = User::factory(5)->create(['role' => 'parent']);
        $students     = User::factory(20)->create(['role' => 'student']);

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
            // 3 groups per center
            $groupsForCenter = Group::factory(3)->create([
                'center_id'  => $center->id,
                'teacher_id' => $teachers->random()->id,
            ]);

            $groups = $groups->merge($groupsForCenter);
        }

        // ---------- PARENT–STUDENT LINKS ----------
        $parentStudentLinks = collect();

        foreach ($parents as $parent) {
            // give each parent 3 random children
            $randomStudents = $students->random(3);

            foreach ($randomStudents as $student) {
                $parentStudentLinks->push(
                    ParentStudentLink::factory()->create([
                        'parent_id'    => $parent->id,
                        'student_id'   => $student->id,
                        'relationship' => $faker->randomElement(['father', 'mother', 'guardian']),
                    ])
                );
            }
        }

        // ---------- GROUP–STUDENT ENROLLMENT ----------
        $groupStudents = collect();

        foreach ($groups as $group) {
            // 6 students per group
            $randomStudents = $students->random(6);

            foreach ($randomStudents as $student) {
                $groupStudents->push(
                    GroupStudent::factory()->create([
                        'group_id'  => $group->id,
                        'student_id'=> $student->id,
                        'status'    => 'approved',
                        'is_pay'    => $faker->boolean(70),
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
                    'type'      => $type,
                ]);
            }
        }

        // ---------- ASSESSMENTS + RESULTS ----------
        $assessments = collect();
        foreach ($groups as $group) {
            $assessmentsForGroup = Assessment::factory(2)->create([
                'center_id' => $group->center_id,
                'group_id'  => $group->id,
            ]);

            $assessments = $assessments->merge($assessmentsForGroup);
        }

        // group enrollments by group_id
        $enrollmentsByGroup = $groupStudents->groupBy('group_id');

        foreach ($assessments as $assessment) {
            $studentsInGroup = $enrollmentsByGroup->get($assessment->group_id, collect());

            foreach ($studentsInGroup->pluck('student_id')->unique() as $studentId) {
                AssessmentResult::factory()->create([
                    'assessment_id' => $assessment->id,
                    'student_id'    => $studentId,
                    'score'         => $faker->randomFloat(2, 40, 100),
                    'remarks'       => $faker->sentence(),
                ]);
            }
        }

        // ---------- ATTENDANCE ----------
        $staffPool   = $teachers->concat($assistants);
        $groupLookup = $groups->keyBy('id');

        foreach ($groupStudents as $membership) {
            $group = $groupLookup->get($membership->group_id);

            if (! $group) {
                continue;
            }

            Attendance::factory()->create([
                'center_id'  => $group->center_id,
                'group_id'   => $group->id,
                'student_id' => $membership->student_id,
                'date'       => now()->subDays($faker->numberBetween(0, 10))->toDateString(),
                'status'     => $faker->randomElement(['present', 'absent', 'late', 'excused']),
                'marked_by'  => $staffPool->random()->id,
            ]);
        }

        // ---------- PARENT NOTIFICATIONS ----------
        foreach ($parents as $parent) {
            // try to get first linked student for this parent
            $studentLink = $parentStudentLinks->firstWhere('parent_id', $parent->id);

            // if no link, pick a random groupStudent record
            $groupRecord = $studentLink
                ? $groupStudents->firstWhere('student_id', $studentLink->student_id)
                : $groupStudents->random();

            $group = $groupRecord ? $groupLookup->get($groupRecord->group_id) : null;

            Notification::factory(2)->create([
                'center_id'          => $group?->center_id,
                'sender_id'          => $teachers->random()->id,
                'recipient_id'       => $parent->id,
                'related_student_id' => $studentLink?->student_id,
                'related_group_id'   => $group?->id,
                'type'               => $faker->randomElement(['attendance', 'general', 'low_performance']),
                'title'              => 'SAMS Update',
                'message'            => $faker->sentence(15),
                'is_read'            => false,
            ]);
        }

        // ---------- AI STUDENT ANALYSIS ----------
        foreach ($students->take(5) as $student) {
            $groupRecord = $groupStudents->firstWhere('student_id', $student->id);

            if (! $groupRecord) {
                continue;
            }

            $group = $groupLookup->get($groupRecord->group_id);

            AiStudentAnalysis::factory()->create([
                'center_id'    => $group->center_id,
                'student_id'   => $student->id,
                'requested_by' => $staffPool->random()->id,
                'summary'      => "Performance summary for {$student->name}",
                'details'      => $faker->paragraph(),
            ]);
        }
    }
}
