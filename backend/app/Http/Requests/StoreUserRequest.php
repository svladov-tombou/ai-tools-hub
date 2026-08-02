<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Authorization runs BEFORE validation, so a manager or employee gets 403 whatever
     * they send — an invalid payload from a non-admin must not answer with 422 and leak
     * the validation rules. This is why the check lives here and not in the controller.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('create', User::class)) {
            return false;
        }

        // A non-owner may not create a user holding the `owner` role. The payload is
        // unvalidated at this point, hence the defensive Arr::wrap and int cast — it
        // must not throw on junk input.
        $ownerRoleId = Role::where('name', 'owner')->value('id');
        $requestedRoleIds = collect(Arr::wrap($this->input('role_ids')))->map(fn ($id) => (int) $id);

        if ($requestedRoleIds->contains($ownerRoleId) && ! $this->user()->hasRole('owner')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // `min:1`: a user with no role is an undefined state for every policy in the
            // app, which all ask `hasRole()`.
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
