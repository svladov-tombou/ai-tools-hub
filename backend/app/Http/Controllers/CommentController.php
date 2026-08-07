<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Tool;

class CommentController extends Controller
{
    public function index(Tool $tool)
    {
        $this->authorize('view', $tool);

        return $tool->comments()
            ->with('user')
            ->orderByDesc('created_at')
            // timestamps() stores whole seconds, so two comments written in the same second
            // tie on created_at and nothing in SQL then fixes their order. Measured on this
            // MySQL 8.4: the tie already comes back id-descending, because the query walks the
            // (tool_id, created_at) index backwards and the primary key is the last part of
            // that index. So this line changes no result TODAY — it pins one, against a
            // different query plan, a different index, or a different engine picking the other
            // direction. Paginated output makes an unstable tie worse than cosmetic: a row can
            // repeat on page 2 or vanish between pages.
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function store(StoreCommentRequest $request, Tool $tool)
    {
        $this->authorize('view', $tool);

        // Built and saved once, rather than Comment::create(...) then assign: tool_id is NOT
        // NULL, so an insert that ran before the assignment would fail on the constraint.
        $comment = new Comment($request->safe()->only('body'));
        $comment->tool_id = $tool->id;
        $comment->user_id = $request->user()->id;
        $comment->save();

        return response()->json($comment->load('user'), 201);
    }
}
