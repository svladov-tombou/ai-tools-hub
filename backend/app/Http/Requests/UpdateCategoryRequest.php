<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Before validation, so a non-admin gets 403 whatever the payload looks like.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The whole map is required on update, not `sometimes`. `name` is a single JSON
            // value, so a partial map REPLACES the stored one — sending {"bg": "..."} would
            // silently drop the English and French translations. Requiring the full map makes
            // the edit form send what it means, the same reasoning as ToolPayload (ADR-23).
            'name' => ['required', 'array:bg,en,fr'],
            'name.bg' => ['required', 'string', 'max:255'],
            'name.en' => ['sometimes', 'string', 'filled', 'max:255'],
            'name.fr' => ['sometimes', 'string', 'filled', 'max:255'],
            // The slug is immutable after creation. `prohibited` makes an attempt to change
            // it a loud 422 rather than a silently ignored field: saved ?category= URLs stay
            // valid, and CategorySeeder's firstOrCreate is keyed on this exact value — a
            // renamed slug would make the next db:seed recreate the original as a new row.
            'slug' => ['prohibited'],
        ];
    }
}
