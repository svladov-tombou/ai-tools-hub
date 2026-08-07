# AI Tools Hub — project rules
Internal company platform: teams share, categorize and discover AI tools.
`backend/` Laravel 13 API (Sail, Docker) + `frontend/` Next.js 16 (host). Global rules in
`~/.claude/CLAUDE.md` apply; this file overrides on conflict.

## IMPORTANT: stop and ask the human
Do not decide these yourself. Present options and wait. Asking costs seconds; a wrong choice here costs days.
Anything NOT on this list is yours: decide it, report the choice, keep going.
Judge by reversibility — uncommitted work on a branch undoes with one `git checkout`.
1. Database schema changes beyond adding a nullable column (renames, type changes, relation changes).
2. Anything touching permissions, policies, or who can see/do what.
3. Adding an external dependency (composer or npm package).
4. Changing an existing API response shape, field name, or route.
5. Product behaviour: WHAT a feature does, and any edge case whose answer a user would
   notice. HOW is yours — naming, structure, the wording of UI strings and their keys.
6. Introducing a NEW KIND of thing the project does not have yet (a new layer, a new state model, a new top-level directory). Placing a file inside an existing pattern is not this.
7. Anything that revises a decision already recorded in `docs/decisions.md`.
8. Ambiguity about WHAT is wanted: report it and stop. Never invent a missing
   requirement. Ambiguity about HOW: choose what best fits the existing code, name it,
   and continue.
When unsure whether something qualifies, ask.
The `ask` and `deny` rules in `.claude/settings.json` are enforced even under
`--dangerously-skip-permissions` (verified 2026-08-07). Never route around a gated path
with `python3`, `sed` or `cat >`. If a gate blocks work you think is correct, say so and wait.

## IMPORTANT: i18n — the most expensive trap here
`messages/bg/common.json` is the TYPE SOURCE for `t()`. A key must exist there first or `tsc` fails.
Add dictionary keys as their own step, before the code that uses them. Both `bg` and `en` stay in sync.
Never silence a `t()` type error with `as never` or eslint-disable — that error is the check working.
No hardcoded user-facing strings anywhere.
Two mechanisms: UI text and fixed-slug labels (roles, departments) live in the dictionary,
category names in the DATABASE (ADR-27). Never translate user-creatable rows through the
dictionary — a typed dictionary cannot cover rows that do not exist at build time.

## Roles
Exactly four, seeded with a numeric `level`: owner (100), pm (60), manager (40), employee (20).
Never invent role names. Authorization is enforced in the BACKEND (policies/middleware);
frontend role checks are UX only, never the security boundary.

## Commands (run these; do not guess)
- Backend runs in Docker/Sail, from `backend/`: `./vendor/bin/sail up -d`, `./vendor/bin/sail artisan <cmd>`,
  `./vendor/bin/sail composer <cmd>`, format with `./vendor/bin/sail bin pint`.
- Backend tests: `./vendor/bin/sail artisan test` (Pest).
- Frontend runs ON THE HOST (nvm Node 24), from `frontend/`: `npm run dev`, `npm run lint`, `npm run build`,
  `npx tsc --noEmit`. Never run frontend commands inside a container.
- `npm run build` requires the dev server to be STOPPED (both want `.next`).

## Definition of done
Backend: `sail artisan test` green (the FULL suite, not just new tests).
Frontend: `npx tsc --noEmit` + `npm run lint` + `npm run build` all clean.
UI work: verified in a real browser via Playwright MCP. Never claim UI works without opening it.
There is NO frontend unit-test framework. Do not install one — that is an ask-first decision.

## Architecture
Backend: controllers are thin (validate, call one action, return). Business logic in
`app/Actions/` or `app/Services/`, one public method each. Validation in Form Requests.
Models hold relationships and scopes only. JSON only — never Blade.
Frontend: components render and wire, nothing else. Every backend call goes through
`src/lib/api.ts` — never a raw `fetch`. No `any`.

## File size (target / ceiling)
Controllers, components: 30–80 / 150. Actions, services, logic modules: <100 / 200.
Helpers, migrations, seeders, tests: looser / ~300.
Split by responsibility, never mid-logic. The ceiling is not a goal: if splitting a 220-line
module would not make it clearer, leave it and say why.

## Security
Auth is Sanctum Bearer tokens (ADR-6). Never add `SANCTUM_STATEFUL_DOMAINS`, CSRF cookies,
or `withCredentials`. Token handling lives only in `src/lib/api.ts`.
Never output or commit secret values.

## Tests
IMPORTANT: never make a test pass by skipping, narrowing, or deleting it. No `.skip` / `.only`,
no commenting out, no deleting a failing test. "Tests pass" means they ran and were green.
A test must assert the DESIRED behaviour, and must FAIL if the behaviour is wrong —
a test that passes either way proves nothing.

## Before you write code
Read `docs/pitfalls.md` before touching the frontend, migrations, or seeders.
Read the relevant ADRs in `docs/decisions.md` before implementing anything non-trivial.
Every completed phase ends with a new ADR (append-only; never edit a past one —
mark it `Superseded by ADR-N`) and one logical commit.

## Delegating to the coder subagent
Reconnaissance, review, and running tests stay with you — do not delegate them.
The subagent inherits this file but NOT the conversation: it cannot see what you read or discussed.
State the complete desired end state in the prompt, never "finish the previous edit".
The subagent cannot ask questions. If a prompt could be read two ways, resolve it before delegating.

## Language
Talk to the user in Bulgarian. Write code, comments, commit messages, ADRs and
subagent prompts in English. `docs/decisions.md` is English-only — it is append-only,
so a mixed-language entry can never be cleaned up.

## Maintaining the rule files
You append to `docs/decisions.md` and `docs/pitfalls.md` yourself, every phase.
NEVER edit `CLAUDE.md` on your own initiative — propose the exact
replacement text and wait for the user to approve. If a rule here is wrong or outdated,
say so; never silently work around it.

## Long-running processes
The user starts the environment: `sail up -d` in `backend/`, `npm run dev` in `frontend/`.
Never start the frontend dev server yourself — it does not exit and would block the session.
Check whether it is up with `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000`.
If it is not running, ASK the user to start it and wait.
