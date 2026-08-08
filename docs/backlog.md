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

**18. The tool card overflows at 375px.** The row holding the name, the draft
badge and the edit/delete buttons in `tool-card.tsx`. Measured on 7 August 2026
as a baseline across all locales: `/bg/tools` 7 overflowing elements, worst
+30px; `/fr/tools` 2, worst +22px; `/en/tools` 0 — which is why it went
unnoticed. French is not the cause; it is lighter than Bulgarian. A pre-existing
layout defect.

**19. `/_not-found` renders without the app shell.** ADR-52 promoted
`[locale]/layout.tsx` to the root layout, so nothing sits above `[locale]` any
more — and the built-in not-found page is outside it. What is served is
`<html id="__next_error__">` with no `lang`, no font or theme classes, a bare
`<body>` and ZERO stylesheet links, against one on every real page; the
`metadata` title still applies, so it is unstyled rather than blank. The reach
is narrow: the proxy matcher `/((?!api|_next|.*\..*).*)` skips only paths
containing a dot, so `/nope` is redirected to `/bg/nope` and never arrives
here, while `/foo.bar` does — verified, 404 and unstyled. The trap when fixing
it: `src/app/not-found.tsx` would put a route back above `[locale]`, which
forces a root layout there again, which is precisely the hardcoded
`lang="en"` ADR-52 removed. NOT compared against the previous behaviour — the
verification build overwrote `.next`, so the pre-change artifact no longer
exists.

**Known fix, deferred to the 16.3 upgrade.** Next.js has an
`app/global-not-found.tsx` convention for exactly this shape — a root layout
defined by a top-level dynamic segment. It must return a FULL HTML document, so
it carries its own `<html lang>`, fonts and `globals.css`, and it does NOT put a
route back above `[locale]`: ADR-52 stands. It requires
`experimental.globalNotFound` in `next.config.ts`. The project is on next
16.2.12 and the config has no `experimental` block at all, so this flag would be
the first. Deferred for the same reason `getLocale()` was: an experimental flag
is not worth the version risk for a page reachable only through dotted non-file
URLs. Re-evaluate at 16.3 TOGETHER with `experimental.rootParams` /
`getLocale()` (ADR-52) — one decision about the version, not two about the same
risk.

**20. `npm audit` reports 6 high-severity advisories in the frontend.**
Printed on every deploy. They split in two by REACHABILITY, not by severity.

Build/lint only, no runtime path: `brace-expansion` (one copy under
`@typescript-eslint`), `js-yaml`, `nanoid`. All three are denial-of-service
against the process that runs them, so they need an attacker who controls their
input — and the input of a linter is this repository.

Runtime-capable but unreachable here: `postcss` (build-time, over our own CSS)
and `sharp`, which inherits libvips CVEs and only matters where images are
processed. The frontend has NO `<img>` and NO `next/image` anywhere — verified
by a sweep and confirmed by the user — so `sharp` is present as a `next`
dependency and never called. THE CONDITION THAT CHANGES THIS: adding
`next/image` over user-supplied URLs makes `sharp` reachable and this entry
urgent.

Both of the second group need `npm audit fix --force`, i.e. a MAJOR bump of
`next` itself, not a patch. Fold into the 16.3 upgrade together with item 19
and `getLocale()` (ADR-52). Do NOT run `npm audit fix --force` on its own:
it trades a working build for a theoretical risk.

## Decided against

**Singular form for `totalCount`.** "Общо 1 коментара" and "1 comments total"
are grammatically wrong. The user saw it and decided explicitly not to fix it.
The fix, if that ever changes, is ICU plural in both dictionaries, with no
code change.
