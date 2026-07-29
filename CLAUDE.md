# Project: AI Tools Hub (greenfield, internal company platform)
Full-stack monorepo: teams share AI tools/libraries, categorize them, and discover them by role.
Two apps in one repo: `/backend` (Laravel 13 API) + `/frontend` (Next.js SPA).
Stack: Laravel 13 (PHP 8.3+), Next.js + React + TypeScript, MySQL, Redis — all in Docker.
Global rules in `~/.claude/CLAUDE.md` apply. This file adds project specifics and overrides on conflict.

## Commands (run these; do not guess)
- Everything runs in Docker. Do not run `php`, `composer`, `npm`, or `artisan` on the host — go through Sail / the containers.
- Backend uses Laravel Sail. Run Sail commands from the `backend/` directory (that is where `compose.yaml` lives).
- Start stack: `cd backend && ./vendor/bin/sail up -d` — Stop: `./vendor/bin/sail down` — Status: `./vendor/bin/sail ps`
- Backend artisan: `./vendor/bin/sail artisan <cmd>`
- Backend tests: `./vendor/bin/sail artisan test` (Pest)
- Backend deps: `./vendor/bin/sail composer <cmd>`
- Lint/format backend: `./vendor/bin/sail bin pint`
- Frontend (Next.js) lives in `frontend/`, runs on the host via nvm Node 24 (see ADR-9). Run from `frontend/`: `npm run dev` (dev server, port 3000), `npm run build`, `npm run lint`. Type-check: `npx tsc --noEmit`.
- Before declaring a task done: backend tests, frontend tests, lint, and typecheck must all pass.

## Architecture — backend (thin controllers, logic in modules)
- Controllers (`app/Http/Controllers/`) are the thin entry layer: validate input, call one action/service, return response. No business logic.
- Business logic lives in focused single-purpose Action classes (`app/Actions/`) or Services (`app/Services/`). One public method, clear name.
- Eloquent models hold relationships and scopes only — not workflow logic.
- Form Requests (`app/Http/Requests/`) own validation rules. Controllers never inline validation.
- API routes only (`routes/api.php`). This backend serves JSON, never Blade views.

## Architecture — frontend (thin components, logic in modules)
- Components (`src/components/` or `app/`) are the thin entry layer: rendering + wiring only. No business logic, no raw `fetch` to Laravel.
- All backend calls go through one API client (`src/lib/api.ts`) and focused modules under `src/lib/` (e.g. `src/lib/tools.ts`). Components call these.
- Types live next to the logic they describe or in `src/types.ts`. No `any` — if a type is unknown, model it.

## File size (target / ceiling — split by responsibility, never cut mid-logic)
- Controllers / components: 30–80 / 150 lines.
- Actions, services, logic modules: <100 / 200 lines.
- Helpers, migrations, seeders, tests: looser / ~300 lines.

## Auth & security (token-based Sanctum — chosen deliberately, see ADR)
- Auth is Laravel Sanctum API tokens (Bearer), NOT cookie/SPA session auth. Do not add `SANCTUM_STATEFUL_DOMAINS`, CSRF-cookie flows, or `withCredentials` — this project sends `Authorization: Bearer <token>`.
- Frontend stores the token and attaches it in `src/lib/api.ts`; components never touch the token directly.
- Role-based access (owner, backend, frontend, pm, qa, designer) is enforced on the BACKEND (policies/middleware). Frontend role checks are UX only — never the security boundary.
- Secrets (`APP_KEY`, DB password, any keys) live in `.env` and are NEVER output or committed. Reading non-secret config (e.g. an URL) for diagnosis is fine.
- `.gitignore` must ignore: `/vendor`, `/node_modules`, `.env` and `.env.*` (keep `.env.example`), `/backend/storage/*.key`, frontend build output (`.next/`, `dist/`), and `.playwright-mcp/`.

## Testing
- Backend: Pest for actions/services and API endpoints. Test behavior (the JSON contract, the role rule), not implementation.
- Frontend: Vitest for logic in `src/lib/`. Test behavior, not implementation.
- IMPORTANT: for UI verification use the Playwright MCP browser tools — actually open the app in a browser, interact, and confirm the flow works. Do not claim the UI works without a browser check.
- A test must assert the DESIRED behavior, never be trimmed to match current (possibly wrong) output.
- IMPORTANT: never make tests pass by skipping, narrowing, or deleting them. No `.skip`/`.only`/`->skip()`, no commenting-out, no deleting a failing test. "Tests pass" means they actually ran and were green.

## Autonomous work
- Follow the global Escalation rule: pick the most conservative option that fits these conventions, proceed, and log non-trivial choices in `docs/decisions.md`.
- After a working slice, commit (one logical change per commit).

## Decisions
- Architectural decisions are recorded in `docs/decisions.md` (ADR format, append-only, English).
- When you make or change a non-trivial architectural decision, add a new ADR. Never edit past ADRs — mark them `Superseded by ADR-N`.
