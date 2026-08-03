<?php

namespace App\Http\Requests;

use App\Models\Tool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateToolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only owner and pm may change a tool's `status`, in EITHER direction (ADR-37, correcting
     * ADR-35(5), which restricted only the move to `published`). With draft visibility in force,
     * an author who could unpublish their own tool would remove it from everyone else's list and
     * route around moderation.
     *
     * A change is measured against the STORED value: the request carries one only when `status` is
     * present AND differs from the row. Three things are therefore NOT a change and stay allowed —
     * an absent key (an ordinary rename must not become a 403), a value equal to the stored one (a
     * client echoing the object back), and, in StoreToolRequest, creation, which has no previous
     * value. A no-op naming the current value stays allowed even when that value is `published`.
     *
     * This lives here rather than in ToolPolicy::update because a policy ability receives the model
     * and the user but never the payload. It also runs BEFORE validation (the ADR-28 precedent), so
     * the refusal cannot depend on what else the request happens to carry. The stored row is reached
     * through route model binding, which resolves before the request does.
     */
    public function authorize(): bool
    {
        $tool = $this->route('tool');

        if (! $this->has('status') || $this->input('status') === $tool->status) {
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'url' => ['sometimes', 'url'],
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
