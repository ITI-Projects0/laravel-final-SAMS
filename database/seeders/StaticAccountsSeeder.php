<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\ParentStudentLink;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StaticAccountsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roleAdmin = Role::where('name', 'admin')->first();
        $roleStudent = Role::where('name', 'student')->first();
        $roleParent = Role::where('name', 'parent')->first();

        if (!$roleAdmin || !$roleStudent || !$roleParent) {
            return;
        }

        SeedUserHelper::createUser([
            'name' => 'SAMS Super Admin',
            'email' => 'ahmed.alla56756@gmail.com',
        ], $roleAdmin, 'password');

        $center = Center::orderBy('id')->first();

        $studentUser = SeedUserHelper::createUser([
            'name' => 'Student User',
            'email' => 'student.user@example.com',
            'center_id' => $center?->id,
        ], $roleStudent, 'password');

        $parentUser = SeedUserHelper::createUser([
            'name' => 'Parent User',
            'email' => 'parent.user@example.com',
            'center_id' => $center?->id,
        ], $roleParent, 'password');

        if ($center && $parentUser && $studentUser) {
            ParentStudentLink::firstOrCreate([
                'parent_id' => $parentUser->id,
                'student_id' => $studentUser->id,
            ], [
                'relationship' => 'guardian',
            ]);
        }
    }
}
