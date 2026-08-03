<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Role;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeRole(string $name, int $level): Role
{
    return Role::create([
        'name' => $name,
        'display_name' => ucfirst($name),
        'level' => $level,
    ]);
}

function makeUserWithRole(string $name, int $level): User
{
    $user = User::factory()->create();
    $user->roles()->attach(makeRole($name, $level));

    return $user;
}

function makeTool(array $overrides = []): Tool
{
    $createdBy = array_key_exists('created_by', $overrides) ? $overrides['created_by'] : null;
    unset($overrides['created_by']);

    $tool = Tool::create(array_merge([
        'name' => 'Test Tool',
        'description' => 'A tool for testing',
        'url' => 'https://example.com',
        'status' => 'published',
    ], $overrides));

    $tool->created_by = $createdBy;
    $tool->save();

    return $tool;
}

// READ

test('an authenticated user can list tools', function () {
    Sanctum::actingAs(User::factory()->create());

    makeTool();

    $this->getJson('/api/tools')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('an unauthenticated request to list tools is rejected', function () {
    $this->getJson('/api/tools')->assertStatus(401);
});

test('search filters tools by name or description', function () {
    Sanctum::actingAs(User::factory()->create());

    makeTool(['name' => 'Awesome Copilot', 'description' => 'An AI pair programmer']);
    makeTool(['name' => 'Random Tool', 'description' => 'Does something unrelated']);

    $response = $this->getJson('/api/tools?search=Copilot')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Awesome Copilot');
});

test('category filter returns only tools in that category', function () {
    Sanctum::actingAs(User::factory()->create());

    $category = Category::create(['name' => ['bg' => 'Писане', 'en' => 'Writing'], 'slug' => 'writing']);

    $matching = makeTool(['name' => 'In Category']);
    $matching->categories()->attach($category);

    makeTool(['name' => 'Not In Category']);

    $response = $this->getJson('/api/tools?category=writing')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('In Category');
});

// DEPARTMENT FILTER

test('department filter returns only tools in that department', function () {
    Sanctum::actingAs(User::factory()->create());

    $department = Department::create(['name' => 'Marketing', 'slug' => 'marketing']);

    $matching = makeTool(['name' => 'In Department']);
    $matching->departments()->attach($department);

    makeTool(['name' => 'Not In Department']);

    $response = $this->getJson('/api/tools?department=marketing')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('In Department');
});

test('department and category filters combine as AND, not OR', function () {
    Sanctum::actingAs(User::factory()->create());

    $marketing = Department::create(['name' => 'Marketing', 'slug' => 'marketing']);
    $writing = Category::create(['name' => ['bg' => 'Писане', 'en' => 'Writing'], 'slug' => 'writing']);

    // Tool A matches BOTH filters
    $both = makeTool(['name' => 'Both']);
    $both->departments()->attach($marketing);
    $both->categories()->attach($writing);

    // Tool B matches ONLY the category
    $categoryOnly = makeTool(['name' => 'Category Only']);
    $categoryOnly->categories()->attach($writing);

    $response = $this->getJson('/api/tools?department=marketing&category=writing')->assertOk();

    // AND => 1 (only "Both"); OR would wrongly return 2
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Both');
});

// CREATE

test('an authenticated user can create a tool with categories and roles attached', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => ['bg' => 'Продуктивност', 'en' => 'Productivity'], 'slug' => 'productivity']);
    $role = makeRole('manager', 40);

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => 'Does things',
        'url' => 'https://example.com',
        'category_ids' => [$category->id],
        'role_ids' => [$role->id],
    ]);

    $response->assertCreated();
    expect($response->json('created_by'))->toBe($user->id);
    expect($response->json('categories.0.id'))->toBe($category->id);
    expect($response->json('roles.0.id'))->toBe($role->id);
});

test('creating a tool with invalid data returns a validation error', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/tools', [
        'description' => 'Missing a name',
        'url' => 'not-a-url',
    ])->assertStatus(422);
});

test('creating a tool with a description over 5000 characters is rejected', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => str_repeat('a', 5001),
        'url' => 'https://example.com',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('description');
});

test('creating a tool with a description of exactly 5000 characters is accepted', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/tools', [
        'name' => 'New Tool',
        'description' => str_repeat('a', 5000),
        'url' => 'https://example.com',
    ]);

    $response->assertStatus(201);
});

test('updating a tool with a description over 5000 characters is rejected', function () {
    $author = User::factory()->create();
    Sanctum::actingAs($author);

    $tool = makeTool(['created_by' => $author->id]);

    $response = $this->putJson("/api/tools/{$tool->id}", [
        'description' => str_repeat('a', 5001),
    ]);

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('description');
});

// UPDATE / DELETE authorization (ADR-12)

test('the author can update their own tool', function () {
    $author = User::factory()->create();
    Sanctum::actingAs($author);

    $tool = makeTool(['created_by' => $author->id]);

    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Updated Name'])
        ->assertOk()
        ->assertJsonPath('name', 'Updated Name');

    expect($tool->fresh()->name)->toBe('Updated Name');
});

test('the author can delete their own tool', function () {
    $author = User::factory()->create();
    Sanctum::actingAs($author);

    $tool = makeTool(['created_by' => $author->id]);

    $this->deleteJson("/api/tools/{$tool->id}")->assertNoContent();

    expect(Tool::find($tool->id))->toBeNull();
});

