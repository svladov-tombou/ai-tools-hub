<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// BASELINE — without this, a test suite where login is broken for everyone would still
// look like it proves something about deactivation.
test('an active user can log in', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(200);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('a deactivated user cannot log in, even with the correct password', function () {
    $user = User::factory()->inactive()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    // Asserting only the status code would pass even if a token had been issued as a side
    // effect; the guarantee is that a deactivated login writes nothing.
    expect($user->tokens()->count())->toBe(0);
});

test('a deactivated user with the wrong password gets the credentials error, not the deactivation error', function () {
    $user = User::factory()->inactive()->create();

    // This test is the reason the check order exists: it goes red the moment someone moves
    // the is_active check above the password check, which would leak account state to an
    // unauthenticated caller.
    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422);

    expect($response->json('errors.email.0'))->toBe('The provided credentials are incorrect.');
});

// Two defaults exist and they are NOT the same guarantee, so they are asserted separately:
// the model's $attributes default (what a just-created instance reports, and therefore what
// a create endpoint returns in its JSON) and the column default (what the database stores).
// Eloquent never reads a column default back after an insert, so with only the column default
// the in-memory value is null — falsy, i.e. a brand-new user reads as deactivated.
test('a newly created user is active by default, in memory and in the database', function () {
    $user = User::factory()->create();

    expect($user->is_active)->toBeTrue();
    expect($user->fresh()->is_active)->toBeTrue();
});

// Bypasses the model entirely, which is the only way to prove the MIGRATION's default rather
// than the model's: the assertion above would still pass if the column had no default at all.
test('the column default makes a row inserted without is_active active', function () {
    DB::table('users')->insert([
        'name' => 'Raw insert',
        'email' => 'raw@example.local',
        'password' => 'irrelevant',
    ]);

    expect(DB::table('users')->where('email', 'raw@example.local')->value('is_active'))->toBe(1);
});

test('is_active is serialized as a boolean, not as 1/0', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(200);

    // toBeTrue fails on the integer 1, which is exactly the point: this test is what proves
    // the model cast is present, since MySQL returns a tinyint.
    expect($response->json('user.is_active'))->toBeTrue();
});
