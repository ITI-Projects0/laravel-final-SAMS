<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ParentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $role = Role::where('name', 'parent')->first();
        if (!$role) {
            return;
        }

        foreach (SeedBlueprints::centerSpecs() as $spec) {
            $center = Center::where('subdomain', $spec['subdomain'])->first();
            if (!$center) {
                continue;
            }

            foreach (range(1, $spec['parent_count'] ?? 0) as $_) {
                SeedUserHelper::createUser(['center_id' => $center->id], $role);
            }

            foreach ($spec['extra_parents'] ?? [] as $parentData) {
                SeedUserHelper::createUser(array_merge($parentData, ['center_id' => $center->id]), $role);
            }
        }
    }
}
