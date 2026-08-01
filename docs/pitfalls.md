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

**`ToolPayload` has NO optional fields.** Nullables are `| null`, never `?`. Deliberate: the
controller uses `->safe()`, so a MISSING field is not updated. Clear a documentation link and
omit the field, and the old value silently survives. The type forces an explicit null.

## i18n

`messages/bg/common.json` is the TYPE SOURCE for `t()`. A new key goes there FIRST or `tsc` fails.
Keep `bg` and `en` in sync — check with the script in "Useful commands" below.
A template key using a backtick string compiles ONLY if the interpolated value is a union of
existing keys. Never fix such an error with `as never` or eslint-disable — that error is the
check working.

## Backend

**Two alphabets.** Writing wants IDs: `category_ids`, `role_ids`, `department_ids`.
Filtering wants names: category → slug, department → slug, role → NAME. Mixing these
produces empty results with no error.

**Pivot tables:** `category_tool`, `role_tool`, `department_tool` — alphabetical, singular,
no timestamps.

**Seeder order in `DatabaseSeeder` is a dependency declaration**, not a preference:
RoleSeeder → UserSeeder → CategorySeeder → DepartmentSeeder → ToolSeeder.
Running ToolSeeder before DepartmentSeeder creates tools with NO departments and NO error.

**`created_by` is deliberately OUTSIDE `$fillable`** (anti-tampering) and set server-side.
A test caught that it was silently null, which made the whole ADR-12 rule dead code.

**On update, use `sync()`** (replaces), not `syncWithoutDetaching()` (only adds).

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

**4. `git status` collapses NEW DIRECTORIES.** Use `git status --short -uall` or you cannot see
what is about to be committed. Paths with square brackets need QUOTES in `git add`.
Never `git add .`.

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

## Useful commands

Dictionary sync check (run from `frontend/`):

    python3 - <<'PY'
    import json
    def flat(o, p=""):
        out = set()
        for k, v in o.items():
            key = f"{p}.{k}" if p else k
            out |= flat(v, key) if isinstance(v, dict) else {key}
        return out
    bg = flat(json.load(open("messages/bg/common.json")))
    en = flat(json.load(open("messages/en/common.json")))
    print("BG only:", sorted(bg - en) or "none")
    print("EN only:", sorted(en - bg) or "none")
    print("Total:", len(bg), "|", len(en))
    PY

Files over the size ceiling (run from repo root):

    find backend/app frontend/src -type f \( -name '*.php' -o -name '*.ts' -o -name '*.tsx' \) \
      -exec wc -l {} + | sort -rn | head -20

Seed users (password `password`):
ivan@admin.local = owner | elena@manager.local = manager | petar@employee.local = employee
