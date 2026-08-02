<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// UNAUTHENTICATED — doubles as a regression trap against a future edit moving a route
// out of the auth:sanctum group.

test('unauthenticated requests get 401 on every user-admin endpoint', function () {
    $target = User::factory()->create();

    $this->getJson('/api/users')->assertStatus(401);
    $this->postJson('/api/users', [])->assertStatus(401);
    $this->putJson("/api/users/{$target->id}", [])->assertStatus(401);
    $this->putJson("/api/users/{$target->id}/roles", [])->assertStatus(401);
    $this->putJson("/api/users/{$target->id}/password", [])->assertStatus(401);
    $this->postJson("/api/users/{$target->id}/activate")->assertStatus(401);
    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(401);
});

// MANAGER AND EMPLOYEE ARE FORBIDDEN — every write is tested from a non-admin role, and
// each test asserts that nothing was written, not merely the status code. A status code
// with a side effect behind it is not a guard, and neither is a hidden menu.

test('manager and employee cannot list users', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));

    $this->getJson('/api/users')->assertStatus(403);
})->with(['manager', 'employee']);

test('manager and employee cannot create a user', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $employeeRoleId = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee', 'level' => 20])->id;
    $before = User::count();

    $this->postJson('/api/users', [
        'name' => 'New Hire',
        'email' => 'new-hire@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$employeeRoleId],
    ])->assertStatus(403);

    expect(User::count())->toBe($before);
})->with(['manager', 'employee']);

test('manager and employee cannot update another user', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $target = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.com']);

    $this->putJson("/api/users/{$target->id}", [
        'name' => 'Changed Name',
        'email' => 'changed@example.com',
    ])->assertStatus(403);

    expect($target->fresh()->name)->toBe('Original Name');
    expect($target->fresh()->email)->toBe('original@example.com');
})->with(['manager', 'employee']);

test("manager and employee cannot change another user's roles", function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $target = createUserWithRole('employee');
    $pmRoleId = Role::firstOrCreate(['name' => 'pm'], ['display_name' => 'Pm', 'level' => 60])->id;

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [$pmRoleId]])->assertStatus(403);

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['employee']);
})->with(['manager', 'employee']);

test("manager and employee cannot reset another user's password", function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $target = User::factory()->create();
    $originalHash = $target->password;

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(403);

    expect($target->fresh()->password)->toBe($originalHash);
})->with(['manager', 'employee']);

test('manager and employee cannot activate a deactivated user', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $target = User::factory()->inactive()->create();

    $this->postJson("/api/users/{$target->id}/activate")->assertStatus(403);

    expect($target->fresh()->is_active)->toBeFalse();
})->with(['manager', 'employee']);

test('manager and employee cannot deactivate an active user', function (string $role) {
    Sanctum::actingAs(createUserWithRole($role));
    $target = User::factory()->create();
    $target->createToken('t');

    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(403);

    expect($target->fresh()->is_active)->toBeTrue();
    // The token check is what proves the guard stopped the whole action, not just the flag.
    expect($target->tokens()->count())->toBe(1);
})->with(['manager', 'employee']);

test('a non-admin sending an invalid payload to create a user gets 403, not 422', function () {
    // This test is the entire reason authorization lives in the Form Request's
    // authorize() rather than in the controller body: under the controller-body
    // pattern validation fires first and hands the validation rules to someone who may
    // not write at all.
    Sanctum::actingAs(createUserWithRole('manager'));

    $this->postJson('/api/users', ['email' => 'not-an-email'])->assertStatus(403);
});

// SELF RULES

test('an admin can update their own name and email', function (string $role) {
    // The discriminating counterpart: without it, a blanket "no self-edit"
    // implementation would pass every self-restriction test below for the wrong reason.
    $actor = createUserWithRole($role);
    Sanctum::actingAs($actor);

    $this->putJson("/api/users/{$actor->id}", [
        'name' => 'New Own Name',
        'email' => 'own-new-email@example.com',
    ])->assertStatus(200);

    expect($actor->fresh()->name)->toBe('New Own Name');
    expect($actor->fresh()->email)->toBe('own-new-email@example.com');
})->with(['owner', 'pm']);

test('an admin cannot change their own roles', function (string $role) {
    $actor = createUserWithRole($role);
    Sanctum::actingAs($actor);

    $this->putJson("/api/users/{$actor->id}/roles", ['role_ids' => $actor->roles->pluck('id')->all()])
        ->assertStatus(403);

    expect($actor->fresh()->roles->pluck('name')->all())->toBe([$role]);
})->with(['owner', 'pm']);

test('an admin cannot deactivate themselves', function (string $role) {
    $actor = createUserWithRole($role);
    Sanctum::actingAs($actor);

    $this->postJson("/api/users/{$actor->id}/deactivate")->assertStatus(403);

    expect($actor->fresh()->is_active)->toBeTrue();
})->with(['owner', 'pm']);

