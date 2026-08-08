# Project pitfalls

Hard-won, project-specific traps. Read before touching the frontend, migrations, or seeders.
Each entry cost real debugging time. Append new ones as they are found; do not delete.

## Frontend

**Navigation imports.** `useRouter`, `usePathname`, `Link`, `redirect` come ONLY from
`@/i18n/navigation`. `useSearchParams` comes from `next/navigation` — it is not
locale-aware, and that is correct. Mixing these silently breaks locale routing.

**The middleware file is `src/proxy.ts`, not `middleware.ts`.** Do not create `middleware.ts`.

**Colors.** `@theme inline` BREAKS dark mode. Semantic tokens live in the non-inline `@theme`.
Two dangerously similar names: `text-secondary` is PEACH; `text-text-secondary` is grey
secondary text. Picking the wrong one looks like a design bug, not a typo.

**Tailwind is invisible to tsc and lint.** An unknown class is just a string; nothing errors.
Verify visually. NEVER build a class name dynamically (grid-cols-${n}) — Tailwind scans
source text and will not generate it. Use a lookup table of static strings.

**Types live in two places:** `src/types.ts` and `src/types/`. Before adding a type, check
which one the surrounding code uses. Do not introduce a third convention.

**No data-fetching libraries.** No SWR, no react-query. Everything goes through `src/lib/api.ts`.

**Server components cannot fetch data.** Auth is a Bearer token in localStorage (ADR-6), so
every authenticated request is client-side. This is why the `/tools/[id]/edit` heading lives
in the client component, not the page.

**Panel/modal state must hold an ID, not a copy of the row.** Storing the object freezes it at the
moment the panel opened, so after a save + reload the panel renders the OLD values while the list
beneath shows the new ones — a heading that keeps announcing the previous name. Store the id and
`find()` it in the freshly loaded list on each render. Note the `key` for the ADR-24 remount then
comes from the id, which is what you want: it stays stable across reloads and changes only when you
switch rows.

**`ToolPayload` has NO optional fields.** Nullables are `| null`, never `?`. Deliberate: the
controller uses `->safe()`, so a MISSING field is not updated. Clear a documentation link and
omit the field, and the old value silently survives. The type forces an explicit null.

## i18n

`messages/bg/common.json` is the TYPE SOURCE for `t()`. A new key goes there FIRST or `tsc` fails.
Every OTHER dictionary is unchecked by the compiler — keep each one in sync with `bg` by hand,
using the script in "Useful commands" below.
A template key using a backtick string compiles ONLY if the interpolated value is a union of
existing keys. Never fix such an error with `as never` or eslint-disable — that error is the
check working.

**Backend validation messages are a PARTIAL translation (ADR-49).** `backend/lang/bg/validation.php`
covers only the 16 rules the Form Requests actually use. Add a rule that is not in that file and its
message comes out in ENGLISH inside a Bulgarian form — quietly, through `fallback_locale`. Nothing
fails: not `tsc`, not `sail artisan test`, not the browser. Adding a validation rule therefore has a
second step — add its key to `lang/bg/validation.php`, and its field name to the `attributes` block
in the same file, or the sentence reads "Полето body е задължително".
Which key a rule reports under is not always its name: `Password::min(8)` reports under `min.string`,
and `array:bg,en,fr` reports under plain `array` whether the value is not an array or merely has a
disallowed key. Prove the key with a failing request before writing the translation.

**Adding a LANGUAGE touches TEN files, and only ONE of them fails loudly.** French is done; this
is about the NEXT one. Four files carry the UI-language list and six more carry the category-name
map, all declared independently, with nothing cross-checking them.

The UI language, numbered in the ORDER THE WORK HAS TO HAPPEN — which is not the same question as
which one fails loudly:

1. `frontend/messages/<locale>/common.json` — the dictionary. FIRST, per ADR-51 (1), because doing
   it second breaks the build (see 2). SILENT, and not in the way it looks. `bg` is the TYPE
   SOURCE, but it is the type source for `bg` ALONE: `src/types/next-intl.d.ts` imports
   `../../messages/bg/common.json` and nothing else, and `i18n/request.ts` loads the reader's
   dictionary through a template literal — `import(\`../../messages/${locale}/common.json\`)` —
   which TypeScript cannot resolve to a type. A key missing from a non-`bg` dictionary is therefore
   NOT a `tsc` error. It surfaces at runtime as the key path rendered in place of the sentence.
   Guarded only by the sync script in "Useful commands", which nothing runs for you; and it compares
   the dictionaries to each other, so a key missing from ALL of them reports "in sync".
