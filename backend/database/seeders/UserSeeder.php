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
            ['name' => 'Иван Иванов', 'email' => 'ivan@admin.local', 'role' => 'owner', 'is_active' => true],
            ['name' => 'Мария Стоянова', 'email' => 'maria@pm.local', 'role' => 'pm', 'is_active' => true],
            ['name' => 'Елена Петрова', 'email' => 'elena@manager.local', 'role' => 'manager', 'is_active' => true],
            ['name' => 'Петър Георгиев', 'email' => 'petar@employee.local', 'role' => 'employee', 'is_active' => true],
            ['name' => 'Георги Димитров', 'email' => 'georgi@inactive.local', 'role' => 'employee', 'is_active' => false],
        ];

        foreach ($users as $data) {
            // firstOrCreate, NOT updateOrCreate: administrators can now rename users and
            // change their roles through the UI, and updateOrCreate would overwrite an
            // admin's edit — and reset their password to the seed value — on the next
            // db:seed. The same decision already taken for CategorySeeder. Roles (and the
            // deactivated flag) are attached only when the row was just created, for the
            // same reason; a clean slate is `migrate:fresh --seed`.
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')]
            );

            if ($user->wasRecentlyCreated) {
                $role = Role::where('name', $data['role'])->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id]);

                // is_active is not mass-assignable, so it is set by direct property
                // assignment rather than through the create array above.
                if (! $data['is_active']) {
                    $user->is_active = false;
                    $user->save();
                }
            }
        }
    }
}
