<?php

use App\Models\Comment;
use App\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * created_by is outside #[Fillable], so it is assigned after the create — the same shape the
 * other tool test files use.
 */
function commentTool(array $overrides = []): Tool
{
    $createdBy = $overrides['created_by'] ?? null;
    unset($overrides['created_by']);

    $tool = Tool::create(array_merge([
        'name' => 'Commented Tool',
        'description' => 'A tool for comment tests',
        'url' => 'https://example.com',
        'status' => 'published',
    ], $overrides));

    $tool->created_by = $createdBy;
    $tool->save();

    return $tool;
}

/** Writes a comment straight to the database, bypassing the endpoint, at a chosen time. */
function writeComment(Tool $tool, int $userId, string $body, ?string $at = null): Comment
{
    $comment = new Comment(['body' => $body]);
    $comment->tool_id = $tool->id;
    $comment->user_id = $userId;

    if ($at !== null) {
        $comment->created_at = $at;
        $comment->updated_at = $at;
    }

    $comment->save();

    return $comment;
}

test('a user who sees the tool can post a comment and read it back', function () {
    $employee = createUserWithRole('employee');
    $tool = commentTool();

    Sanctum::actingAs($employee);

    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => 'First comment'])
        ->assertCreated()
        ->assertJsonPath('body', 'First comment')
        ->assertJsonPath('user.id', $employee->id);

    $response = $this->getJson("/api/tools/{$tool->id}/comments")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toBe('First comment');
    expect($response->json('data.0.user.name'))->toBe($employee->name);
});

test('an employee refused a foreign draft is refused its comments on both endpoints', function () {
    $owner = createUserWithRole('owner');
    $employee = createUserWithRole('employee');

    $tool = commentTool(['status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/tools/{$tool->id}/comments")->assertForbidden();
    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Sneaking in'])->assertForbidden();

    // The status code alone would not prove the write was refused rather than merely unreported.
    expect(Comment::count())->toBe(0);
});

test('the author of a draft can comment on their own draft', function () {
    $employee = createUserWithRole('employee');

    $tool = commentTool(['status' => 'draft', 'created_by' => $employee->id]);

    Sanctum::actingAs($employee);

    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Note to self'])->assertCreated();

    $response = $this->getJson("/api/tools/{$tool->id}/comments")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

test('an unauthenticated request is rejected on both endpoints', function () {
    $tool = commentTool();

    $this->getJson("/api/tools/{$tool->id}/comments")->assertStatus(401);
    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Anonymous'])->assertStatus(401);

    expect(Comment::count())->toBe(0);
});

test('an empty body is rejected', function () {
    Sanctum::actingAs(createUserWithRole('employee'));
    $tool = commentTool();

    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    expect(Comment::count())->toBe(0);
});

test('a body of exactly 2000 characters is accepted and 2001 is rejected', function () {
    Sanctum::actingAs(createUserWithRole('employee'));
    $tool = commentTool();

    // The boundary PAIR is the point: a 3000-character test would also pass against max:2500.
    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => str_repeat('a', 2000)])
        ->assertCreated();

    $this->postJson("/api/tools/{$tool->id}/comments", ['body' => str_repeat('a', 2001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    expect(Comment::count())->toBe(1);
});

test('a user_id and a tool_id sent in the body are ignored', function () {
    $author = createUserWithRole('employee');
    $victim = createUserWithRole('owner');

    $tool = commentTool();
    $otherTool = commentTool(['name' => 'Other Tool']);

    Sanctum::actingAs($author);

    $response = $this->postJson("/api/tools/{$tool->id}/comments", [
        'body' => 'Whose comment is this?',
        'user_id' => $victim->id,
        'tool_id' => $otherTool->id,
    ])->assertCreated();

    expect($response->json('user.id'))->toBe($author->id);

    $comment = Comment::first();

    // The author comes from the token and the tool from the URL — never from the payload.
    expect($comment->user_id)->toBe($author->id);
    expect($comment->tool_id)->toBe($tool->id);
});

test('two comments written in the same second come back in a stable order', function () {
    $user = createUserWithRole('employee');
    $tool = commentTool();

    $sameSecond = '2026-08-07 10:00:00';

    $first = writeComment($tool, $user->id, 'First', $sameSecond);
    $second = writeComment($tool, $user->id, 'Second', $sameSecond);

    // The premise of the test, asserted rather than assumed: without a genuine tie on
    // created_at the rest of this proves nothing at all.
    expect($first->fresh()->created_at->eq($second->fresh()->created_at))->toBeTrue();
    expect($second->id)->toBeGreaterThan($first->id);

    // What this test does and does not catch, measured rather than assumed (2026-08-07).
    // Deleting `orderByDesc('id')` from the controller leaves it GREEN: this MySQL already
    // returns the tie id-descending on its own. Reversing the tiebreaker to ascending turns
    // it RED. So it pins the contract and catches a wrong order; it cannot catch a missing
    // one, and no test through this endpoint can, because the behaviour it guards against is
    // a query plan this database does not currently choose.

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/tools/{$tool->id}/comments")->assertOk();

    expect($response->json('data.0.id'))->toBe($second->id);
    expect($response->json('data.1.id'))->toBe($first->id);
});

test('comments are paginated 50 at a time, newest first', function () {
    $user = createUserWithRole('employee');
    $tool = commentTool();

    foreach (range(1, 51) as $i) {
        writeComment($tool, $user->id, "Comment {$i}", now()->subMinutes(51 - $i)->toDateTimeString());
    }

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/tools/{$tool->id}/comments")->assertOk();

    expect($response->json('per_page'))->toBe(50);
    expect($response->json('total'))->toBe(51);
    expect($response->json('data'))->toHaveCount(50);
    expect($response->json('data.0.body'))->toBe('Comment 51');
    expect($response->json('data.49.body'))->toBe('Comment 2');

    $secondPage = $this->getJson("/api/tools/{$tool->id}/comments?page=2")->assertOk();

    expect($secondPage->json('data'))->toHaveCount(1);
    expect($secondPage->json('data.0.body'))->toBe('Comment 1');
});

test('the list holds only the comments of the tool in the url', function () {
    $user = createUserWithRole('employee');

    $tool = commentTool();
    $otherTool = commentTool(['name' => 'Other Tool']);

    writeComment($tool, $user->id, 'Belongs here');
    writeComment($otherTool, $user->id, 'Belongs elsewhere');

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/tools/{$tool->id}/comments")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toBe('Belongs here');
});

test('deleting a tool deletes its comments and leaves other tools alone', function () {
    $owner = createUserWithRole('owner');

    $tool = commentTool(['created_by' => $owner->id]);
    $otherTool = commentTool(['name' => 'Untouched Tool']);

    writeComment($tool, $owner->id, 'Goes away');
    writeComment($otherTool, $owner->id, 'Stays');

    expect(Comment::count())->toBe(2);

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/tools/{$tool->id}")->assertNoContent();

    // The surviving comment is what discriminates: a cascade that emptied the whole table
    // would satisfy "the deleted tool's comments are gone" just as well.
    expect(Comment::count())->toBe(1);
    expect(Comment::first()->tool_id)->toBe($otherTool->id);
});
