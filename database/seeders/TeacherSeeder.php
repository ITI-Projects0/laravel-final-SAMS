<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TeacherSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $role = Role::where('name', 'teacher')->first();
        if (!$role) {
            return;
        }

        foreach (SeedBlueprints::centerSpecs() as $spec) {
            $center = Center::where('subdomain', $spec['subdomain'])->first();
            if (!$center) {
                continue;
            }

            foreach (range(1, $spec['teacher_count'] ?? 0) as $_) {
                SeedUserHelper::createUser(['center_id' => $center->id], $role);
            }

            foreach ($spec['extra_teachers'] ?? [] as $teacherData) {
                SeedUserHelper::createUser(array_merge($teacherData, ['center_id' => $center->id]), $role);
            }
        }
    }
}