2. `frontend/src/i18n/routing.ts` — `locales`. SECOND, and THE LOUD ONE. `src/app/[locale]/layout.tsx`
   prerenders every entry through `generateStaticParams`, so a locale listed here without its
   dictionary fails `npm run build` itself — not the first request to `/<locale>`. That is the whole
   reason for the order: done this way round, every intermediate state of the work still builds.
3. `backend/lang/<locale>/validation.php` — the validation translations, PARTIAL per ADR-49.
   Guarded by `backend/tests/Feature/ValidationLanguageParityTest.php`, but read what that test
   actually covers: it globs the DIRECTORIES under `lang/` and asserts each one carries `bg`'s key
   set, in `rules` and `attributes` separately. It catches an INCOMPLETE language file, never an
   ABSENT language — a locale with no `lang/<locale>/` directory is not in the dataset and is never
   compared to anything. Its one guard against a vacuous run asserts merely that SOME non-`bg`
   directory exists, which `fr` satisfies forever. (`en` deliberately has no directory: it is the
   framework's own language.) A directory that exists with no `validation.php` in it does fail, by name.
4. `backend/app/Http/Middleware/SetLocale.php` — `SUPPORTED`. **NOTHING guards this one.** No test,
   no script, no compiler. It is the only one of the four with no check at all behind it.

Then the category names, which live in the DATABASE (ADR-27) and are a second list of the same
languages written out six more times, all silent: `LocalizedName` in `frontend/src/types.ts`,
`CategoryNamePayload` in `frontend/src/lib/api.ts`, `toCategoryName` in
`frontend/src/lib/category-name.ts`, and `CategoryFormValues` + the `fields` array + a
`settings.categories.name<X>` key in `frontend/src/components/category-form.tsx`; then `array:bg,en,fr`
and the `name.<locale>` rules in BOTH `StoreCategoryRequest` and `UpdateCategoryRequest`. Miss the
frontend ones and the new language cannot be typed into the category form; miss the backend ones and
saving one is a 422 naming a key the admin can see on screen.

Do only 1 and 2 and the interface LOOKS finished: the routes, the navigation and every label render
in the new language. Two things are still wrong and neither announces itself. `SUPPORTED` rejects the
locale, so the request falls back to the configured default and every 422 comes back in ENGLISH under
a translated form. And the category names come back in BULGARIAN — `localizedName` ends in
`?? name.bg`, so an untranslated row is indistinguishable from a translated one. Neither of these
showed when French was added: ADR-27 had carried a `fr` slot in the name map since it was written, and
ADR-50 put `fr` in `SUPPORTED` a whole phase early. A NEW language inherits neither head start.
Nothing throws, `tsc` is clean, `npm run build` is clean and the whole Pest suite stays green.

If a UI in some language is showing English validation errors, look at `SetLocale::SUPPORTED` first:
adding `lang/<locale>/` without adding it to the whitelist is the same silent failure the other way round.

## Backend

**Two alphabets.** Writing wants IDs: `category_ids`, `role_ids`, `department_ids`.
Filtering wants names: category → slug, department → slug, role → NAME. Mixing these
produces empty results with no error.

**Pivot tables:** `category_tool`, `role_tool`, `department_tool` — alphabetical, singular,
no timestamps. The reversed form (`tool_category`, `tool_role`, `tool_department`) has now been
written twice in prose, and NOTHING in the project catches it: Laravel assembles the pivot name
itself from the two model names, so the relations keep working, `tsc` never sees a table name and
the suite stays green. The wrong name only bites where a table is typed by hand — a raw
`DB::table('tool_category')`, a migration, a manual query. Order the two model names alphabetically
before typing one, and confirm a live FK against
`information_schema.REFERENTIAL_CONSTRAINTS` rather than against the migration file when the
delete behaviour matters (ADR-41).

**Seeder order in `DatabaseSeeder` is a dependency declaration**, not a preference:
RoleSeeder → UserSeeder → CategorySeeder → DepartmentSeeder → ToolSeeder.
Running ToolSeeder before DepartmentSeeder creates tools with NO departments and NO error.

**`created_by` is deliberately OUTSIDE `$fillable`** (anti-tampering) and set server-side.
A test caught that it was silently null, which made the whole ADR-12 rule dead code.

**On update, use `sync()`** (replaces), not `syncWithoutDetaching()` (only adds).

**`select()` must come BEFORE `withCount()`.** `withCount` sets the query's select list, after which
the column array passed to `get([...])` is silently ignored and the response widens to every column,
timestamps included. Reversed — `withCount()` then `select([...])` — the subquery is dropped instead
and the `*_count` field vanishes. Both failures are silent. Correct:
`Model::select([...])->withCount('rel')->get()`.

**Laravel 13's base `Controller` does NOT include `AuthorizesRequests`** — it was added manually.

**A seeder keyed on a DERIVED value is keyed on the value it derives from.** `CategorySeeder`
used `updateOrCreate(['slug' => $slug], ...)` — idempotent-looking — but the slug came from
`Str::slug($name)`. Renaming the categories would have changed every key: five NEW rows, five
orphans still holding all 18 `category_tool` pivot rows, and `ToolSeeder` (which attaches by
hardcoded English slug) silently attaching nothing. Fixed by an explicit `slug => name` map,
like `DepartmentSeeder`. Before changing seed data, ask what the key is derived FROM.

**Prove a rename in place; do not assume it.** Snapshot ids, slugs and pivot counts BEFORE and
AFTER the seed and compare. "The names look right in the UI" is also true when five new rows
were created and the old ones are sitting there orphaned.

**`sail artisan` includes destructive commands.** `migrate:fresh` WIPES the database and kills
every token (`personal_access_tokens` is recreated) → everyone must log in again. `db:seed`
only adds. Tests use a SEPARATE database (`phpunit.xml`), which is why `RefreshDatabase` is safe.

**Tests run on MySQL, not SQLite.** `phpunit.xml` overrides `DB_DATABASE` (`testing`) but NOT
`DB_CONNECTION`. So MySQL-specific raw SQL in a migration is safe — and, conversely, nothing
protects you from writing SQL that only MySQL understands.

**A column default is a DATABASE default; Eloquent never reads it back after an insert.** After
`$table->boolean('is_active')->default(true)`, a freshly created model carries `is_active = null`
in memory while the stored row says 1 — and `null` is falsy, so `! $user->is_active` reads a
brand-new user as DEACTIVATED, and a create endpoint returns `"is_active": null` against a row that
says true. The row is fine; the instance is not. Fix it at the cause with
`protected $attributes = ['is_active' => true]`, never by calling `->fresh()` in the test.
Model default and column default are TWO guarantees: assert them separately, the model one through
the factory and the column one through a raw `DB::table()->insert()` that bypasses the model.
Either assertion alone passes with the other mechanism missing.

**`User`'s `#[Fillable]` is `name, email, password` — nothing else.** `department_id` and
`is_active` are outside it on purpose, so `User::create([... 'department_id' => 3])` and
`$user->update([...])` **silently drop them**. Set them by direct property assignment before
`save()`. No error, no warning; the field simply never lands.

**A boolean column needs its cast or it serializes as `1`/`0`.** MySQL returns a tinyint, so without
`'is_active' => 'boolean'` in `casts()` the JSON carries integers and a frontend `boolean` type is a
lie. `expect(...)->toBeTrue()` fails on `1`, which is what makes it a real test; `assertJson` would
not catch it.

## JSON columns (`categories.name`, ADR-27)

**`orderBy()` on a JSON column does not sort — and does not complain.** `orderBy('name')` on the
JSON `name` returned rows in INSERTION order. No error, no warning; the list is simply wrong in a
way that looks like "the seeder ran in that order". Sort by an extracted path
(`name->>'$.bg'`) or by another column. Proven by making the test go red on purpose.

**MySQL cannot put a UNIQUE index on a JSON column.** Converting a unique string column to JSON
means dropping that index first (`ALTER TABLE ... DROP INDEX categories_name_unique`). A
generated column + unique index is the workaround; it was judged not worth it here.

**Converting VARCHAR → JSON in place: widen to TEXT first.** `JSON_OBJECT('bg', name)` adds ~10
characters, so wrapping a value near the `VARCHAR(255)` ceiling truncates it silently and the
following `MODIFY ... JSON` then fails on unparseable text. Order:
drop index → `MODIFY name TEXT` → `UPDATE ... JSON_OBJECT` → `MODIFY name JSON NOT NULL`.
It cannot be done in one `Schema::table()` call — the contents must be rewritten between the two
type changes. In `down()`, extract through a TEMPORARY column: assigning
`JSON_UNQUOTE(JSON_EXTRACT(...))` back into the JSON column stores a quoted JSON string scalar,
so the reverted VARCHAR would contain the quotes.

**Use a nowdoc (`<<<'SQL'`) for SQL containing a JSON path.** `'$.bg'` inside a double-quoted PHP
string is asking for trouble, and single quotes cannot nest with MySQL's.

**Forgetting the model's array cast fails far from the cause.** `Category::create(['name' => [...]])`
without `'name' => 'array'` in `casts()` throws
`Grammar::parameterize(): Argument #1 ($values) must be of type array, string given` from deep
inside the query builder. The message names the grammar, not the missing cast.

**A tie on `created_at` is not the nondeterminism you expect, and the test for it lies.** `timestamps()`
stores WHOLE SECONDS, so two rows written in the same second tie — but MySQL 8.4 does not then return them
in a random order. Measured 2026-08-07 on `comments`: with an index on `(tool_id, created_at)` and
`ORDER BY created_at DESC`, twelve tied rows came back id-DESCENDING with and without an explicit
`orderByDesc('id')`, byte for byte the same list. The index is scanned backwards and the primary key is
its last part, so the tiebreaker is already there implicitly. The consequence for testing is the trap:
**deleting the tiebreaker leaves the suite green**, so a test asserting the order does NOT prove the
tiebreaker is doing anything, and someone "simplifying" the query later gets no warning. Reversing it to
ascending does go red, which is the most such a test can be worth. Keep the explicit term — the implicit
one depends on which plan the optimizer picks, and under pagination an unstable tie makes a row repeat on
page 2 or vanish between pages — and write down in the test what it does and does not catch. General form:
before claiming a test proves an ordering guarantee, DELETE the guarantee and watch it fail. If it does
not fail, the test is describing the database's habits, not your code.

## Authorization

**A Form Request's `authorize()` runs BEFORE `rules()`.** Where the check lives decides what an
unauthorized user sees. In the Form Request: 403 whatever the payload. In the controller body
(`$this->authorize(...)`): validation fires first, so a manager sending junk gets a **422 listing
the validation rules** and only a valid payload gets 403. Neither writes anything, but only the
first keeps the rules private. `CategoryController` uses the Form Request; `ToolController` uses
the controller body. Decide deliberately, and write the test that tells them apart — a non-admin
sending an INVALID payload.

**`get(['id', 'slug'])` returns null for every column you did not select.** A snapshot query that
prints `name` after selecting only id and slug shows `null` for all five rows — which reads exactly
like "the migration wiped the names". The data was fine; the query was. Snapshots are the tool used
to prove a rename in place, so a broken snapshot is worse than none.

## Process gotchas

**1. The chain with a missing middle — the most expensive bug so far.** Both ends exist, the
middle does not. `tsc` and `lint` stay SILENT, because an optional parameter you never pass is
valid TypeScript. When adding a parameter, trace the WHOLE path: who writes it, who reads it,
who passes it, and which `useEffect` dependency arrays it belongs in. Verify with grep — the
number of hits must match what you expect.

**2. A test that does not discriminate is worthless.** Choose cases where right and wrong
answers differ. Good: the boundary pair 5000 passes / 5001 fails (a 10,000-char test would also
pass against `max:9999`). Good: test editing by REMOVING a role, not adding one — a form that
only sends additions passes an add-only test unchanged.

**3. The terminal truncates output.** Never judge by a clipped result — `cat` the real file.

**3a. A Playwright click that "ran" is not a click that landed.** The login form is React-controlled;
filling it right after `page.goto` can land before hydration, and then the button click does nothing:
no navigation, no error message, typed text silently reset to empty. It reads exactly like a broken
login. **The decisive signal is the network panel — zero `/api/` requests means the handler never
fired**, so the bug is in the interaction, not the app. Confirm the app is innocent out-of-band
(`curl` the same login) before debugging code that is fine. To get past it: seed
`localStorage.auth_token` with a token minted by `curl` and navigate — the token flow is already
covered elsewhere, so re-driving the form proves nothing new.

**3b. The test client keeps the authenticated user BETWEEN requests inside one test.** A second
`$this->withHeader('Authorization', "Bearer ...")` call does not re-resolve the guard: it answers as
whoever the FIRST request authenticated. So a "this revoked token no longer works" assertion returns
**200 and the app is innocent** — the request never looked at the token. Symptom shape is identical to
a real security hole, so confirm out-of-band with `curl` before touching the code (it went
200 → deactivate → 401 live, exactly as intended). Fix inside the test with
`$this->app['auth']->forgetGuards();` between the two requests. Note `Sanctum::actingAs()` sets the
guard's user directly and would mask the Bearer header entirely — use REAL tokens
(`->createToken('t')->plainTextToken`) whenever the token itself is what is under test.

**3c. To prove something is ABSENT in the browser, assert against RENDERED text, not document text.**
next-intl serialises the whole message catalogue into the RSC payload, so every dictionary string sits
in a `<script>` in the document regardless of what renders. `document.body.textContent.includes("…")`
therefore returns **true for a correctly hidden element** — a false positive that reads exactly like a
conditional that failed to hide. Real case: a creator row was properly omitted for an authorless tool
and the check still said the text was on the page; the single match was the serialised dictionary.
Use `document.body.innerText`, which returns only what is visible, or narrow the query to the element
you actually mean (`card.querySelector(...)`). The same trap applies to any string that lives in
`messages/*/common.json` — which is every user-facing string in this project.

**3d. On this machine the MCP browser driver's clicks never reach React's handlers.** `browser_click`
reports full success — element visible, enabled, stable, "click action done", no error — and the handler
simply does not run: no state change, no `/api/` request, and a `window.confirm` stubbed to record its
calls is never called. `element.click()` from `browser_evaluate` fires the same handler normally, which is
how the difference was isolated. **The app is innocent and this was checked from both sides:** the
developer confirmed the same form works by hand in a real browser. This is 3a's failure mode with 3a's fix
removed — a fresh reload does not help, so "it landed before hydration" is not the explanation. Symptom to
recognise: text fields accept `fill` (raw DOM values stick, because nothing re-renders to overwrite them)
while every button and checkbox on the page does nothing.

**3e. Do NOT prove a destructive flow through browser automation.** Two of the driver's properties combine
badly. `window.confirm` is swallowed, so it has to be stubbed to `true` — which removes the only guard the
flow has. And `element.click()`, the 3d workaround, takes the FIRST matching element on the PAGE, not the
one in the row you meant. Together they deleted a record belonging to someone else: the stub said yes and
the click landed on a different card's button. It was recoverable only because an accessibility snapshot
taken minutes earlier still held every field and every relation. A destructive endpoint is proved by
**Pest against an isolated database**, where `RefreshDatabase` makes a wrong target harmless, **plus one
manual pass by a human**. If a browser check is genuinely needed, scope the selector to the row
(`card.querySelector(...)` — never `document.querySelectorAll(...)[0]`) and act only on a record created
for that purpose.

**3f. `browser_click` DOES work on a plain link, unlike on a button (3d).** Navigating the tool
card's name and description links landed both times — the URL changed and the target page rendered. That
is not a counter-example to 3d: a `Link` renders a real `<a href>`, and the browser follows the href on its
own whether or not React's own click handler ran. So a navigation check can be driven with `browser_click`
and needs no `element.click()` workaround; anything that depends on a React handler (buttons, checkboxes,
form submits) still does. When a link's selector is ambiguous — the navbar and the page can both point at
`/tools` — scope it (`a.text-accent[href=...]`) rather than taking the first match, per 3e.

**3g. Filling a React-controlled field in the MCP browser needs the NATIVE setter, or the form submits
empty.** 3d notes that `fill` makes raw DOM values stick. What it does not say is that React never hears
about them: `el.value = 'text'` (and `fill`) sets the DOM property while React's state stays `""`, so the
character counter keeps reading 0, and the submit handler — which sends STATE, not the DOM — posts an
empty body. It looks exactly like a broken form. The shape that works, because it is what React's own
`onChange` listens for:

    const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
    setter.call(el, 'text');
    el.dispatchEvent(new Event('input', { bubbles: true }));

Use `HTMLInputElement.prototype` for an `<input>`. **Assert on a rendered consequence of the state, not on
`el.value`** — a character counter, a disabled button, an error that should clear. `el.value` is true for
the broken case too, which is what makes it a useless check. Submit afterwards with `element.click()` per
3d; the handler then reads the state React actually has.

**4. `git status` collapses NEW DIRECTORIES.** Use `git status --short -uall` or you cannot see
what is about to be committed. Paths with square brackets need QUOTES in `git add`.
Never `git add .`.

**4a. `react-hooks/set-state-in-effect` follows a `useCallback` but not a function declared inside
the effect.** A loader written as `useCallback` and called from an effect body is flagged; the same
code declared inside the effect is not. Moving the setState after the first `await` is NOT enough —
the rule traces into the callback regardless. The shape that passes, and that `tools-list.tsx`
already uses: declare the async loader inside the effect, guard with `isMounted`, and re-run it by
bumping a `reloadToken` state that the dependency array watches. Mutation handlers then call
`setReloadToken(n => n + 1)` instead of the loader.

**5. `eslint-disable` suppresses real bugs.** When the linter objects, ask WHAT it is hiding.
Real case: `react-hooks/set-state-in-effect` was RIGHT. Both workarounds (eslint-disable,
queueMicrotask) would have hidden the same redundant render. The correct fix was a lazy
`useState` initializer, because the value is read once and never changes.

**6. Do not blame the cache for a type error** before understanding it. Compare the working
case against the failing one BEFORE guessing.

**7. Edits arrive in portions** (import first, usage second). An unused import mid-work is not
a panic. But if "done" is announced while the usage is missing, reject and re-issue a
SELF-CONTAINED prompt with the ENTIRE desired end state — rejecting restores the file on disk,
so "fix the previous edit" prompts refer to something that no longer exists.

**8. A 4xx in the console is not always a failure.** A 422 while testing validation is success.

**9. `npm run build` requires the dev server STOPPED** — both want `.next`.

**10. NEVER `git stash` uncommitted work in this project.** To compare the working tree against
HEAD, extract instead: `git show HEAD:<path> > /tmp/old.php`. Note that Pint runs INSIDE the
container, which only sees `backend/` mounted at `/var/www/html` — host `/tmp` is invisible to
it. Get the file in with `docker compose cp /tmp/old.php laravel.test:/tmp/old.php`, then
`sail exec laravel.test ./vendor/bin/pint --test /tmp/old.php`.

**11. Before "fixing" a linter/formatter complaint, check whether it is YOURS.** Pint flagged
`no_unused_imports` on the edited seeder. Running it against the HEAD version of the same file
and against the two untouched sibling seeders showed all three fail identically — pre-existing
scaffold debt, not something the change introduced. Fixing it would have been unrelated
cleanup across three files.

**11a. `sail bin pint` with NO path rewrites the whole project, including that pre-existing debt.**
Five seeders (`UserSeeder`, `ToolSeeder`, `DepartmentSeeder`, `RoleSeeder`, `CategorySeeder`) still
fail `no_unused_imports` and friends per #11, so the plain command "fixes" five files nobody asked
about and they land in your commit looking like part of the feature — and `git add` of named paths
will not save you, because the paths are inside `backend/`. **Always pass the files you touched:**
`./vendor/bin/sail bin pint --test <paths>` to check, then the same paths without `--test` to fix.
Run it BEFORE `git add`, never after.

## Useful commands

Dictionary sync check (run from `frontend/`). Walks every `messages/*/` and compares it against
`bg`, which is the reference because it is the TYPE SOURCE. Prints per language what is missing
and what `bg` does not have, so adding a third language does not need a new script:

    python3 - <<'PY'
    import json, pathlib
    def flat(o, p=""):
        out = set()
        for k, v in o.items():
            key = f"{p}.{k}" if p else k
            out |= flat(v, key) if isinstance(v, dict) else {key}
        return out
    def load(p):
        return flat(json.load(p.open(encoding="utf-8")))
    ref = load(pathlib.Path("messages/bg/common.json"))
    print(f"bg (reference): {len(ref)} keys")
    ok = True
    for d in sorted(p for p in pathlib.Path("messages").iterdir()
                    if p.is_dir() and p.name != "bg"):
        f = d / "common.json"
        if not f.exists():
            print(f"{d.name}: NO common.json"); ok = False; continue
        keys = load(f)
        missing, extra = sorted(ref - keys), sorted(keys - ref)
        print(f"{d.name}: {len(keys)} keys — "
              f"{'in sync' if not missing and not extra else 'OUT OF SYNC'}")
        for k in missing: print(f"  missing in {d.name}: {k}"); ok = False
        for k in extra:   print(f"  not in bg ({d.name}): {k}"); ok = False
    print("OK" if ok else "FAIL")
    PY

Files over the size ceiling (run from repo root):

    find backend/app frontend/src -type f \( -name '*.php' -o -name '*.ts' -o -name '*.tsx' \) \
      -exec wc -l {} + | sort -rn | head -20

Seed users (password `password`):
ivan@admin.local = owner | maria@pm.local = pm | elena@manager.local = manager |
petar@employee.local = employee | georgi@inactive.local = employee, DEACTIVATED (cannot log in)

`UserSeeder` and `CategorySeeder` use `firstOrCreate`: re-seeding never overwrites a row that already
exists, so it cannot undo an edit made through the UI. A clean slate needs `migrate:fresh --seed`.
