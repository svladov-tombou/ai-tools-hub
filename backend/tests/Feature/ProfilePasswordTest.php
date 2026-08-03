<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('the same user gets 204 on the self-service endpoint and 403 on the admin one', function () {
    $user = createUserWithRole('employee');
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(204);

    // Same user, same intent, the other endpoint: UserPolicy::updatePassword is owner/pm
    // only, which is the entire reason ADR-40(1) added a second route instead of relaxing
    // the first. If this pair ever answers the same way, the two rules have collapsed.
    $this->putJson("/api/users/{$user->id}/password", [
        'password' => 'new-password-2',
        'password_confirmation' => 'new-password-2',
    ])->assertStatus(403);
});

test('a wrong current_password is a 422 on that field, and the stored hash is unchanged', function () {
    $user = createUserWithRole('employee');
    $originalHash = $user->password;
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'not-the-password',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    // Asserted rather than inferred from the status code: a 422 says the request was
    // refused, not that nothing was written.
    expect($user->fresh()->password)->toBe($originalHash);
});

// This is the assertion that goes red if the `required` on `current_password` is ever
// dropped, since every other field in the payload is perfectly valid.
test('a missing current_password is a 422, even though the new password is otherwise valid', function () {
    $user = createUserWithRole('employee');
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');
});

// The next two do NOT exist to check that Laravel's own rules work: ADR-40(3) DUPLICATES
// `confirmed` and Password::min(8) here instead of extracting them, so these are what keeps
// the self-service path and the admin reset from drifting apart in password strength.
test('a mismatched confirmation is a 422 on password, and the stored hash is unchanged', function () {
    $user = createUserWithRole('employee');
    $originalHash = $user->password;
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-2',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    // Asserted separately from the status code: a 422 says the request was refused, not
    // that nothing was written.
    expect($user->fresh()->password)->toBe($originalHash);
});

test('a password shorter than 8 characters is a 422 on password, and the stored hash is unchanged', function () {
    $user = createUserWithRole('employee');
    $originalHash = $user->password;
    Sanctum::actingAs($user);

    // Seven characters — the boundary immediately below Password::min(8), so lowering the
    // minimum by one is enough to make this go green.
    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'short7c',
        'password_confirmation' => 'short7c',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    expect($user->fresh()->password)->toBe($originalHash);
});

// Driven through /api/login so it asserts the stored hash really changed, not that the
// endpoint answered 204.
test('after a success, the old password no longer authenticates and the new one does', function () {
    $user = createUserWithRole('employee');
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertStatus(204);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'brand-new-password',
    ])->assertStatus(200);
});

test('an unauthenticated request is rejected', function () {
    User::factory()->create();

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(401);
});

test('a successful change revokes every other token and keeps the one that made the request', function () {
    // Real tokens over a Bearer header, never Sanctum::actingAs (pitfalls #3b): actingAs
    // writes no row to personal_access_tokens, so there would be nothing to revoke and
    // nothing to keep, and this test would pass while asserting nothing at all.
    $user = createUserWithRole('employee');
    $current = $user->createToken('current');
    $other = $user->createToken('other');

    expect($user->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', "Bearer {$current->plainTextToken}")
        ->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertStatus(204);

    // The surviving row is THE CURRENT token, not merely "one row left": a rule that kept
    // an arbitrary token would satisfy a bare count assertion.
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->getKey())->toBe($current->accessToken->getKey());

    // Without this the request below answers as the user resolved by the FIRST call and
    // returns 200 while the app is innocent — the test client keeps the guard's resolved
    // user between requests inside one test (pitfalls #3b).
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$other->plainTextToken}")
        ->getJson('/api/user')
        ->assertStatus(401);
});

test('every role can change their own password', function (string $role) {
    $user = createUserWithRole($role);
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'role-password-1',
        'password_confirmation' => 'role-password-1',
    ])->assertStatus(204);

    // Authorization on this endpoint does not depend on the role at all, so a 403 for any
    // of the four would mean the admin policy leaked into the self-service path.
    expect(Hash::check('role-password-1', $user->fresh()->password))->toBeTrue();
})->with(['owner', 'pm', 'manager', 'employee']);
