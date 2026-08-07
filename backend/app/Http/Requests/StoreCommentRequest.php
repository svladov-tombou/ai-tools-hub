<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Deliberately unconditional. The real check is `$this->authorize('view', $tool)` in the
     * controller body, because reading and writing a comment are the SAME question — may you
     * see this tool — and that question has exactly one answer, in ToolPolicy::view. Putting a
     * copy here would be a second place to keep in step. The cost is the ADR-28 trade-off,
     * accepted knowingly: validation runs first, so a caller who may not see the tool and
     * sends an invalid body gets 422 before the 403. ToolController already works this way.
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
            // 2000, against 5000 for a tool's description: a description is a document, a
            // comment is a reply. `required` also rejects "" — an empty comment is not a
            // comment.
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
