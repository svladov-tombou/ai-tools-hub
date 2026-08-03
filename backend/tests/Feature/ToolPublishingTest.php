<?php

use App\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function publishingTool(array $overrides = []): Tool
{
    $createdBy = $overrides['created_by'] ?? null;
    unset($overrides['created_by']);

    $tool = Tool::create(array_merge([
        'name' => 'Publishing Tool',
        'description' => 'A tool for publishing tests',
        'url' => 'https://example.com',
        'status' => 'draft',
    ], $overrides));

    $tool->created_by = $createdBy;
    $tool->save();

    return $tool;
}

// THE CENTRAL TEST OF THIS PHASE. Authorship passes ToolPolicy::update, so only the role
// check on publishing can stop this — a naive implementation that only checks update() would
// return 200 here.
test('an employee cannot publish their OWN tool', function () {
    $employee = createUserWithRole('employee');
    $tool = publishingTool(['created_by' => $employee->id]);

    Sanctum::actingAs($employee);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'published'])
        ->assertForbidden();

    expect($tool->fresh()->status)->toBe('draft');
});

test('a manager cannot publish their own tool', function () {
    $manager = createUserWithRole('manager');
    $tool = publishingTool(['created_by' => $manager->id]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'published'])
        ->assertForbidden();

    expect($tool->fresh()->status)->toBe('draft');
});

test('an owner can publish their own draft', function () {
    $owner = createUserWithRole('owner');
    $tool = publishingTool(['created_by' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'published'])
        ->assertOk()
        ->assertJsonPath('status', 'published');

    expect($tool->fresh()->status)->toBe('published');
});

test('a pm can publish a draft they did not create', function () {
    $employee = createUserWithRole('employee');
    $pm = createUserWithRole('pm');
    $tool = publishingTool(['created_by' => $employee->id]);

    Sanctum::actingAs($pm);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'published'])
        ->assertOk();

    expect($tool->fresh()->status)->toBe('published');
});

test('an employee creating a tool without a status gets a draft', function () {
    $employee = createUserWithRole('employee');
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => 'Does things',
        'url' => 'https://example.com',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'draft');
});

test('a manager creating a tool without a status gets a draft', function () {
    $manager = createUserWithRole('manager');
    Sanctum::actingAs($manager);

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => 'Does things',
        'url' => 'https://example.com',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'draft');
});

// The ADR-11 column default still applying to publishers — this is what makes the absence
// of a migration correct.
test('an owner creating a tool without a status gets the published default', function () {
    $owner = createUserWithRole('owner');
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => 'Does things',
        'url' => 'https://example.com',
    ]);

    $response->assertCreated();

    // Asserted separately from the JSON on purpose: the stored row and the returned body are
    // two different claims, and they disagreed before Tool::$attributes was added.
    expect(Tool::first()->status)->toBe('published');

    $response->assertJsonPath('status', 'published');
});

test('an employee explicitly asking to publish on create is refused and nothing is written', function () {
    $employee = createUserWithRole('employee');
    Sanctum::actingAs($employee);

    $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => 'Does things',
        'url' => 'https://example.com',
        'status' => 'published',
    ])->assertForbidden();

    expect(Tool::count())->toBe(0);
});

// Proves authorize() runs before validation — the ADR-28 property the placement was chosen
// for. If this ever returns 422, the check has drifted into the controller or the rules.
test('a non-publisher is refused BEFORE validation runs', function () {
    $employee = createUserWithRole('employee');
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/tools', [
        'url' => 'not-a-url',
        'status' => 'published',
    ]);

    $response->assertStatus(403);
});

// This is the test that fails if prepareForValidation() is ever copied into UpdateToolRequest.
test('an author saving an unrelated edit does not unpublish their own published tool', function () {
    $employee = createUserWithRole('employee');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'published']);

    Sanctum::actingAs($employee);

    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Renamed By Author'])
        ->assertOk();

    expect($tool->fresh()->status)->toBe('published');
});

// Inverted from 200 to 403 by ADR-37. This test previously codified what ADR-35(5) literally
// allowed, and writing the permission down as an assertion is what exposed it as a hole:
// with draft visibility, an author who unpublishes their own tool hides it from everyone else.
test('an author cannot move their own published tool back to draft', function () {
    $employee = createUserWithRole('employee');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'published']);

    Sanctum::actingAs($employee);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'draft'])
        ->assertForbidden();

    expect($tool->fresh()->status)->toBe('published');
});

test('an owner can move a published tool back to draft', function () {
    $employee = createUserWithRole('employee');
    $owner = createUserWithRole('owner');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'published']);

    Sanctum::actingAs($owner);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'draft'])->assertOk();

    expect($tool->fresh()->status)->toBe('draft');
});

test('a pm can move a published tool back to draft', function () {
    $employee = createUserWithRole('employee');
    $pm = createUserWithRole('pm');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'published']);

    Sanctum::actingAs($pm);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'draft'])->assertOk();

    expect($tool->fresh()->status)->toBe('draft');
});

test('a manager cannot move their own published tool back to draft', function () {
    $manager = createUserWithRole('manager');
    $tool = publishingTool(['created_by' => $manager->id, 'status' => 'published']);

    Sanctum::actingAs($manager);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'draft'])
        ->assertForbidden();

    expect($tool->fresh()->status)->toBe('published');
});

// THE BOUNDARY CASE. Sending the status a tool already has is not a change, so it must pass.
// This is the test that fails if the rule degrades into "the key is present -> 403", which
// every other test in this file would still be green against.
test('sending the status a tool already has is not a change', function () {
    $employee = createUserWithRole('employee');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'draft']);

    Sanctum::actingAs($employee);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'draft', 'name' => 'Renamed'])
        ->assertOk();

    expect($tool->fresh()->status)->toBe('draft');
    expect($tool->fresh()->name)->toBe('Renamed');
});

// The counterintuitive half of the same rule, asserted because ADR-37 claims it: naming the
// current value is a no-op even when that value is `published`. Refusing it would break any
// client that sends the whole object back unchanged. It is not a hole — the same request
// against a DRAFT tool is the 403 two tests above.
test('an author naming the published status their tool already has is not a change', function () {
    $employee = createUserWithRole('employee');
    $tool = publishingTool(['created_by' => $employee->id, 'status' => 'published']);

    Sanctum::actingAs($employee);

    $this->putJson("/api/tools/{$tool->id}", ['status' => 'published'])->assertOk();

    expect($tool->fresh()->status)->toBe('published');
});
