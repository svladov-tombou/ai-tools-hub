<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Иван Иванов', 'email' => 'ivan@admin.local', 'role' => 'owner'],
            ['name' => 'Елена Петрова', 'email' => 'elena@manager.local', 'role' => 'manager'],
            ['name' => 'Петър Георгиев', 'email' => 'petar@employee.local', 'role' => 'employee'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')]
            );

            $role = Role::where('name', $data['role'])->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
