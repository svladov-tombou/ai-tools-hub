<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Authorization runs BEFORE validation, so a non-admin gets 403 whatever the
     * payload looks like.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            // `prohibited` on both is deliberate: roles and passwords have their own
            // endpoints with their own authorization rules, and a silently ignored field
            // is how a caller comes to believe it changed something it did not. A loud
            // 422 is the point — the same reasoning as the immutable category slug in
            // UpdateCategoryRequest.
            'role_ids' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
