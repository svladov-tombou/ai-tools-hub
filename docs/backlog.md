# Backlog

Known gaps and deferred work, ordered by how likely each is to bite.

This file is not a checklist. A fixed item is DELETED, not ticked — a backlog
full of green marks is noise. Items rejected on purpose live in the last
section so nobody proposes them again.

## Open

**1. Comment pagination has no controls.** `getComments` takes a `page`
parameter that nothing calls (ADR-48). At 60 comments the screen says
"60 total" and shows 50. Copied from `tools-list.tsx`, which has the same gap
but hurts later.

**3. `maxLength={5000}` on the description truncates silently on paste.** Paste
6000 characters, the field keeps 5000, nothing says so. The comment field
deliberately has no `maxLength` for this reason — the two forms now differ.

**4. The card description is now an `<a>`, not a `<p>`.** It cannot be selected
with the mouse for copying (dragging drags the link), and the link's accessible
name is the whole text. Fix, if reopened: a small "Read more" hit area.

**5. The "back" link loses the filter** from the query string — it points at
bare `/tools` (ADR-46). The browser back button preserves it, so this is
suboptimal, not broken.

**6. `npm run build` and `npm run dev` fight over `.next`.** This stalls EVERY
frontend phase. Fix: `distDir` in `next.config.ts`, read from an environment
variable. One line. Verified: `next build` has no `--distdir` flag.

**7. The frontend has no test runner.** `package.json` has no `test` script.
Playwright MCP covers part of the need, but by hand only, not in CI. Do not
install one without asking (see CLAUDE.md).

**8. Probably no test that deactivation deletes tokens.** The whole mechanism
is one line in `UserController::deactivate` (ADR-32). If a refactor drops it,
every test stays green. UNVERIFIED — confirm before writing the test.

**9. `comments.body` is `text` (~65k) while validation caps it at 2000.** Same
mismatch as the description. Not reachable through the API; a seeder or tinker
can do it.

**10. Tool metadata is duplicated** between `tool-card.tsx` and
`tool-detail.tsx`. Flagged under the rule of three — extract at the third
consumer, not before.

**11. Two components are over the line limit.** `tool-detail.tsx` is 166 and
`tool-card.tsx` is 167, against a ceiling of 150. Left deliberately, recorded
in ADR-46.

**12. `docs/workflow.md` is not in git** (`.git/info/exclude`). No history and
no `git checkout` if it breaks. If it is ever backed up, back it up outside
the repo.

**13. `.gitignore` is modified locally on the server** (`M`). `deploy.sh` runs
`git pull`. If that file ever changes in the repo too, the pull conflicts
mid-deploy.

**14. `ValidationLocaleTest.php` is 469 lines against a ceiling of ~300.** It
was 363 before French was added. Left whole on purpose so far: the file is two
datasets plus the tests that consume them, and moving the cases to
`tests/Datasets/` would put them a file away from the assertions that explain
them. A real split has to extract `localeActingAs` and `localeTool` into
`tests/Pest.php` or a trait first — Pest loads every test file into one process,
so a second file defining them is a fatal redeclare.
`ValidationLanguageParityTest.php` is a separate file but shares nothing with
this one, so it does not count as the trigger.

**15. `array:bg,en,fr` cannot say which key was wrong.** A disallowed key and a
value that is not an array both report under plain `validation.array`, so the
Bulgarian message has to be true for both and says only "must be an array".
A specific message would need `custom.name.array` — out of scope in ADR-49.

**16. `<html lang="en">` on every locale.** The tag is in
`src/app/layout.tsx` (line 27, the `lang` attribute on line 28), the root
layout and the only `<html>` in `src/`. The build of 7 August 2026 shows it in
the output itself: `.next/server/app/bg.html`, `en.html` and `fr.html` all
carry `lang="en"`. `RootLayout({ children })` takes no `params`, and an App
Router root layout sits outside every dynamic segment, so it can never receive
one. `src/app/[locale]/layout.tsx` does have the locale (line 19) but renders
no `<html>`. The root layout exists because `src/app/page.tsx` is a route at
`/` outside `[locale]` that redirects to the default locale, and a page at the
top forces a root layout, which must carry `<html>`/`<body>`. Three options.
(A) Promote `[locale]/layout.tsx` to root: delete `src/app/layout.tsx` and
`src/app/page.tsx` and move `<html lang={locale}>` with
`suppressHydrationWarning`, `<body>`, the fonts, the `./globals.css` import,
the metadata and `ThemeProvider` down. This is the structure next-intl
documents, and the recommended one. (B) `getLocale()` in the root layout —
tried and rejected, not from memory: `setRequestLocale` fills a per-request
cache and is called by the child, which renders after the parent, so static
generation would silently give `lang="bg"` everywhere. (C) `headers()` in the
root layout — works, but makes the parent of everything dynamic and collapses
all 28 prerendered routes (25 pages, plus `/favicon.ico`, `/_not-found` and
`/_global-error`) to server-rendered. A is a layer change, so CLAUDE.md point 6
makes it the user's call. It also needs a check that `/` still redirects to
`/bg`: with `src/app/page.tsx` deleted that job falls entirely to `src/proxy.ts`
— the Next.js 16 middleware file, whose matcher does cover `/`.

**17. Hardcoded English strings in `theme-toggle.tsx`.** Line 34 is
`aria-label={isDark ? "Switch to light mode" : "Switch to dark mode"}`, which
violates the "no hardcoded user-facing strings" rule in CLAUDE.md. The toggle
is rendered by the navbar (twice — desktop and mobile), which lives in
`[locale]/layout.tsx`, so the English label is announced on every page in all
three locales. The fix is two keys in each of the three `common.json`
dictionaries plus the component change.

**18. The tool card overflows at 375px.** The row holding the name, the draft
badge and the edit/delete buttons in `tool-card.tsx`. Measured on 7 August 2026
as a baseline across all locales: `/bg/tools` 7 overflowing elements, worst
+30px; `/fr/tools` 2, worst +22px; `/en/tools` 0 — which is why it went
unnoticed. French is not the cause; it is lighter than Bulgarian. A pre-existing
layout defect.

## Decided against

**Singular form for `totalCount`.** "Общо 1 коментара" and "1 comments total"
are grammatically wrong. The user saw it and decided explicitly not to fix it.
The fix, if that ever changes, is ICU plural in both dictionaries, with no
code change.
