<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StudentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $role = Role::where('name', 'student')->first();
        if (!$role) {
            return;
        }

        foreach (SeedBlueprints::centerSpecs() as $spec) {
            $center = Center::where('subdomain', $spec['subdomain'])->first();
            if (!$center) {
                continue;
            }

            foreach (range(1, $spec['student_count'] ?? 0) as $_) {
                SeedUserHelper::createUser(['center_id' => $center->id], $role);
            }

            foreach ($spec['extra_students'] ?? [] as $studentData) {
                SeedUserHelper::createUser(array_merge($studentData, ['center_id' => $center->id]), $role);
            }
        }
    }
}
