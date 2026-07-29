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
            ['name' => 'pm', 'display_name' => 'Project Manager', 'level' => 60],
            ['name' => 'manager', 'display_name' => 'Manager', 'level' => 40],
            ['name' => 'employee', 'display_name' => 'Employee', 'level' => 20],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
