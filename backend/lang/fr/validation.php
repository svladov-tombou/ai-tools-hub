<?php

/*
|--------------------------------------------------------------------------
| French validation messages — PARTIAL on purpose (ADR-49)
|--------------------------------------------------------------------------
|
| A mirror of `lang/bg/validation.php`: the SAME 16 rules and the SAME 23 attribute
| names, because both files answer for the same eleven Form Requests. A key present in
| one and missing from the other is a bug in whichever file is shorter, and the sets are
| asserted programmatically rather than by eye (ADR-50).
|
| Anything not listed here resolves through `fallback_locale` to the framework's own
| English file in `vendor/`. That is the intended behaviour, and it is also the price:
| a NEW rule added to a Form Request silently produces an English sentence in a French
| form until somebody adds its key here. Nothing fails, nothing warns.
|
| The key SHAPES below were confirmed empirically for Bulgarian (ADR-49, Evidence) and
| are properties of the rules, not of the language:
|   - `Password::min(8)` reports under `min.string`, NOT under a password-specific key.
|   - `array:bg,en,fr` reports under `array` — the SAME key as a value that is not an
|     array at all — and the message never names the allowed keys. The wording therefore
|     has to be true of both cases.
|
| The two strings containing an apostrophe are double-quoted rather than backslash-
| escaped, following `CategorySeeder`'s "Génération d'images". Single quotes everywhere
| else, as in the Bulgarian file.
|
*/

return [

    'array' => 'Le champ :attribute doit être un tableau.',
    'confirmed' => 'Le champ :attribute ne correspond pas à la confirmation.',
    // No :attribute placeholder in the framework's own message either — the rule is only
    // ever applied to the current password, so naming the field adds nothing.
    'current_password' => 'Le mot de passe actuel est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    // Deliberately identical to `in`, exactly as in the Bulgarian file: both mean "this
    // id/value is not one we offer", and the difference between them (a database lookup
    // versus a fixed list) is an implementation detail the person filling in the form
    // cannot act on.
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'filled' => 'Le champ :attribute ne peut pas être vide.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',

    'max' => [
        // Only `.string` is used (max:255, max:2000, max:5000). The sibling forms
        // (.numeric, .array, .file) fall back to English, which is correct: adding them
        // would be translating rules this project does not have.
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],

    'min' => [
        // ":min élément(s)" rather than ":min éléments": Laravel has no pluralisation in
        // validation messages, and `min:1` — the only value used — would otherwise read
        // "au moins 1 éléments".
        'array' => 'Le champ :attribute doit contenir au moins :min élément(s).',
        // This is where Password::min(8) lands. Always ≥ 2 here, so "caractères" is safe.
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],

    'prohibited' => "Le champ :attribute n'est pas autorisé dans cette requête.",
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Le champ :attribute est déjà utilisé.',
    'url' => 'Le champ :attribute doit être une adresse (URL) valide.',

    /*
    |--------------------------------------------------------------------------
    | Custom validation attributes
    |--------------------------------------------------------------------------
    |
    | Without these every message reads "Le champ body est obligatoire" — a French
    | sentence with an English noun in the middle of it. Every field name that appears
    | in any of the eleven Form Requests is listed, including the array element forms
    | (`role_ids.*`), which Laravel resolves through the `.*` key.
    |
    | Lower case throughout, because each one is substituted INTO a sentence.
    |
    | The Bulgarian file takes its wording from the labels the forms already show
    | (`messages/bg/common.json`). There is no `messages/fr/` yet — the frontend learns
    | about French in a later part (ADR-50) — so these follow the MEANING of those
    | labels. When the French dictionary is written, the two have to be read together.
    |
    */

    'attributes' => [
        'body' => 'commentaire',
        'category_ids' => 'catégories',
        'category_ids.*' => 'catégorie',
        'current_password' => 'mot de passe actuel',
        'department_id' => 'service',
        'department_ids' => 'services',
        'department_ids.*' => 'service',
        'description' => 'description',
        'difficulty' => 'difficulté',
        'documentation_url' => 'lien vers la documentation',
        'email' => 'adresse e-mail',
        'name' => 'nom',
        'name.bg' => 'nom (bulgare)',
        'name.en' => 'nom (anglais)',
        'name.fr' => 'nom (français)',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'role_ids' => 'rôles',
        'role_ids.*' => 'rôle',
        // Left untranslated on purpose: `slug` is the wire vocabulary (ADR-26) and the
        // admin form labels the field "Slug" too. A French noun here would name
        // something the user cannot find on the screen.
        'slug' => 'slug',
        'status' => 'statut',
        'url' => "adresse de l'outil",
        'video_url' => 'lien vers la vidéo',
    ],

];
