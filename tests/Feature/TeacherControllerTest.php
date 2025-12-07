<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_augmented_teacher_payload_with_filters_and_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create(['name' => 'Alpha Teacher']);
        User::factory()->teacher()->create(['name' => 'Beta Teacher']);

        $groupOne = Group::factory()->for($teacher, 'teacher')->create(['name' => 'Group One']);
        $groupTwo = Group::factory()->for($teacher, 'teacher')->create(['name' => 'Group Two']);

        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $studentC = User::factory()->student()->create();

        GroupStudent::factory()->create([
            'group_id' => $groupOne->id,
            'student_id' => $studentA->id,
            'status' => 'approved',
        ]);
        GroupStudent::factory()->create([
            'group_id' => $groupTwo->id,
            'student_id' => $studentB->id,
            'status' => 'approved',
        ]);
        GroupStudent::factory()->create([
            'group_id' => $groupTwo->id,
            'student_id' => $studentC->id,
            'status' => 'pending',
        ]);

        Attendance::factory()->create([
            'group_id' => $groupOne->id,
            'center_id' => $groupOne->center_id,
            'student_id' => $studentA->id,
            'date' => today(),
            'status' => 'present',
            'marked_by' => $teacher->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/teachers?search=Alpha&sort_by=approved_students_count&sort_dir=desc&per_page=10');

        $response->assertOk()->assertJson(function (AssertableJson $json) {
            $json->where('meta.pagination.per_page', 10)
                ->where('meta.filters.search', 'Alpha')
                ->where('meta.filters.sort_by', 'approved_students_count')
                ->where('data.data.0.name', 'Alpha Teacher')
                ->where('data.data.0.taught_groups_count', 2)
                ->where('data.data.0.approved_students_count', 2)
                ->where('data.data.0.pending_students_count', 1)
                ->where('data.data.0.total_students', 2)
                ->where('data.data.0.groups.0.attendance_today_count', 1)
                ->where('data.data.0.groups.0.students_count', 1)
                ->etc();
        });
    }

    public function test_show_returns_teacher_with_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $group = Group::factory()->for($teacher, 'teacher')->create();
        $student = User::factory()->student()->create();

        GroupStudent::factory()->create([
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/api/teachers/{$teacher->id}");

        $response->assertOk()->assertJson(function (AssertableJson $json) {
            $json->where('data.taught_groups_count', 1)
                ->where('data.approved_students_count', 1)
                ->where('data.pending_students_count', 0)
                ->where('data.total_students', 1)
                ->has('data.groups', 1)
                ->etc();
        });
    }
}
