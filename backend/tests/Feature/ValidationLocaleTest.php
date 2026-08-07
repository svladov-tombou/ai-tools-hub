<?php

use App\Models\Category;
use App\Models\Role;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Authenticates as a freshly created holder of the role. Named for this file: Pest loads
 * every test file into one process, so a second `actingAsRole` would be a fatal redeclare.
 */
function localeActingAs(string $roleName): User
{
    $user = createUserWithRole($roleName);

    Sanctum::actingAs($user);

    return $user;
}

/** A published tool, the shape the other tool test files use — `Tool` has no factory. */
function localeTool(): Tool
{
    return Tool::create([
        'name' => 'Locale Tool',
        'description' => 'A tool for locale tests',
        'url' => 'https://example.com',
        'status' => 'published',
    ]);
}

/**
 * A payload that satisfies every rule in StoreUserRequest, so a test can break exactly one.
 */
function validUserPayload(array $overrides = []): array
{
    $employeeRoleId = Role::firstOrCreate(
        ['name' => 'employee'],
        ['display_name' => 'Employee', 'level' => 20],
    )->id;

    return array_merge([
        'name' => 'Нов потребител',
        'email' => 'new@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_ids' => [$employeeRoleId],
        'department_id' => null,
    ], $overrides);
}

/**
 * One row per validation rule the application actually uses (ADR-49). Each closure runs
 * INSIDE the test, after the database has been refreshed; it authenticates, creates whatever
 * the endpoint needs, and returns the request to send.
 *
 * These go out as real HTTP requests through the whole middleware stack on purpose. A direct
 * `Validator::make()` would exercise the translations but not the middleware that decides
 * which language they come out in, and that middleware is half of what this phase built.
 */
dataset('rules', [
    'required' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/tools', ['description' => 'x', 'url' => 'https://a.test']];
        },
        'name',
        'Полето име е задължително.',
    ],
    'string' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/tools', ['name' => 5, 'description' => 'x', 'url' => 'https://a.test']];
        },
        'name',
        'Полето име трябва да е текст.',
    ],
    'max (string)' => [
        function () {
            localeActingAs('owner');
            $tool = localeTool();

            return ['post', "/api/tools/{$tool->id}/comments", ['body' => str_repeat('щ', 2001)]];
        },
        'body',
        'Полето коментар не може да е по-дълго от 2000 символа.',
    ],
    'min (string) — where Password::min(8) lands' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/users', validUserPayload([
                'password' => 'къса',
                'password_confirmation' => 'къса',
            ])];
        },
        'password',
        'Полето парола трябва да е поне 8 символа.',
    ],
    'email' => [
        fn () => ['post', '/api/login', ['email' => 'не-е-имейл', 'password' => 'password']],
        'email',
        'Полето имейл трябва да съдържа валиден имейл адрес.',
    ],
    'url' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/tools', ['name' => 'A', 'description' => 'x', 'url' => 'не-е-адрес']];
        },
        'url',
        'Полето адрес на инструмента трябва да съдържа валиден адрес (URL).',
    ],
    'integer' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/users', validUserPayload(['department_id' => 'abc'])];
        },
        'department_id',
        'Полето отдел трябва да е цяло число.',
    ],
    'array' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/categories', ['name' => 'плосък низ', 'slug' => 'flat']];
        },
        'name',
        'Полето име трябва да е масив.',
    ],
    'array — a disallowed key reports under the SAME rule' => [
        function () {
            localeActingAs('owner');

            // `array:bg,en,fr` with an unknown key `de`. Proven in ADR-49's Evidence to
            // report under `validation.array`, exactly like a value that is not an array at
            // all — which is why the wording has to be true of both.
            return ['post', '/api/categories', [
                'name' => ['bg' => 'Писане', 'de' => 'Schreiben'],
                'slug' => 'writing',
            ]];
        },
        'name',
        'Полето име трябва да е масив.',
    ],
    'filled' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/categories', [
                'name' => ['bg' => 'Писане', 'en' => ''],
                'slug' => 'writing',
            ]];
        },
        'name.en',
        'Полето име (английски) не може да е празно.',
    ],
    'in' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/tools', [
                'name' => 'A',
                'description' => 'x',
                'url' => 'https://a.test',
                'difficulty' => 'много-трудно',
            ]];
        },
        'difficulty',
        'Избраната стойност за трудност е невалидна.',
    ],
    'exists' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/tools', [
                'name' => 'A',
                'description' => 'x',
                'url' => 'https://a.test',
                'category_ids' => [999999],
            ]];
        },
        'category_ids.0',
        'Избраната стойност за категория е невалидна.',
    ],
    'unique' => [
        function () {
            localeActingAs('owner');
            $existing = User::factory()->create(['email' => 'taken@example.test']);

            return ['post', '/api/users', validUserPayload(['email' => $existing->email])];
        },
        'email',
        'Полето имейл вече се използва.',
    ],
    'confirmed' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/users', validUserPayload(['password_confirmation' => 'нещо-друго'])];
        },
        'password',
        'Полето парола не съвпада с потвърждението.',
    ],
    'prohibited' => [
        function () {
            localeActingAs('owner');
            $category = Category::create(['name' => ['bg' => 'Писане'], 'slug' => 'writing']);

            return ['put', "/api/categories/{$category->id}", [
                'name' => ['bg' => 'Писане'],
                'slug' => 'ново-име',
            ]];
        },
        'slug',
        'Полето slug не е разрешено в тази заявка.',
    ],
    'current_password' => [
        function () {
            // The factory's password is `password`; anything else is the wrong one.
            localeActingAs('employee');

            return ['put', '/api/user/password', [
                'current_password' => 'грешна-парола',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ]];
        },
        'current_password',
        'Текущата парола е грешна.',
    ],
    'regex' => [
        function () {
            localeActingAs('owner');

            return ['post', '/api/categories', [
                'name' => ['bg' => 'Писане'],
                'slug' => 'Не Е Slug',
            ]];
        },
        'slug',
        'Полето slug е в невалиден формат.',
    ],
]);

