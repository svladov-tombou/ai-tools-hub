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

**2. Laravel validation errors are in English.** "The body field is required."
on a Bulgarian screen. Fix this BEFORE adding French, or a French user sees two
foreign languages at once. Needs `lang/` directories in `backend/`.

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

## Decided against

**Singular form for `totalCount`.** "Общо 1 коментара" and "1 comments total"
are grammatically wrong. The user saw it and decided explicitly not to fix it.
The fix, if that ever changes, is ICU plural in both dictionaries, with no
code change.
