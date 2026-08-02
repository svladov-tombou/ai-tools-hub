<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Authorization runs BEFORE validation, so a manager or employee gets 403 whatever
     * they send — an invalid payload from a non-admin must not answer with 422 and leak
     * the validation rules. This is why the check lives here and not in the controller.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `array:bg,en,fr` rejects unknown languages: a typo'd key would otherwise be
            // stored silently and never rendered, since the frontend only reads these three.
            'name' => ['required', 'array:bg,en,fr'],
            // bg is required because it is the fallback the frontend falls back TO (ADR-27).
            'name.bg' => ['required', 'string', 'max:255'],
            // Optional, but `filled` rejects "" and null: a present-but-empty translation
            // renders as a blank label instead of falling back to bg.
            'name.en' => ['sometimes', 'string', 'filled', 'max:255'],
            'name.fr' => ['sometimes', 'string', 'filled', 'max:255'],
            // The slug is the wire vocabulary (ADR-26): it travels in ?category=<slug>.
            // Cyrillic, spaces or capitals would produce a filter URL that looks fine and
            // matches nothing. Str::slug cannot derive it from a Bulgarian name, so it is
            // typed by hand and the format has to be enforced.
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'unique:categories,slug',
            ],
        ];
    }
}
