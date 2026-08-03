<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePasswordRequest;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    /**
     * Deliberately NOT a method on UserController (ADR-40(4)): every action there
     * authorizes against a route-bound target through UserPolicy, while this one has no
     * target but the token's own user, and a method whose authorization works differently
     * sitting among them is how the next reader mis-copies the pattern.
     */
    public function updatePassword(UpdateProfilePasswordRequest $request)
    {
        $user = $request->user();

        // The model's `hashed` cast hashes on assignment, so do NOT call Hash::make here.
        $user->password = $request->validated('password');
        $user->save();

        // ADR-40(11): every OTHER token is revoked while the current one survives — it was
        // proven by `current_password` a moment ago, and logging the user out of the screen
        // they just used buys nothing. UserController::deactivate deletes ALL of a target's
        // tokens because there the target is somebody else whose access must fall in full.
        $current = $user->currentAccessToken();
        $tokens = $user->tokens();

        // `$current->exists` is INSURANCE AGAINST A SILENT BREAK, not a guard that changes
        // today's behaviour, and NO test covers it — removing it reds nothing (measured).
        // In a real request currentAccessToken() is always a stored row, so `exists` is true
        // and both versions are identical. Under Sanctum::actingAs there is no row and both
        // versions delete everything: with the clause because nothing is excluded, without it
        // because the Mockery double answers `false` to getKey(), so whereKeyNot(false) is
        // `id != 0` and matches every row. That equivalence rests entirely on `false`: a
        // Sanctum version whose double answered `null` would make this whereKeyNot(null),
        // i.e. `id != NULL`, which matches NO row — revocation would stop happening and stay
        // green. Sanctum v4.3.3 is not ours to pin, so do not "simplify" this away.
        if ($current instanceof PersonalAccessToken && $current->exists) {
            $tokens->whereKeyNot($current->getKey());
        }

        $tokens->delete();

        return response()->noContent();
    }
}
