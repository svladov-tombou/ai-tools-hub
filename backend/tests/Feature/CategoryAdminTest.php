<?php

use App\Models\Category;
use App\Models\Role;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Creates the role, a user holding it, and authenticates as that user.
 * Levels mirror RoleSeeder; the tests do not seed.
 */
function actingAsRole(string $roleName): User
{
    $level = match ($roleName) {
        'owner' => 100,
        'pm' => 60,
        'manager' => 40,
        'employee' => 20,
    };

    $user = User::factory()->create();
    $user->roles()->attach(Role::create([
        'name' => $roleName,
        'display_name' => ucfirst($roleName),
        'level' => $level,
    ]));

    Sanctum::actingAs($user);

    return $user;
}

function validCategoryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['bg' => 'Нова категория', 'en' => 'New category', 'fr' => 'Nouvelle catégorie'],
        'slug' => 'new-category',
    ], $overrides);
}

// AUTHORIZATION — the menu is not the guard; every write endpoint is tested from a
// non-admin role, and each test asserts that nothing was written, not merely the status.

test('an unauthenticated request cannot create, update or delete a category', function () {
    $category = Category::create(['name' => ['bg' => 'Писане'], 'slug' => 'writing']);

    $this->postJson('/api/categories', validCategoryPayload())->assertStatus(401);
    $this->putJson("/api/categories/{$category->id}", ['name' => ['bg' => 'X']])->assertStatus(401);
    $this->deleteJson("/api/categories/{$category->id}")->assertStatus(401);
});

test('owner and pm can create a category', function (string $role) {
    actingAsRole($role);

    $response = $this->postJson('/api/categories', validCategoryPayload())->assertStatus(201);

    // Exactly the shape /api/categories returns. If someone later returns the whole model,
    // this fails rather than silently widening the API with timestamps (the ADR-18 rule).
    expect(array_keys($response->json()))->toBe(['id', 'name', 'slug']);

    expect(Category::where('slug', 'new-category')->first()->name)->toBe([
        'bg' => 'Нова категория',
        'en' => 'New category',
        'fr' => 'Nouvelle catégorie',
    ]);
})->with(['owner', 'pm']);

test('manager and employee are forbidden from creating a category', function (string $role) {
    actingAsRole($role);

    $this->postJson('/api/categories', validCategoryPayload())->assertStatus(403);

    expect(Category::count())->toBe(0);
})->with(['manager', 'employee']);

test('manager and employee are forbidden from updating a category', function (string $role) {
    $category = Category::create(['name' => ['bg' => 'Оригинал'], 'slug' => 'original']);
    actingAsRole($role);

    $this->putJson("/api/categories/{$category->id}", ['name' => ['bg' => 'Променено']])
        ->assertStatus(403);

    expect($category->fresh()->name)->toBe(['bg' => 'Оригинал']);
})->with(['manager', 'employee']);

test('manager and employee are forbidden from deleting a category', function (string $role) {
    $category = Category::create(['name' => ['bg' => 'Оригинал'], 'slug' => 'original']);
    actingAsRole($role);

    $this->deleteJson("/api/categories/{$category->id}")->assertStatus(403);

    expect(Category::find($category->id))->not->toBeNull();
})->with(['manager', 'employee']);

test('a forbidden role gets 403 even when the payload is invalid', function () {
    actingAsRole('manager');

    // Authorization runs in the Form Request, i.e. BEFORE validation. If the check moved
    // into the controller body this would answer 422 and leak the validation rules to a
    // user who may not write at all.
    $this->postJson('/api/categories', ['slug' => 'НЕ Е ВАЛИДЕН'])->assertStatus(403);
});

// UPDATE

test('owner can rename a category in all three languages', function () {
    $category = Category::create(['name' => ['bg' => 'Старо', 'en' => 'Old'], 'slug' => 'writing']);
    actingAsRole('owner');

    $this->putJson("/api/categories/{$category->id}", [
        'name' => ['bg' => 'Ново', 'en' => 'New', 'fr' => 'Nouveau'],
    ])->assertOk();

    expect($category->fresh()->name)->toBe(['bg' => 'Ново', 'en' => 'New', 'fr' => 'Nouveau']);
    expect($category->fresh()->slug)->toBe('writing');
});

test('the slug cannot be changed after creation', function () {
    $category = Category::create(['name' => ['bg' => 'Писане'], 'slug' => 'writing']);
    actingAsRole('owner');

    $this->putJson("/api/categories/{$category->id}", [
        'name' => ['bg' => 'Писане'],
        'slug' => 'texts',
    ])->assertStatus(422)->assertJsonValidationErrors('slug');

    expect($category->fresh()->slug)->toBe('writing');
});

