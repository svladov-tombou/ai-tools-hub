<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// LISTING

test('owner and pm can list users as a plain array', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    createUserWithRole('employee');

    $response = $this->getJson('/api/users')->assertStatus(200);

    // A plain array, NOT a paginated envelope: no `data` key at the top level, and the
    // count matches every row in the table.
    expect($response->json())->not->toHaveKey('data');
    expect($response->json())->toHaveCount(User::count());
})->with(['owner', 'pm']);

test('listing includes deactivated users, and reports their is_active correctly', function () {
    // Reactivating a user has to be possible from somewhere, so hiding deactivated users
    // from the list would make the screen useless for exactly the case it exists to fix.
    Sanctum::actingAs(createUserWithRole('owner'));
    $active = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    $response = $this->getJson('/api/users')->assertStatus(200);

    $entries = collect($response->json())->keyBy('id');
    expect($entries->has($active->id))->toBeTrue();
    expect($entries->has($inactive->id))->toBeTrue();
    expect($entries[$inactive->id]['is_active'])->toBeFalse();
});

test('a listed user has exactly the documented keys, and no more', function () {
    // Asserting the EXACT set, in this order, is the point: if someone later returns the
    // whole model, this fails rather than silently widening the API with timestamps, the
    // password hash, or the belongsToMany pivot object.
    Sanctum::actingAs(createUserWithRole('owner'));

    $response = $this->getJson('/api/users')->assertStatus(200);
    $first = $response->json()[0];

    expect(array_keys($first))->toBe(['id', 'name', 'email', 'is_active', 'department_id', 'roles']);
    expect(array_keys($first['roles'][0]))->toBe(['id', 'name', 'display_name', 'level']);
});

test('is_active is serialized as a real boolean, not as 1/0', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $active = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    $response = $this->getJson('/api/users')->assertStatus(200);
    $entries = collect($response->json())->keyBy('id');

    // toBeTrue/toBeFalse fail on the integers 1/0, which is exactly the point: this is
    // what proves the model cast is present, since the database returns a tinyint.
    expect($entries[$active->id]['is_active'])->toBeTrue();
    expect($entries[$inactive->id]['is_active'])->toBeFalse();
});

// CREATING

test('owner and pm can create a user', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $employeeRoleId = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee', 'level' => 20])->id;

    $response = $this->postJson('/api/users', [
        'name' => 'Fresh Hire',
        'email' => 'fresh-hire@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$employeeRoleId],
    ])->assertStatus(201);

    $created = User::where('email', 'fresh-hire@example.com')->firstOrFail();
    expect($created->roles->pluck('id')->all())->toBe([$employeeRoleId]);

    // is_active is NOT part of the payload — it comes from the column/model default.
    expect($response->json('is_active'))->toBeTrue();
    expect($created->is_active)->toBeTrue();

    // The one assertion that proves the password was hashed correctly rather than
    // stored raw or double-hashed.
    $this->postJson('/api/login', [
        'email' => 'fresh-hire@example.com',
        'password' => 'password123',
    ])->assertStatus(200);
})->with(['owner', 'pm']);

test('department_id is optional when creating a user', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $employeeRoleId = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee', 'level' => 20])->id;

    $response = $this->postJson('/api/users', [
        'name' => 'No Department',
        'email' => 'no-department@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$employeeRoleId],
    ])->assertStatus(201);

    expect($response->json('department_id'))->toBeNull();
    expect(User::where('email', 'no-department@example.com')->value('department_id'))->toBeNull();
});

test('department_id is stored when a real department is given', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $department = Department::create(['name' => 'IT', 'slug' => 'it']);
    $employeeRoleId = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee', 'level' => 20])->id;

    $response = $this->postJson('/api/users', [
        'name' => 'IT Hire',
        'email' => 'it-hire@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$employeeRoleId],
        'department_id' => $department->id,
    ])->assertStatus(201);

    expect($response->json('department_id'))->toBe($department->id);
    expect(User::where('email', 'it-hire@example.com')->value('department_id'))->toBe($department->id);
});

// UPDATING

test('name, email and department_id change and persist', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $department = Department::create(['name' => 'IT', 'slug' => 'it']);
    $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $this->putJson("/api/users/{$target->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'department_id' => $department->id,
    ])->assertStatus(200);

    $fresh = $target->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->email)->toBe('new@example.com');
    expect($fresh->department_id)->toBe($department->id);
});

