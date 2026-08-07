<?php

/*
|--------------------------------------------------------------------------
| Every lang/<locale>/validation.php carries the SAME keys as bg's
|--------------------------------------------------------------------------
|
| ADR-50 (2) established this invariant by running a set diff once, by hand. This file is
| the standing version of that check: `bg` is the reference because it was written first
| and every other language was written from it.
|
| The two sections are compared independently. `rules` and `attributes` fail in different
| ways — a missing rule produces an English sentence, a missing attribute produces a
| sentence built around an English column name — and folding them into one comparison
| would report the second as if it were the first.
|
| The directory is walked rather than a list of locales being named here, so a fourth
| language is covered the moment its folder exists. That is the point: `docs/pitfalls.md`
| describes the locale list living in several places with nothing cross-checking them, and
| a check that itself needs updating per language would be one more of those places.
|
| No HTTP and no database: this asserts a property of two PHP files. The behaviour they
| produce over the wire is `ValidationLocaleTest`'s job.
|
*/

const PARITY_REFERENCE = 'bg';

/**
 * `lang/`, resolved from this file rather than through `lang_path()`.
 *
 * The helper needs a booted application, and the locale list below is read while Pest is
 * COLLECTING tests — before that happens. `lang_path()` there returns nothing, the dataset
 * comes out empty and Pest reports "no dataset(s) provided" instead of running anything.
 */
function parityLangPath(string $relative = ''): string
{
    return __DIR__.'/../../lang'.($relative === '' ? '' : "/{$relative}");
}

/**
 * Dot-flattens a message array to its leaf keys, so `min => ['array' => ...]` becomes
 * `min.array` — the form Laravel actually resolves and the form a human can grep for.
 *
 * Named for this file: Pest loads every test file into one process, so a bare `flatten`
 * would be a fatal redeclare the day another file wants one.
 */
function parityFlattenKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = array_merge($keys, parityFlattenKeys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

/** The two independent key sets of one language file, as ['rules' => [...], 'attributes' => [...]]. */
function parityKeySets(string $locale): array
{
    $messages = require parityLangPath("{$locale}/validation.php");

    // `attributes` is a field-name map, not a rule; pulled out before the rest is
    // flattened so `attributes.body` cannot be mistaken for a rule key called `body`.
    $attributes = parityFlattenKeys($messages['attributes'] ?? []);
    unset($messages['attributes']);

    return ['rules' => parityFlattenKeys($messages), 'attributes' => $attributes];
}

/** Every locale directory under `lang/` except the reference, in a stable order. */
function parityLocales(): array
{
    $locales = array_map(
        fn (string $path) => basename($path),
        glob(parityLangPath('*'), GLOB_ONLYDIR) ?: [],
    );

    sort($locales);

    return array_values(array_diff($locales, [PARITY_REFERENCE]));
}

it('finds at least one lang/ directory besides bg, or the parity test below compares nothing', function () {
    // Without this the test below is a loop over an empty list: it would pass with the
    // French file deleted, which is the one thing it must never do. `en` has no directory
    // — it is the framework's own language (ADR-49) — so today this is `fr` alone.
    //
    // The explanation lives in the test NAME rather than in an assertion message because
    // Pest truncates the message on `not->toBeEmpty` and prints the name in full.
    expect(parityLocales())->not->toBeEmpty();
});

it('carries the same validation keys as the reference language', function (string $locale) {
    $path = parityLangPath("{$locale}/validation.php");

    expect(file_exists($path))->toBeTrue(
        "lang/{$locale}/ exists but has no validation.php — every 422 in {$locale} falls back to English",
    );

    $reference = parityKeySets(PARITY_REFERENCE);
    $actual = parityKeySets($locale);

    // One list naming every key and the language it is missing from, rather than a diff of
    // two arrays the reader then has to line up by eye.
    $problems = [];

    foreach (['rules', 'attributes'] as $section) {
        foreach (array_diff($reference[$section], $actual[$section]) as $key) {
            $problems[] = "{$section}: `{$key}` is in ".PARITY_REFERENCE.", MISSING from {$locale}";
        }

        foreach (array_diff($actual[$section], $reference[$section]) as $key) {
            $problems[] = "{$section}: `{$key}` is in {$locale}, MISSING from ".PARITY_REFERENCE;
        }
    }

    expect($problems)->toBe([], sprintf(
        "lang/%s/validation.php and lang/%s/validation.php do not carry the same keys:\n%s",
        PARITY_REFERENCE,
        $locale,
        implode("\n", array_map(fn (string $line) => "  - {$line}", $problems)),
    ));
})->with(parityLocales());