it('answers 422 in Bulgarian when the request asks for Bulgarian', function (Closure $case, string $field, string $expected) {
    [$method, $path, $payload] = $case();

    $response = $this->withHeader('Accept-Language', 'bg')->json($method, $path, $payload);

    $response->assertStatus(422);

    // Read out of the decoded array rather than through assertJsonPath: two of the field
    // names contain a dot (`name.en`, `category_ids.0`) and dot-path lookup would read them
    // as nesting. `toContain` is a strict comparison, so this asserts the EXACT sentence,
    // not a substring of it.
    $errors = $response->json('errors');

    expect(array_key_exists($field, $errors))->toBeTrue("no 422 errors reported for `{$field}`");
    expect($errors[$field])->toContain($expected);
})->with('rules');

it('names the field in Bulgarian rather than by its wire name', function () {
    localeActingAs('owner');
    $tool = localeTool();

    // The whole point of the `attributes` block. Without it this reads "Полето body е
    // задължително" — a Bulgarian sentence built around an English noun.
    $this->withHeader('Accept-Language', 'bg')
        ->postJson("/api/tools/{$tool->id}/comments", [])
        ->assertStatus(422)
        ->assertJsonPath('errors.body.0', 'Полето коментар е задължително.')
        // `message` is Laravel's first error message, so it is translated for free. The
        // frontend renders `errors` and not `message` today; this pins it anyway, because
        // the two must not be able to disagree about language.
        ->assertJsonPath('message', 'Полето коментар е задължително.');
});

it('answers in English when the request asks for English', function () {
    localeActingAs('owner');

    $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('falls back to the configured default for a language it does not support', function () {
    localeActingAs('owner');

    // `de` is not on the whitelist. English here is the assertion that matters: `bg` is
    // FIRST in the whitelist, so an implementation that answered with "the best supported
    // match" (Symfony's getPreferredLanguage does exactly that) would answer in Bulgarian.
    // It must not, and it must not 500 either.
    $this->withHeader('Accept-Language', 'de')
        ->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');

    expect(App::getLocale())->toBe(config('app.locale'));
});

it('leaves an already-configured default alone for an unsupported language', function () {
    localeActingAs('owner');
    // The other direction of the same rule: an unsupported language must not RESET the
    // locale either. With the application configured for Bulgarian, `de` stays Bulgarian.
    App::setLocale('bg');

    $this->withHeader('Accept-Language', 'de')
        ->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'Полето име е задължително.');
});

it('falls back to the configured default when no language is asked for', function () {
    localeActingAs('owner');

    $this->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('honours q-values and ignores the region subtag', function () {
    localeActingAs('owner');

    // What a browser actually sends. `bg-BG` has to resolve to `bg`, and `en;q=0.8` must
    // not win over it.
    $this->withHeader('Accept-Language', 'bg-BG,bg;q=0.9,en;q=0.8')
        ->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'Полето име е задължително.');
});

it('resolves a region-only tag with no bare language behind it', function () {
    localeActingAs('owner');

    // This is the test that actually covers `explode('_', $language)[0]` in SetLocale, and
    // the one above is NOT: `bg-BG,bg;q=0.9,en;q=0.8` parses to ["bg_BG", "bg", "en"], so
    // the bare `bg` on the second pass would match even with the region left on. Here
    // Symfony returns ["bg_BG"] and nothing else — drop the suffix-stripping line and this
    // request falls back to English.
    $this->withHeader('Accept-Language', 'bg-BG')
        ->postJson('/api/tools', ['description' => 'x', 'url' => 'https://a.test'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'Полето име е задължително.');
});

it('translates min on an array, which no endpoint can currently reach', function () {
    // `role_ids => ['required', 'array', 'min:1']` never reports `min` over HTTP: an empty
    // array fails `required` first and Laravel stops validating that attribute there. The
    // key is translated anyway, so it is asserted here rather than shipped untested.
    App::setLocale('bg');

    $errors = Validator::make(['role_ids' => []], ['role_ids' => ['array', 'min:1']])->errors();

    expect($errors->first('role_ids'))->toBe('Полето роли трябва да съдържа поне 1 елемент(а).');
});

it('leaves an untranslated rule in English instead of failing', function () {
    // The documented price of a PARTIAL translation (ADR-49). `boolean` is not in
    // lang/bg/validation.php, so it resolves through fallback_locale — quietly, in English.
    // This test exists so that behaviour is a decision on record rather than a surprise.
    App::setLocale('bg');

    $errors = Validator::make(['flag' => 'nope'], ['flag' => ['boolean']])->errors();

    expect($errors->first('flag'))->toBe('The flag field must be true or false.');
});
