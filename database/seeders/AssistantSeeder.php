<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AssistantSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $role = Role::where('name', 'assistant')->first();
        if (!$role) {
            return;
        }

        foreach (SeedBlueprints::centerSpecs() as $spec) {
            $center = Center::where('subdomain', $spec['subdomain'])->first();
            if (!$center) {
                continue;
            }

            foreach (range(1, $spec['assistant_count'] ?? 0) as $_) {
                SeedUserHelper::createUser(['center_id' => $center->id], $role);
            }

            foreach ($spec['extra_assistants'] ?? [] as $assistantData) {
                SeedUserHelper::createUser(array_merge($assistantData, ['center_id' => $center->id]), $role);
            }
        }
    }
}
