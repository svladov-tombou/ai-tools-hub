<?php

use App\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function visibilityTool(array $overrides = []): Tool
{
    $createdBy = $overrides['created_by'] ?? null;
    unset($overrides['created_by']);

    $tool = Tool::create(array_merge([
        'name' => 'Visible Tool',
        'description' => 'A tool for visibility tests',
        'url' => 'https://example.com',
        'status' => 'published',
    ], $overrides));

    $tool->created_by = $createdBy;
    $tool->save();

    return $tool;
}

test('an employee sees every published tool plus their own draft, but not another users draft', function () {
    $employee = createUserWithRole('employee');
    $owner = createUserWithRole('owner');

    for ($i = 1; $i <= 8; $i++) {
        visibilityTool(['name' => "Published {$i}"]);
    }

    visibilityTool(['name' => 'My Draft', 'status' => 'draft', 'created_by' => $employee->id]);
    visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('data'))->toHaveCount(9);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('My Draft');
    expect($names)->not->toContain('Foreign Draft');
});

test('the paginator total counts only tools the caller may see', function () {
    $employee = createUserWithRole('employee');
    $owner = createUserWithRole('owner');

    for ($i = 1; $i <= 8; $i++) {
        visibilityTool(['name' => "Published {$i}"]);
    }

    visibilityTool(['name' => 'My Draft', 'status' => 'draft', 'created_by' => $employee->id]);
    visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('total'))->toBe(9);
});

test('a manager sees all ten tools including both drafts', function () {
    $manager = createUserWithRole('manager');
    $owner = createUserWithRole('owner');
    $employee = createUserWithRole('employee');

    for ($i = 1; $i <= 8; $i++) {
        visibilityTool(['name' => "Published {$i}"]);
    }

    visibilityTool(['name' => 'My Draft', 'status' => 'draft', 'created_by' => $employee->id]);
    visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('total'))->toBe(10);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('My Draft');
    expect($names)->toContain('Foreign Draft');
});

test('an owner sees all ten tools', function () {
    $actingOwner = createUserWithRole('owner');
    $pm = createUserWithRole('pm');
    $employee = createUserWithRole('employee');

    for ($i = 1; $i <= 8; $i++) {
        visibilityTool(['name' => "Published {$i}"]);
    }

    visibilityTool(['name' => 'My Draft', 'status' => 'draft', 'created_by' => $employee->id]);
    visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $pm->id]);

    Sanctum::actingAs($actingOwner);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('data'))->toHaveCount(10);
});

test('a draft with no author is hidden from an employee but visible to a manager', function () {
    $employee = createUserWithRole('employee');
    $manager = createUserWithRole('manager');

    visibilityTool(['name' => 'Published Tool']);
    visibilityTool(['name' => 'Authorless Draft', 'status' => 'draft', 'created_by' => null]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Published Tool');

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/tools')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('an employee gets 403 when fetching another users draft directly', function () {
    $owner = createUserWithRole('owner');
    $employee = createUserWithRole('employee');

    $tool = visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/tools/{$tool->id}")->assertForbidden();
});

test('a manager gets 200 for the same draft an employee is refused', function () {
    $owner = createUserWithRole('owner');
    $employee = createUserWithRole('employee');
    $manager = createUserWithRole('manager');

    $tool = visibilityTool(['name' => 'Foreign Draft', 'status' => 'draft', 'created_by' => $owner->id]);

    Sanctum::actingAs($employee);
    $this->getJson("/api/tools/{$tool->id}")->assertForbidden();

    Sanctum::actingAs($manager);
    $this->getJson("/api/tools/{$tool->id}")->assertOk();
});

test('an employee can fetch their own draft directly', function () {
    $employee = createUserWithRole('employee');

    $tool = visibilityTool(['name' => 'My Draft', 'status' => 'draft', 'created_by' => $employee->id]);

    Sanctum::actingAs($employee);

    $response = $this->getJson("/api/tools/{$tool->id}")->assertOk();

    expect($response->json('status'))->toBe('draft');
});

test('an employee gets 403 for an authorless draft fetched directly', function () {
    $employee = createUserWithRole('employee');

    $tool = visibilityTool(['name' => 'Authorless Draft', 'status' => 'draft', 'created_by' => null]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/tools/{$tool->id}")->assertForbidden();
});

test('any authenticated user can fetch a published tool directly', function () {
    $employee = createUserWithRole('employee');

    $tool = visibilityTool(['name' => 'Published Tool', 'created_by' => null]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/tools/{$tool->id}")->assertOk();
});
