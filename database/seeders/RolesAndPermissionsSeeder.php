<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $permissions = [
        'manage centers',
        'manage teachers',
        'manage students',
        'manage groups',
        'manage attendance',
        'manage grades',
        'view student performance',
        'use ai analysis',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $roleCenter = Role::firstOrCreate(['name' => 'center_admin', 'guard_name' => 'api']);
        $roleTeacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'api']);
        $roleAssistant = Role::firstOrCreate(['name' => 'assistant', 'guard_name' => 'api']);
        $roleStudent = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
        $roleParent = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'api']);

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
    }
}
