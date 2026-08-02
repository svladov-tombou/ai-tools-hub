<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateUserRolesRequest extends FormRequest
{
    /**
     * Authorization runs BEFORE validation, so a non-admin gets 403 whatever the
     * payload looks like.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('updateRoles', $this->route('user'))) {
            return false;
        }

        // Only an owner may grant or revoke the `owner` role. An owner actor may do
        // anything further; for a non-owner actor, the submitted set must not CHANGE
        // whether the target holds the owner role. Written symmetrically on purpose:
        // the revoke direction is today also blocked upstream by the policy's pm/owner
        // rule, and keeping both directions here means the "only an owner touches the
        // owner role" guarantee survives if that upstream rule is ever relaxed.
        if ($this->user()->hasRole('owner')) {
            return true;
        }

        $ownerRoleId = Role::where('name', 'owner')->value('id');
        $requestedRoleIds = collect(Arr::wrap($this->input('role_ids')))->map(fn ($id) => (int) $id);
        $requestedHasOwner = $requestedRoleIds->contains($ownerRoleId);
        $targetHasOwner = $this->route('user')->hasRole('owner');

        return $requestedHasOwner === $targetHasOwner;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
