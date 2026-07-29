<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'url' => ['sometimes', 'url'],
            'documentation_url' => ['nullable', 'url'],
            'video_url' => ['nullable', 'url'],
            'difficulty' => ['nullable', 'in:beginner,intermediate,advanced'],
            'status' => ['nullable', 'in:draft,published'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ];
    }
}
