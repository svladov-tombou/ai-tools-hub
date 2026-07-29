<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'owner', 'display_name' => 'Owner', 'level' => 100],
            ['name' => 'pm', 'display_name' => 'PM', 'level' => 60],
            ['name' => 'qa', 'display_name' => 'QA', 'level' => 50],
            ['name' => 'backend', 'display_name' => 'Backend', 'level' => 40],
            ['name' => 'frontend', 'display_name' => 'Frontend', 'level' => 40],
            ['name' => 'designer', 'display_name' => 'Designer', 'level' => 30],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
