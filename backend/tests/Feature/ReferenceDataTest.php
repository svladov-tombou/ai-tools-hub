<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// CATEGORIES

test('an unauthenticated request to list categories is rejected', function () {
    $this->getJson('/api/categories')->assertStatus(401);
});

test('an authenticated user can list categories', function () {
    Sanctum::actingAs(User::factory()->create());

    Category::create(['name' => ['bg' => 'Писане', 'en' => 'Writing'], 'slug' => 'writing']);
    Category::create(['name' => ['bg' => 'Код', 'en' => 'Coding'], 'slug' => 'coding']);

    $response = $this->getJson('/api/categories')->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json())->toHaveCount(2);

    foreach ($response->json() as $category) {
        expect(array_keys($category))->toBe(['id', 'name', 'slug']);
    }
});

test('a category name is returned as a translation map for every language', function () {
    Sanctum::actingAs(User::factory()->create());

    Category::create([
        'name' => [
            'bg' => 'Продуктивност',
            'en' => 'Productivity',
            'fr' => 'Productivité',
        ],
        'slug' => 'productivity',
    ]);

    $name = $this->getJson('/api/categories')->assertOk()->json('0.name');

    // The frontend picks the value for the current locale (ADR-27), so every language
    // must arrive intact and decoded. Without the model's array cast this would be a
    // raw JSON *string* and the comparison below would fail.
    expect($name)->toBe([
        'bg' => 'Продуктивност',
        'en' => 'Productivity',
        'fr' => 'Productivité',
    ]);
});

test('a category with a missing translation returns only the translations it has', function () {
    Sanctum::actingAs(User::factory()->create());

    Category::create(['name' => ['bg' => 'Само на български'], 'slug' => 'bg-only']);

    $name = $this->getJson('/api/categories')->assertOk()->json('0.name');

    // No server-side substitution: the map is returned exactly as stored and the
    // frontend falls back to `bg`. A backend that quietly filled in the missing 'en'
    // would hide an untranslated category instead of surfacing it.
    expect($name)->toBe(['bg' => 'Само на български']);
    expect($name)->not->toHaveKey('en');
});

test('categories are ordered by slug ascending', function () {
    Sanctum::actingAs(User::factory()->create());

    // Inserted in neither slug order nor Bulgarian-name order, and the two orders differ
    // from each other, so this test fails if the order is insertion order (the old
    // orderBy('name') on a JSON column) as well as if it is alphabetical by name.
    Category::create(['name' => ['bg' => 'Я'], 'slug' => 'productivity']);
    Category::create(['name' => ['bg' => 'А'], 'slug' => 'writing']);
    Category::create(['name' => ['bg' => 'Б'], 'slug' => 'code-assistants']);

    $response = $this->getJson('/api/categories')->assertOk();

    expect($response->json('*.slug'))->toBe(['code-assistants', 'productivity', 'writing']);
});

// ROLES

test('an unauthenticated request to list roles is rejected', function () {
    $this->getJson('/api/roles')->assertStatus(401);
});

test('an authenticated user can list roles', function () {
    Sanctum::actingAs(User::factory()->create());

    Role::create(['name' => 'employee', 'display_name' => 'Employee', 'level' => 10]);
    Role::create(['name' => 'owner', 'display_name' => 'Owner', 'level' => 100]);

    $response = $this->getJson('/api/roles')->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json())->toHaveCount(2);

    foreach ($response->json() as $role) {
        expect(array_keys($role))->toBe(['id', 'name', 'display_name', 'level']);
    }
});

test('roles are ordered by level descending', function () {
    Sanctum::actingAs(User::factory()->create());

    Role::create(['name' => 'employee', 'display_name' => 'Employee', 'level' => 10]);
    Role::create(['name' => 'owner', 'display_name' => 'Owner', 'level' => 100]);
    Role::create(['name' => 'pm', 'display_name' => 'PM', 'level' => 60]);

    $response = $this->getJson('/api/roles')->assertOk();

    expect($response->json('*.name'))->toBe(['owner', 'pm', 'employee']);
});

// DEPARTMENTS

test('an unauthenticated request to list departments is rejected', function () {
    $this->getJson('/api/departments')->assertStatus(401);
});

test('an authenticated user can list departments', function () {
    Sanctum::actingAs(User::factory()->create());

    Department::create(['name' => 'Marketing', 'slug' => 'marketing']);
    Department::create(['name' => 'Sales', 'slug' => 'sales']);

    $response = $this->getJson('/api/departments')->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json())->toHaveCount(2);

    foreach ($response->json() as $department) {
        expect(array_keys($department))->toBe(['id', 'name', 'slug']);
    }
});

test('departments are ordered by name ascending', function () {
    Sanctum::actingAs(User::factory()->create());

    Department::create(['name' => 'Gamma', 'slug' => 'gamma']);
    Department::create(['name' => 'Alpha', 'slug' => 'alpha']);
    Department::create(['name' => 'Beta', 'slug' => 'beta']);

    $response = $this->getJson('/api/departments')->assertOk();

    expect($response->json('*.name'))->toBe(['Alpha', 'Beta', 'Gamma']);
});
