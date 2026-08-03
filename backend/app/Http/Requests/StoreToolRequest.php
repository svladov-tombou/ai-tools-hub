<?php

namespace App\Http\Requests;

use App\Models\Tool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreToolRequest extends FormRequest
{
    /**
     * A non-publisher's tool always starts as a draft (ADR-35).
     *
     * The guard is `! filled(...)`, so this only fills a status that was ABSENT (or null, or an
     * empty string). An explicitly sent `published` must survive untouched into authorize(),
     * which runs AFTER this method — overwriting it here would turn the intended 403 into a
     * silent downgrade, and the caller would get 201 for a publication that never happened.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('status') && ! $this->user()->can('publish', Tool::class)) {
            $this->merge(['status' => 'draft']);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * Only owner and pm may publish (ADR-35). This lives here rather than in ToolPolicy::update
     * because a policy ability receives the model and the user but never the payload, so it
     * cannot see that `status` is the field being set. Running here also means the answer comes
     * BEFORE validation (the ADR-28 precedent): the refusal cannot depend on what else the
     * request happens to carry.
     */
    public function authorize(): bool
    {
        if ($this->input('status') !== 'published') {
            return true;
        }

        return $this->user()->can('publish', Tool::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'url' => ['required', 'url'],
            'documentation_url' => ['nullable', 'url'],
            'video_url' => ['nullable', 'url'],
            'difficulty' => ['nullable', 'in:beginner,intermediate,advanced'],
            'status' => ['nullable', 'in:draft,published'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ];
    }
}
