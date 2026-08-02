<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserPasswordRequest extends FormRequest
{
    /**
     * Authorization runs BEFORE validation, so a non-admin gets 403 whatever the
     * payload looks like.
     */
    public function authorize(): bool
    {
        return $this->user()->can('updatePassword', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `confirmed` (expecting `password_confirmation`) is not ceremony here: this
            // app has no self-service password reset, so a typo in an initial password
            // locks the new user out until an admin fixes it.
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
