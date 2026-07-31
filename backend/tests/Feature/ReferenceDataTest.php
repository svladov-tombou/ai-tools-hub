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