// VALIDATION OF THE TRANSLATION MAP

test('the bulgarian name is required', function () {
    actingAsRole('owner');

    // bg is the fallback the frontend falls back TO (ADR-27); without it a category has
    // no name at all in any locale.
    $this->postJson('/api/categories', validCategoryPayload(['name' => ['en' => 'Only English']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('name.bg');
});

test('english and french are optional', function () {
    actingAsRole('owner');

    $this->postJson('/api/categories', validCategoryPayload(['name' => ['bg' => 'Само български']]))
        ->assertStatus(201);

    expect(Category::first()->name)->toBe(['bg' => 'Само български']);
});

test('an unknown language key is rejected', function () {
    actingAsRole('owner');

    // A typo'd or unsupported key would be stored and never rendered, because the frontend
    // only ever reads bg, en and fr.
    $this->postJson('/api/categories', validCategoryPayload([
        'name' => ['bg' => 'Тест', 'de' => 'Test'],
    ]))->assertStatus(422)->assertJsonValidationErrors('name');

    expect(Category::count())->toBe(0);
});

test('a present but empty translation is rejected', function () {
    actingAsRole('owner');

    // "" is a valid string, so `string` alone would accept it and the English UI would
    // render a blank label instead of falling back to Bulgarian.
    $this->postJson('/api/categories', validCategoryPayload([
        'name' => ['bg' => 'Тест', 'en' => ''],
    ]))->assertStatus(422)->assertJsonValidationErrors('name.en');
});

test('a category name of 255 characters is accepted and 256 is rejected', function () {
    actingAsRole('owner');

    // A boundary pair, not a round number: a 10,000-character test would also pass against
    // max:9999 and would prove nothing about the limit that is actually configured.
    $this->postJson('/api/categories', validCategoryPayload([
        'name' => ['bg' => str_repeat('а', 255)],
        'slug' => 'exactly-255',
    ]))->assertStatus(201);

    $this->postJson('/api/categories', validCategoryPayload([
        'name' => ['bg' => str_repeat('а', 256)],
        'slug' => 'one-too-many',
    ]))->assertStatus(422)->assertJsonValidationErrors('name.bg');
});

// SLUG FORMAT — the slug travels in ?category=<slug>, so the format has to hold.

test('an invalid slug format is rejected', function (string $slug) {
    actingAsRole('owner');

    $this->postJson('/api/categories', validCategoryPayload(['slug' => $slug]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
})->with([
    'cyrillic' => 'асистенти',
    'spaces' => 'code assistants',
    'capitals' => 'Code-Assistants',
    'underscore' => 'code_assistants',
    'leading hyphen' => '-code',
    'trailing hyphen' => 'code-',
    'double hyphen' => 'code--assistants',
]);

test('a lowercase hyphenated slug is accepted', function () {
    actingAsRole('owner');

    // The other half of the pair: a regex that rejected everything would pass the test
    // above and fail this one.
    $this->postJson('/api/categories', validCategoryPayload(['slug' => 'code-assistants-2']))
        ->assertStatus(201);
});

test('a duplicate slug is rejected', function () {
    Category::create(['name' => ['bg' => 'Писане'], 'slug' => 'writing']);
    actingAsRole('owner');

    $this->postJson('/api/categories', validCategoryPayload(['slug' => 'writing']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

// DELETION

test('a category no tool uses can be deleted', function () {
    $category = Category::create(['name' => ['bg' => 'Празна'], 'slug' => 'empty']);
    actingAsRole('owner');

    $this->deleteJson("/api/categories/{$category->id}")->assertStatus(204);

    expect(Category::find($category->id))->toBeNull();
});

test('deleting a category still used by tools is blocked and names the count', function () {
    $category = Category::create(['name' => ['bg' => 'Заета'], 'slug' => 'busy']);

    foreach (['First tool', 'Second tool'] as $name) {
        $tool = Tool::create([
            'name' => $name,
            'description' => 'Uses the busy category',
            'url' => 'https://example.com',
            'status' => 'published',
        ]);
        $tool->categories()->attach($category);
    }

    actingAsRole('owner');

    $response = $this->deleteJson("/api/categories/{$category->id}")->assertStatus(422);

    // The count is in the message: "some tools use it" does not tell the admin what to fix.
    expect($response->json('errors.category.0'))->toContain('2');

    // Without the guard this would return 204 and the cascading foreign keys would delete
    // both category_tool rows — two tools silently losing a category, with no trace.
    expect(Category::find($category->id))->not->toBeNull();
    expect($category->tools()->count())->toBe(2);
});
