<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfilePasswordRequest extends FormRequest
{
    /**
     * No policy ability and no route parameter: the target of this endpoint is the token's
     * own user by construction, so the `auth:sanctum` group has already established the
     * only fact this request needs (ADR-40(2)).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The guard is named EXPLICITLY. `current_password` without a parameter resolves
            // the DEFAULT guard, and `config('auth.defaults.guard')` is `web` — a session
            // guard this app never uses; it is only `sanctum` at runtime as a side effect of
            // Authenticate::authenticate() calling shouldUse(). A broken chain refuses a
            // CORRECT password, so the dependency is stated rather than inherited.
            'current_password' => ['required', 'current_password:sanctum'],
            // Mirrors UpdateUserPasswordRequest deliberately (ADR-40(3)): duplicated rather
            // than extracted, so the admin reset and this path cannot drift into different
            // password strengths.
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