test('a different regular user cannot update another users tool', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();

    $tool = makeTool(['created_by' => $author->id]);

    Sanctum::actingAs($other);

    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('a different regular user cannot delete another users tool', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();

    $tool = makeTool(['created_by' => $author->id]);

    Sanctum::actingAs($other);

    $this->deleteJson("/api/tools/{$tool->id}")->assertForbidden();
});

test('an owner can update and delete a tool they did not create', function () {
    $author = User::factory()->create();
    $owner = makeUserWithRole('owner', 100);

    $tool = makeTool(['created_by' => $author->id]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Moderated by owner'])->assertOk();
    $this->deleteJson("/api/tools/{$tool->id}")->assertNoContent();
});

test('a pm can update and delete a tool they did not create', function () {
    $author = User::factory()->create();
    $pm = makeUserWithRole('pm', 60);

    $tool = makeTool(['created_by' => $author->id]);

    Sanctum::actingAs($pm);

    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Moderated by pm'])->assertOk();
    $this->deleteJson("/api/tools/{$tool->id}")->assertNoContent();
});

test('a tool with no author can only be updated by owner or pm, not a regular user', function () {
    $tool = makeTool(['created_by' => null]);

    Sanctum::actingAs(User::factory()->create());
    $this->putJson("/api/tools/{$tool->id}", ['name' => 'Regular attempt'])->assertForbidden();

    Sanctum::actingAs(makeUserWithRole('owner', 100));
    $this->putJson("/api/tools/{$tool->id}", ['name' => 'By owner'])->assertOk();

    Sanctum::actingAs(makeUserWithRole('pm', 60));
    $this->putJson("/api/tools/{$tool->id}", ['name' => 'By pm'])->assertOk();
});

// DELETE — authorization edges and cascade (ADR-12, ADR-41)

test('an unauthenticated delete request is rejected and the tool is not deleted', function () {
    $tool = makeTool();

    $this->deleteJson("/api/tools/{$tool->id}")->assertStatus(401);

    // Without this the test would still pass if the endpoint quietly deleted the tool
    // before rejecting the request.
    expect(Tool::find($tool->id))->not->toBeNull();
});

test('a manager can see but not delete another users tool', function () {
    $author = User::factory()->create();
    $tool = makeTool(['created_by' => $author->id]);

    $manager = makeUserWithRole('manager', 40);
    Sanctum::actingAs($manager);

    // A manager reads every tool including drafts (ADR-35), so this 200 is what makes the
    // 403 below meaningful rather than trivially true — the manager can find the tool, they
    // are just not allowed to write to it.
    $this->getJson("/api/tools/{$tool->id}")->assertOk();

    // A manager is not a catalog administrator for writes (ADR-12): only the author, owner
    // or pm may delete.
    $this->deleteJson("/api/tools/{$tool->id}")->assertForbidden();

    expect(Tool::find($tool->id))->not->toBeNull();
});

test('a tool with no author can only be deleted by owner or pm, not a regular user', function () {
    $toolA = makeTool(['created_by' => null]);

    Sanctum::actingAs(User::factory()->create());
    $this->deleteJson("/api/tools/{$toolA->id}")->assertForbidden();
    expect(Tool::find($toolA->id))->not->toBeNull();

    Sanctum::actingAs(makeUserWithRole('owner', 100));
    $this->deleteJson("/api/tools/{$toolA->id}")->assertNoContent();
    expect(Tool::find($toolA->id))->toBeNull();

    // A fresh authorless tool: the one above was actually deleted and cannot be deleted twice.
    $toolB = makeTool(['created_by' => null]);

    Sanctum::actingAs(makeUserWithRole('pm', 60));
    $this->deleteJson("/api/tools/{$toolB->id}")->assertNoContent();
    expect(Tool::find($toolB->id))->toBeNull();
});

test('deleting a tool cascades its pivot rows but leaves categories, roles and departments intact', function () {
    $author = User::factory()->create();
    Sanctum::actingAs($author);

    $tool = makeTool(['created_by' => $author->id]);

    $category = Category::create(['name' => ['bg' => 'Тест', 'en' => 'Test'], 'slug' => 'cascade-test']);
    // A real role name and level: CLAUDE.md allows exactly four, and a pivot fixture is no
    // reason to invent a fifth.
    $role = makeRole('employee', 20);
    $department = Department::create(['name' => 'Cascade', 'slug' => 'cascade']);

    $tool->categories()->attach($category);
    $tool->roles()->attach($role);
    $tool->departments()->attach($department);

    // Without this "before" half the test would still pass even if the fixture never
    // attached anything at all, proving nothing about the cascade below.
    expect(DB::table('category_tool')->where('tool_id', $tool->id)->count())->toBe(1);
    expect(DB::table('role_tool')->where('tool_id', $tool->id)->count())->toBe(1);
    expect(DB::table('department_tool')->where('tool_id', $tool->id)->count())->toBe(1);

    $this->deleteJson("/api/tools/{$tool->id}")->assertNoContent();

    expect(Tool::find($tool->id))->toBeNull();

    // The cascading foreign keys must take the LINKS, never the catalogue entries.
    expect(DB::table('category_tool')->where('tool_id', $tool->id)->count())->toBe(0);
    expect(DB::table('role_tool')->where('tool_id', $tool->id)->count())->toBe(0);
    expect(DB::table('department_tool')->where('tool_id', $tool->id)->count())->toBe(0);

    expect(Category::find($category->id))->not->toBeNull();
    expect(Role::find($role->id))->not->toBeNull();
    expect(Department::find($department->id))->not->toBeNull();
});
