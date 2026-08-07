<?php

/*
|--------------------------------------------------------------------------
| Bulgarian validation messages — PARTIAL on purpose (ADR-49)
|--------------------------------------------------------------------------
|
| This file translates ONLY the rules the application actually uses. It was written by
| hand rather than produced by `artisan lang:publish`: a full copy of Laravel's file is
| ~110 rules, of which this project uses 16, and the other ~94 would be dead text that
| still has to be re-read on every framework upgrade.
|
| Anything not listed here resolves through `fallback_locale` to the framework's own
| English file in `vendor/`. That is the intended behaviour, and it is also the price:
| a NEW rule added to a Form Request silently produces an English sentence in a
| Bulgarian form until somebody adds its key here. Nothing fails, nothing warns.
|
| The keys below were confirmed empirically, not from memory (ADR-49, Evidence):
|   - `Password::min(8)` reports under `min.string`, NOT under a password-specific key.
|   - `array:bg,en,fr` reports under `array` — the SAME key as a value that is not an
|     array at all — and the message never names the allowed keys. The wording therefore
|     has to be true of both cases.
|
*/

return [

    'array' => 'Полето :attribute трябва да е масив.',
    'confirmed' => 'Полето :attribute не съвпада с потвърждението.',
    // No :attribute placeholder in the framework's own message either — the rule is only
    // ever applied to the current password, so naming the field adds nothing.
    'current_password' => 'Текущата парола е грешна.',
    'email' => 'Полето :attribute трябва да съдържа валиден имейл адрес.',
    // Deliberately identical to `in`: both mean "this id/value is not one we offer", and
    // the difference between them (a database lookup versus a fixed list) is an
    // implementation detail the person filling in the form cannot act on.
    'exists' => 'Избраната стойност за :attribute е невалидна.',
    'filled' => 'Полето :attribute не може да е празно.',
    'in' => 'Избраната стойност за :attribute е невалидна.',
    'integer' => 'Полето :attribute трябва да е цяло число.',

    'max' => [
        // Only `.string` is used (max:255, max:2000, max:5000). The sibling forms
        // (.numeric, .array, .file) fall back to English, which is correct: adding them
        // would be translating rules this project does not have.
        'string' => 'Полето :attribute не може да е по-дълго от :max символа.',
    ],

    'min' => [
        // ":min елемент(а)" rather than ":min елемента": Laravel has no pluralisation in
        // validation messages, and `min:1` — the only value used — would otherwise read
        // "поне 1 елемента".
        'array' => 'Полето :attribute трябва да съдържа поне :min елемент(а).',
        // This is where Password::min(8) lands. Always ≥ 2 here, so "символа" is safe.
        'string' => 'Полето :attribute трябва да е поне :min символа.',
    ],

    'prohibited' => 'Полето :attribute не е разрешено в тази заявка.',
    'regex' => 'Полето :attribute е в невалиден формат.',
    'required' => 'Полето :attribute е задължително.',
    'string' => 'Полето :attribute трябва да е текст.',
    'unique' => 'Полето :attribute вече се използва.',
    'url' => 'Полето :attribute трябва да съдържа валиден адрес (URL).',

    /*
    |--------------------------------------------------------------------------
    | Custom validation attributes
    |--------------------------------------------------------------------------
    |
    | Without these every message reads "Полето body е задължително" — a Bulgarian
    | sentence with an English noun in the middle of it. Every field name that appears
    | in any of the eleven Form Requests is listed, including the array element forms
    | (`role_ids.*`), which Laravel resolves through the `.*` key.
    |
    | Lower case throughout, because each one is substituted INTO a sentence. The
    | wording follows the labels the forms already show (messages/bg/common.json), so
    | the error under an input names the input the way its own label does.
    |
    */

    'attributes' => [
        'body' => 'коментар',
        'category_ids' => 'категории',
        'category_ids.*' => 'категория',
        'current_password' => 'текуща парола',
        'department_id' => 'отдел',
        'department_ids' => 'отдели',
        'department_ids.*' => 'отдел',
        'description' => 'описание',
        'difficulty' => 'трудност',
        'documentation_url' => 'линк към документация',
        'email' => 'имейл',
        'name' => 'име',
        'name.bg' => 'име (български)',
        'name.en' => 'име (английски)',
        'name.fr' => 'име (френски)',
        'password' => 'парола',
        'password_confirmation' => 'потвърждение на паролата',
        'role_ids' => 'роли',
        'role_ids.*' => 'роля',
        // Left untranslated on purpose: `slug` is the wire vocabulary (ADR-26) and the
        // admin form labels the field "Slug" too. A Bulgarian noun here would name
        // something the user cannot find on the screen.
        'slug' => 'slug',
        'status' => 'статус',
        'url' => 'адрес на инструмента',
        'video_url' => 'линк към видео',
    ],

];
