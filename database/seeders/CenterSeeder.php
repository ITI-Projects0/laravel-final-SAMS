<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CenterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $role = Role::where('name', 'center_admin')->first();
        if (!$role) {
            return;
        }

        foreach (SeedBlueprints::centerSpecs() as $spec) {
            $centerAdmin = SeedUserHelper::createUser([
                'name' => $spec['admin_name'],
                'email' => $spec['admin_email'],
            ], $role);

            $center = Center::updateOrCreate(
                ['subdomain' => $spec['subdomain']],
                [
                    'user_id' => $centerAdmin->id,
                    'name' => $spec['name'],
                    'logo_url' => $spec['logo_url'],
                    'primary_color' => $spec['primary_color'] ?? '#0d47a1',
                    'secondary_color' => $spec['secondary_color'] ?? '#fdd835',
                    'subdomain' => $spec['subdomain'],
                    'is_active' => true,
                ]
            );

            $centerAdmin->update(['center_id' => $center->id]);
        }
    }
}