test('an admin can reset their own password', function (string $role) {
    // Allowed because there is no self-service password reset in this app: without this
    // path, an admin who forgets their password is permanently locked out.
    $actor = createUserWithRole($role);
    Sanctum::actingAs($actor);

    $this->putJson("/api/users/{$actor->id}/password", [
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertStatus(204);

    $this->postJson('/api/login', [
        'email' => $actor->email,
        'password' => 'brand-new-password',
    ])->assertStatus(200);

    $this->postJson('/api/login', [
        'email' => $actor->email,
        'password' => 'password',
    ])->assertStatus(422);
})->with(['owner', 'pm']);

// THE OWNER ROLE MAY ONLY BE GRANTED OR REVOKED BY AN OWNER

test('a pm cannot grant the owner role to a manager', function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('manager');
    $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner', 'level' => 100]);

    // The target must NOT already hold owner, otherwise this would pass for the wrong
    // reason: the separate pm/owner-target rule below would block it regardless of who
    // is granting the role. This is the difference between a test that discriminates and
    // one that does not.
    expect($target->hasRole('owner'))->toBeFalse();

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [$ownerRole->id]])->assertStatus(403);

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['manager']);
});

test('an owner can grant the owner role to a manager', function () {
    // The counterpart proving the rule is about WHO acts, not about the role being untouchable.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('manager');
    $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner', 'level' => 100]);

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [$ownerRole->id]])->assertStatus(200);

    expect($target->fresh()->hasRole('owner'))->toBeTrue();
});

test('a pm cannot create a user with the owner role', function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $ownerRoleId = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner', 'level' => 100])->id;
    $before = User::count();

    $this->postJson('/api/users', [
        'name' => 'Wannabe Owner',
        'email' => 'wannabe-owner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$ownerRoleId],
    ])->assertStatus(403);

    expect(User::count())->toBe($before);
});

test('an owner can create a user with the owner role', function () {
    Sanctum::actingAs(createUserWithRole('owner'));
    $ownerRoleId = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner', 'level' => 100])->id;

    $this->postJson('/api/users', [
        'name' => 'New Owner',
        'email' => 'new-owner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$ownerRoleId],
    ])->assertStatus(201);
});

// A PM MAY NOT ACT ON AN OWNER AT ALL

test('a pm cannot update an owner', function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('owner', ['name' => 'Original Owner', 'email' => 'owner-original@example.com']);

    $this->putJson("/api/users/{$target->id}", [
        'name' => 'Changed Owner',
        'email' => 'owner-changed@example.com',
    ])->assertStatus(403);

    expect($target->fresh()->name)->toBe('Original Owner');
    expect($target->fresh()->email)->toBe('owner-original@example.com');
});

test("a pm cannot change an owner's roles", function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('owner');
    $managerRoleId = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager', 'level' => 40])->id;

    $this->putJson("/api/users/{$target->id}/roles", ['role_ids' => [$managerRoleId]])->assertStatus(403);

    expect($target->fresh()->roles->pluck('name')->all())->toBe(['owner']);
});

test("a pm cannot reset an owner's password", function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('owner');
    $originalHash = $target->password;

    $this->putJson("/api/users/{$target->id}/password", [
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(403);

    expect($target->fresh()->password)->toBe($originalHash);
});

test('a pm cannot deactivate an owner', function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('owner');

    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(403);

    expect($target->fresh()->is_active)->toBeTrue();
});

test('a pm cannot activate a deactivated owner', function () {
    Sanctum::actingAs(createUserWithRole('pm'));
    $target = createUserWithRole('owner', ['is_active' => false]);

    $this->postJson("/api/users/{$target->id}/activate")->assertStatus(403);

    expect($target->fresh()->is_active)->toBeFalse();
});

test('an owner can deactivate another owner', function () {
    // Without this counterpart, an implementation that simply made every owner
    // untouchable (rather than only untouchable BY A PM) would pass all five tests above.
    Sanctum::actingAs(createUserWithRole('owner'));
    $target = createUserWithRole('owner');

    $this->postJson("/api/users/{$target->id}/deactivate")->assertStatus(200);

    expect($target->fresh()->is_active)->toBeFalse();
});

// THE SAFETY INVARIANT — stated explicitly rather than left implied across other tests.

test('a pm cannot deactivate the owner, and the owner cannot deactivate themselves', function () {
    // These two rules together are what guarantee at least one active owner always
    // remains, which is why neither may be relaxed alone.
    $owner = createUserWithRole('owner');
    Sanctum::actingAs(createUserWithRole('pm'));

    $this->postJson("/api/users/{$owner->id}/deactivate")->assertStatus(403);

    Sanctum::actingAs($owner);
    $this->postJson("/api/users/{$owner->id}/deactivate")->assertStatus(403);

    expect($owner->fresh()->is_active)->toBeTrue();
});
