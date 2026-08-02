<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Creates a user and attaches the given role, WITHOUT authenticating as them.
 * Levels mirror RoleSeeder; tests do not seed. `firstOrCreate` (not `create`) matters:
 * a single test often needs two users with the same role (two owners, say), and
 * `Role::create` would collide on the unique name.
 */
function createUserWithRole(string $roleName, array $attributes = []): User
{
    $levels = ['owner' => 100, 'pm' => 60, 'manager' => 40, 'employee' => 20];

    $user = User::factory()->create($attributes);

    $user->roles()->attach(Role::firstOrCreate(
        ['name' => $roleName],
        ['display_name' => ucfirst($roleName), 'level' => $levels[$roleName]],
    ));

    return $user->load('roles');
}