test('sending role_ids to the update endpoint is rejected, and the roles stay unchanged', function () {
    // `prohibited` makes it a loud failure: a silently ignored field is how a caller
    // comes to believe it changed something it did not.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('employee');
    $pmRoleId = Role::firstOrCreate(['name' => 'pm'], ['display_name' => 'Pm', 'level' => 60])->id;

    $this->putJson("/api/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'role_ids' => [$pmRoleId],
    ])->assertStatus(422)->assertJsonValidationErrors('role_ids');

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['employee']);
});

test('sending password to the update endpoint is rejected, and the password stays unchanged', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();
    $originalHash = $target->password;

    $this->putJson("/api/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => 'sneaky-password',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    expect($target->fresh()->password)->toBe($originalHash);
});

// ROLES

test('updating roles replaces the set instead of adding to it', function () {
    // Removing is tested rather than adding, on purpose: an implementation using
    // syncWithoutDetaching would pass an add-only test unchanged.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('employee');
    $pmRole = Role::firstOrCreate(['name' => 'pm'], ['display_name' => 'Pm', 'level' => 60]);
    $target->roles()->attach($pmRole);

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [$pmRole->id]])->assertStatus(200);

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['pm']);
});

test('role_ids is required and cannot be an empty array', function () {
    // A user with no role is an undefined state for every policy in the app.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('employee');

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role_ids');

    $this->putJson("/api/users/{$target->id}/roles", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role_ids');
});

test('a non-existent role id is rejected', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('employee');

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role_ids.0');
});

// PASSWORD RESET

test("an owner resets another user's password", function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => 'reset-password-1',
        'password_confirmation' => 'reset-password-1',
    ])->assertStatus(204);

    $this->postJson('/api/login', [
        'email' => $target->email,
        'password' => 'reset-password-1',
    ])->assertStatus(200);

    $this->postJson('/api/login', [
        'email' => $target->email,
        'password' => 'password',
    ])->assertStatus(422);
});

test('a password of exactly 8 characters is accepted and 7 is rejected', function () {
    // A boundary pair, not a round number: a 20-character test would also pass against
    // min:19 and would prove nothing about the number 8.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertStatus(204);

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => '1234567',
        'password_confirmation' => '1234567',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

test('a mismatched password confirmation is rejected', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

// DEACTIVATE / ACTIVATE — the core of the phase

test('deactivating sets is_active to false in the database and in the response', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();

    $response = $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(200);

    expect($response->json('is_active'))->toBeFalse();
    expect($target->fresh()->is_active)->toBeFalse();
});

test("deactivating deletes the target's Sanctum tokens, and only theirs, and the old token stops working", function () {
    // Real tokens, not Sanctum::actingAs: actingAs sets the guard's user directly and
    // would mask what the Bearer header actually does. This is the entire mechanism by
    // which a deactivated user loses access — there is no middleware checking is_active
    // per request, so if this deletion regressed a deactivated user would keep working
    // until their token expired, and nothing else in the suite would notice.
    $owner = createUserWithRole('owner');
    $ownerToken = $owner->createToken('t')->plainTextToken;
    $target = User::factory()->create();
    $targetToken = $target->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/users/{$target->id}/deactivate")
        ->assertStatus(200);

    expect($target->tokens()->count())->toBe(0);
    expect($owner->tokens()->count())->toBe(1);

    // Without this, the assertion below passes with 200 and the app is innocent: Laravel's
    // test client keeps the guard's resolved user between requests inside one test, so the
    // second call would silently answer as the OWNER from the first call rather than
    // resolving the revoked Bearer token at all. Verified out-of-band with curl against the
    // live API — the same token really does go 200 -> deactivate -> 401.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$targetToken}")
        ->getJson('/api/user')
        ->assertStatus(401);
});

test('a deactivated user cannot log in', function () {
    // Re-asserted here, at the point where deactivation actually happens through the
    // admin endpoint, rather than only via a hand-edited flag elsewhere in the suite.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();

    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(200);

    $this->postJson('/api/login', [
        'email' => $target->email,
        'password' => 'password',
    ])->assertStatus(422);
});

test('activating restores access', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = User::factory()->create();
    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(200);

    $this->postJson("/api/users/{$target->id}/activate")->assertStatus(200);

    $this->postJson('/api/login', [
        'email' => $target->email,
        'password' => 'password',
    ])->assertStatus(200);
});

test('activating an already-active user and deactivating an already-inactive one are idempotent', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $activeTarget = User::factory()->create();
    $inactiveTarget = User::factory()->inactive()->create();

    $this->postJson("/api/users/{$activeTarget->id}/activate")->assertStatus(200);
    expect($activeTarget->fresh()->is_active)->toBeTrue();

    $this->postJson("/api/users/{$inactiveTarget->id}/deactivate")->assertStatus(200);
    expect($inactiveTarget->fresh()->is_active)->toBeFalse();
});
