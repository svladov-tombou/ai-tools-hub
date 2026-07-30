<?php

use App\Models\Category;
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

    Category::create(['name' => 'Writing', 'slug' => 'writing']);
    Category::create(['name' => 'Coding', 'slug' => 'coding']);

    $response = $this->getJson('/api/categories')->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json())->toHaveCount(2);

    foreach ($response->json() as $category) {
        expect(array_keys($category))->toBe(['id', 'name', 'slug']);
    }
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
