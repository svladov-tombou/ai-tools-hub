<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `tool_id` and `user_id` are deliberately OUTSIDE #[Fillable] — the same anti-tampering
 * rule as `created_by` on Tool (ADR-12) and `is_active` on User. Both are set by direct
 * property assignment in the controller: the tool comes from the URL and the author comes
 * from the token, so neither may be taken from a request body.
 */
#[Fillable(['body'])]
class Comment extends Model
{
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
