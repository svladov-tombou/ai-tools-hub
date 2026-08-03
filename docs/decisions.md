# Architecture Decision Records

Append-only. Never edit a past ADR — when a decision changes, add a new ADR and mark the old one `Superseded by ADR-N`.

---

## ADR-1: Manual setup instead of the course Starter Kit

**Status:** Accepted

**Context:** Day 6 offers two paths — a prebuilt Bitbucket Starter Kit, or manual setup from scratch. The Starter Kit is third-party auto-generated code with pinned (possibly stale) versions that cannot be reviewed up front.

**Decision:** Build the environment manually. This matches the goal of owning the architecture and understanding every step, and lets us pick current versions ourselves.

**Consequences:** More setup work now; full control and understanding in return. We choose our own versions (see ADR-2/3/4).

---

## ADR-2: Laravel 13 (not "Laravel 10+")

**Status:** Accepted

**Context:** Course lesson 6.4 says "Laravel 10+ or latest stable". Laravel 10 is old; current stable as of setup is Laravel 13 (released 2026-03-17), which requires PHP 8.3+.

**Decision:** Use Laravel 13.

**Consequences:** Backend requires PHP 8.3 or newer (see ADR-3). Access to current framework features and the full support window.

---

## ADR-3: PHP 8.3+ (not 8.2)

**Status:** Accepted

**Context:** Course lesson 6.10 says "install PHP 8.2". Laravel 13 requires PHP 8.3 as a minimum.

**Decision:** Use PHP 8.3+ (8.4 acceptable) in the backend container.

**Consequences:** Aligns with ADR-2. The version is pinned in the backend Dockerfile.

---

## ADR-4: Node.js 24 (Active LTS) for the frontend

**Status:** Accepted

**Context:** The developer's host runs Node 22, which is now Maintenance LTS (EOL 2027-04). Node 24 is the current Active LTS; Node 26 is Current (not yet LTS).

**Decision:** Target Node.js 24 (Active LTS) inside the frontend container. The host Node version is irrelevant because the frontend runs in Docker.

**Consequences:** The Node version is pinned in the frontend Dockerfile, independent of the host. No host upgrade required.

---

## ADR-5: MySQL for the database (not Supabase)

**Status:** Accepted

**Context:** Lesson 6.4 lists "Supabase or MySQL/PostgreSQL". The self-hosted manual path and the course's own stack use MySQL, and a Dockerized MySQL keeps everything local and self-contained.

**Decision:** Use MySQL, running as a container in the stack.

**Consequences:** No external managed-DB dependency. Redis is also included for cache/queue.

---

## ADR-6: Token-based Sanctum auth (not cookie/SPA session auth)

**Status:** Accepted

**Context:** The backend (Laravel) and frontend (Next.js) are separate apps on different origins. Laravel Sanctum supports two modes: cookie-based SPA session auth (needs CORS + stateful domains + CSRF cookie + credentialed requests) and stateless API tokens (Bearer header). Cookie mode has several easy-to-misconfigure moving parts across origins.

**Decision:** Use Sanctum API tokens. The frontend sends `Authorization: Bearer <token>`; no `SANCTUM_STATEFUL_DOMAINS`, no CSRF-cookie flow, no `withCredentials`.

**Consequences:** Simpler cross-origin behavior and easier debugging for a learning project. Token storage on the client is handled in one place (`src/lib/api.ts`) and carries the usual client-token security considerations. Role checks are enforced on the backend; frontend role checks are UX only.

---

## ADR-7: Single root CLAUDE.md (not per-app files)

**Status:** Accepted

**Context:** This is a monorepo with `/backend` and `/frontend`. Claude Code supports either one root CLAUDE.md or a root file plus per-directory files.

**Decision:** Keep one root CLAUDE.md covering both apps, staying within the ~80–120 line budget.

**Consequences:** The whole behavioral contract is visible in one place — simpler for a solo learning project. If the file outgrows its budget, split into per-app files and record that as a new ADR.

---

## ADR-8: Actual backend versions after Sail install (supersedes the version note in ADR-3)

**Status:** Accepted

**Context:** ADR-2/3 targeted "Laravel 13, PHP 8.3+". The `laravel.build` installer provisioned the current Sail runtime, which resolved to concrete versions.

**Decision:** Record the actual provisioned stack: PHP 8.5 (Sail `sail-8.5/app` image), MySQL 8.4, Redis (alpine), Mailpit. Laravel 13.x. This satisfies ADR-3 (8.3+) — no conflict, just the concrete number.

**Consequences:** The backend app container is named `laravel.test` (not `backend`), which is why the CLAUDE.md command section uses Sail syntax (`./vendor/bin/sail ...`) run from `backend/`. Ports: web 80, Vite 5173, MySQL 3306, Mailpit UI 8025.

---

## ADR-9: Frontend runs on the host via nvm (not Docker); Next.js 16 in `frontend/`

**Status:** Accepted

**Context:** The backend is Dockerized via Sail. For the Next.js frontend we chose between a Docker container (full "everything in Docker" consistency) and running on the host via nvm (better dev experience — instant hot-reload, no volume file-watching friction). This is a dev-time decision; production deployment to the VM will be containerized separately.

**Decision:** Run Next.js on the host using nvm Node 24 (per ADR-4). The app was scaffolded with `create-next-app@16.2` into `frontend/` (TypeScript, Tailwind, ESLint, App Router, `src/` dir, `@/*` alias). This overrides the earlier CLAUDE.md assumption that the frontend runs in a container.

**Consequences:** Frontend dev is `npm run dev` on the host from `frontend/`, not through Docker. `create-next-app` also generated `frontend/AGENTS.md` (a Next.js 16 warning to consult bundled docs before coding). We keep it as a deliberate exception to ADR-7's "single root CLAUDE.md": AGENTS.md is a separate, complementary mechanism carrying frontend-specific guidance, not a competing behavioral contract. The redundant `frontend/CLAUDE.md` (which only pointed at AGENTS.md) was deleted.

---

## ADR-10: Roles as a separate table (with hierarchy level), many-to-many with users; no permissions layer yet

**Status:** Accepted

**Context:** The app has six roles (owner, backend, frontend, pm, qa, designer). Three modeling questions arose: (1) roles as an enum column on users vs a separate table; (2) whether to add a permissions layer; (3) how to support "highest role" logic and multi-role users. The course lessons (6.4, 6.5, day 8, day 9) reference access "by role" only — they never introduce permissions as a concept; day 9's middleware protects routes by role, not by permission. The seed data shows one role per user, but the developer prioritizes administrative flexibility and UX and wants users to be able to hold multiple roles.

**Decision:** (1) Roles live in a separate `roles` table: id, name (slug, e.g. "owner"), display_name (e.g. "Owner"), level (integer rank), timestamps. (2) Users relate to roles many-to-many via a `role_user` pivot — a user may hold multiple roles. (3) The `level` column encodes a role hierarchy for access logic (higher = more authority), seeded with gaps so new roles can be inserted between existing ones without renumbering: owner=100, pm=60, qa=50, backend=40, frontend=40, designer=30. (4) No permissions layer for now: access is checked by role or by minimum level. A permissions table + role_permission pivot can be added later without touching existing structure if a lesson requires finer-grained control.

**Consequences:** One extra pivot table and one integer column now, in exchange for cheap future flexibility (going one→many roles later would be a painful data migration; the reverse is trivial). Access checks use helpers on the User model: hasRole(name) for a specific role, and level-based checks (e.g. hasAtLeastLevel(int) / highestRole()) for hierarchical rules — day 9 route protection will use these. The dashboard displays ALL of a user's roles (not just the highest); the hierarchy level is for access logic and optional ranking, not for hiding roles from the user. Note that some roles intentionally share a level (backend and frontend both 40), so "highest role" is not always unique — this is acceptable because presentation shows all roles and access logic only needs the max level, not a unique top role. Adding permissions later is a non-breaking additive change and would get its own ADR.

---

## ADR-11: Tools data model — public catalog, roles as metadata, tools↔categories and tools↔roles many-to-many

**Status:** Accepted

**Context:** The core feature is a shared internal catalog of AI tools that teams discover and search. The developer clarified the intended real-world use: ALL users can see and read ALL tools — roles are NOT a read-access boundary for tools; they are metadata/tags indicating which roles a tool is relevant for, used for categorization and search/filtering. Full-text search across all textual fields is a core requirement (implemented in the UI phase). Day 9 later introduces moderation (approve/reject proposed tools) and an audit trail (who added what), which sit in mild tension with "everyone sees everything immediately"; we resolve this by defaulting everything to visible now while leaving a cheap hook for later moderation.

**Decision:** Create a `tools` table with fields: name (string), description (text), url (string — link to the tool), documentation_url (string, nullable), video_url (string, nullable), difficulty (string enum nullable: beginner/intermediate/advanced), status (string enum: draft/published, default published), created_by (foreignId to users, nullable, nullOnDelete — audit trail per Day 9). Two many-to-many relations via pivots: category_tool (a tool belongs to multiple categories) and role_tool (a tool is "relevant for" multiple roles — a tag, NOT an access filter). A `categories` table: name (string, unique), slug (string, unique). All textual tool fields (name, description) are designed to be searchable.

**Consequences:** Roles now serve two distinct purposes — user access/authority (ADR-10, e.g. who can moderate) AND tool relevance tags (this ADR); these are independent uses of the same roles table. The `status` column defaults to published so today everything is visible immediately (matches the "open sharing" intent); Day 9 can activate moderation by using draft status without a migration. `created_by` gives the audit trail cheaply now rather than via a later migration. No permissions layer still (per ADR-10). Read access to the catalog is universal; role-based restrictions, if any, will apply only to write/moderate actions, enforced on the backend.

---

## ADR-12: Tool write authorization — authors edit their own; owner and pm moderate all tools

**Status:** Accepted

**Context:** The catalog is public to read (any authenticated user sees all tools, per ADR-11). For write actions the developer chose an explicit role-name policy (not the level-based hierarchy from ADR-10 — level is unused here on purpose, in favor of explicit, readable role checks). Note: "owner" is a company/platform-level role (owner of the organization), NOT the owner of an individual tool; tool ownership is tracked separately via created_by.

**Decision:** (1) Read (index, show): any authenticated user, all tools. (2) Create: any authenticated user. (3) Edit/delete: the tool's author (created_by == current user) may edit/delete their OWN tool; users with the owner OR pm role may edit/delete ANY tool (they are catalog administrators/moderators). Other roles (qa, backend, frontend, designer) may only edit/delete tools they created. Checks use explicit hasRole('owner') / hasRole('pm') plus author comparison, not level thresholds. Tools with a null created_by can only be edited/deleted by owner or pm.

**Consequences:** Enforced on the backend via a Tool policy (ToolPolicy) — frontend checks are UX only (ADR-6/ADR-10). owner and pm are the two administrative roles over the catalog. Level-based authorization remains available but unused for tool writes; if the admin role set changes, add a new ADR.

---

## ADR-13: Frontend i18n — next-intl v4 with full locale routing; Bulgarian default, browser detection disabled

**Status:** Accepted

**Context:** Day 8 builds the frontend UI, whose labels are in Bulgarian. To avoid hardcoded strings scattered across components (expensive to retrofit), i18n was set up before the first real screen. The developer chose full locale routing (locale in the URL) over a single-dictionary setup, because the interface should support more than one language over time (Bulgarian now, English available, more later). A verification step revealed that next-intl's middleware negotiates locale from the browser's Accept-Language header by default, so an English-configured browser was redirected to /en despite a Bulgarian default — surfacing a design decision about whether the app should follow the browser or always start in Bulgarian.

**Decision:** (1) Use next-intl v4 with Next.js 16 App Router, full locale routing: all translatable pages live under `src/app/[locale]/`, locales `['bg','en']`, defaultLocale `'bg'`. (2) Follow the required Next.js 16 patterns strictly: the middleware file is `src/proxy.ts` (renamed from middleware.ts in Next.js 16, still using `createMiddleware` from `next-intl/middleware`); `setRequestLocale(locale)` is called first in the `[locale]` layout and in every page before any other next-intl API, to preserve static rendering; `src/i18n/request.ts` validates the incoming locale with `hasLocale` and falls back to the default. (3) Set `localeDetection: false` in the routing config: the app ALWAYS starts in Bulgarian regardless of browser language; users switch to English manually via a language switcher (to be built). Automatic Accept-Language negotiation is intentionally off because this is an internal, behind-login tool for a Bulgarian-speaking audience, not a public global site. (4) Dictionaries are organized by namespace under `messages/{locale}/` (e.g. `common.json`), not one flat file, and TypeScript type safety for translation keys is enabled via `src/types/next-intl.d.ts` typed against the Bulgarian dictionary. (5) `app.title` ("AI Tools Hub") is intentionally identical across locales — it is a product/brand name, not an untranslated string.

**Consequences:** URLs carry a locale prefix (`/bg/...`, `/en/...`); the bare root `/` redirects to `/bg`. Adding a third language later is cheap: extend `locales`, add a `messages/<locale>/` folder, done. All UI text must go through the dictionary from the first component — no hardcoded strings. Frontend components stay thin and never build raw locale logic; navigation uses the wrapped helpers from `src/i18n/navigation.ts`. The browser verification that caught the /en redirect also exposed that a naive equality check on `app.title` cannot prove per-locale loading (same string both sides); the real proof came from a key that genuinely differs (`buttons.save`: Запази vs Save) — a reminder that a green check only means "the assertion I wrote passed," so assertions must target something that actually varies.

---

## ADR-14: Frontend design system — Tailwind v4 CSS-first semantic tokens, "Mint & peach" palette, manual light/dark toggle

**Status:** Accepted

**Context:** Day 8 requires a styled, clean UI. Two decisions from the Day 8 handover had to be grounded before building screens: (1) pastel light colors plus a dark mode, driven by Tailwind design tokens rather than hardcoded colors in components; (2) the palette had to be chosen visually from samples, not described in words. The scaffold uses Tailwind v4, which replaced the v3 `tailwind.config.js` approach with CSS-first `@theme` configuration — a genuine version trap, since an agent working from older knowledge would put colors in a JS config that v4 ignores. A second, subtler trap: defining colors inside `@theme inline` bakes their values at build time, which silently breaks runtime dark-mode switching. The developer picked a "fresh/modern" direction and, from three rendered samples, chose palette C (mint accent + warm peach secondary) for its approachability. Two dark-mode sub-decisions: manual toggle (not just system preference) and two states only (light/dark, no system option).

**Decision:** (1) Design tokens live in `src/app/globals.css` using Tailwind v4 CSS-first `@theme` — NO `tailwind.config.js` for colors. Layered model: raw palette values as CSS variables in `:root` (light) and `.dark` (dark overrides), then semantic role tokens mapped in a NON-inline `@theme` block (`--color-surface: var(--surface)`, `--color-accent: var(--mint)`, etc.). Naming is by role (surface, card, border, text-primary, text-secondary, accent, secondary), never by color. Fonts use `@theme inline` deliberately — they have no light/dark variant, so build-time baking is fine for them; colors must not. (2) Palette "Mint & peach": accent mint `#34BD95` (foreground `#04342C`), secondary peach `#F0997B`; light surfaces `#F1FBF7`/`#FFFFFF`, border `#C6E8DA`, text `#1a1a1a`/`#5F5E5A`; dark surfaces `#13201C`/`#1B2C27`, border `#294039`, text `#EAF5F0`/`#9DB4AC`. The mint accent intentionally has no separate dark value — it reads well on both backgrounds. (3) Dark mode is class-based (`.dark` on `<html>`, via `@custom-variant dark`), toggled manually with `next-themes`: `attribute="class"`, `defaultTheme="light"`, `enableSystem={false}` (two states only), `disableTransitionOnChange`. `ThemeProvider` is a thin `"use client"` wrapper in `src/components/theme-provider.tsx` so the root layout stays a server component; the root `<html>` carries `suppressHydrationWarning`. The toggle (`src/components/theme-toggle.tsx`) uses `useTheme` with a mounted-state guard to avoid a hydration mismatch on first render.

**Consequences:** Components use ONLY semantic utilities (`bg-card`, `text-accent`, `border-border`, etc.) — no hardcoded hex anywhere outside the token definitions in globals.css. A rebrand or palette change is a one-file edit. Adding more roles or a third theme later is additive. Verified in a real browser via the Playwright MCP, not by eye: light-mode computed styles matched the palette pixel-exact, the toggle flips the `.dark` class and swaps every token, the choice persists in `localStorage`, and there were zero hydration warnings on reload (the reliable signal that the SSR'd class and the client's first paint agreed — i.e. no FOUC). Note the scaffold's `metadata.title`/`description` in `src/app/layout.tsx` are still the create-next-app defaults ("Create Next App") — to be fixed in a later step, tracked separately from this design phase.

**Alternatives considered:** System-only dark mode (`prefers-color-scheme`) — rejected in favor of a manual toggle for user control, which is a superset (system detection remains available inside next-themes if wanted later). A three-state toggle (light/dark/system) — rejected for simplicity; two states cover the need now.

---

## ADR-15: App shell — top navigation bar, deferred auth, role-ready nav structure

**Status:** Accepted

**Context:** Day 8 requires role-aware navigation and a shell for the screens (Dashboard, Tools, Profile). Three sub-decisions arose. (1) Layout: top bar vs sidebar — with only 3-4 sections, a sidebar is over-engineered. (2) The navbar's user area implies authentication, but the login flow through the frontend was explicitly not built yet (handover); building it now would mix a large, security-sensitive concern (token handling, ADR-6 rules) into a UI-shell phase. (3) The handover asks for a menu "according to role," but the real sections (Dashboard, Tools, Profile) are visible to all roles — role-filtering only becomes meaningful once a role-restricted section exists (e.g. moderation for owner/pm per ADR-12).

**Decision:** (1) A top navigation bar (`src/components/navbar.tsx`) placed inside `[locale]/layout.tsx` above `{children}`, so it appears on every page under `[locale]/`. Left: app name "AI Tools Hub" (text, no logo) + localized nav links. Right: language switcher, dark-mode toggle (moved here from the test page), and a user area. It collapses to a hamburger menu below the `md` breakpoint. (2) Authentication is deferred: the user area is a static placeholder ("Иван (Owner)") with a no-op Logout button and a code comment flagging it for real-auth wiring. The real login/token flow is its own future phase, kept separate on purpose. (3) Nav links live in a data array (`src/lib/nav-links.ts`) of `{ href, labelKey, requiredRole? }` where `requiredRole` is currently unset on all entries — the structure is ready for role-filtering, but no filtering logic is implemented yet. A shared `Role` type lives in `src/types.ts`, mirroring the backend role names. (4) The language switcher (BG | EN text links) switches locale while preserving the current path, using `href={pathname} locale={locale}` with next-intl's navigation helpers — no manual URL string manipulation. (5) `NextIntlClientProvider` was added in `[locale]/layout.tsx` (via `getMessages()`, after `setRequestLocale`) so client components can read translations.

**Consequences:** All screens now render inside this shell. Adding a role-restricted section later is: set `requiredRole` on the nav entry + write a one-line filter — no structural change. When real auth lands, the placeholder user area and the no-op Logout get wired to it; nothing else in the navbar changes. All navbar text goes through the i18n dictionary (`nav.*` keys added to both locales); nav `labelKey` is a literal union type, so a typo'd key is a compile error (next-intl's typed `t()`). Verified via Playwright MCP: navbar renders localized on /bg and /en, the BG|EN switch preserves the path, the dark toggle works from its new location, the mobile hamburger opens/closes, and there are no hydration warnings. Note: `/tools` and `/profile` 404 for now — those pages are later phases. The scaffold metadata defaults flagged in ADR-14 were also fixed in this phase.

**Alternatives considered:** Sidebar navigation — rejected as over-engineered for 3-4 sections. Building the real login flow now alongside the navbar — rejected to keep the security-sensitive auth work as its own focused phase rather than entangling it with the UI shell.

---

## ADR-16: Role set simplified to the real company hierarchy (supersedes the role list in ADR-10)

**Status:** Accepted

**Context:** ADR-10 seeded six roles (owner, pm, qa, backend, frontend, designer) as generic placeholders. The developer clarified the real organizational structure this app serves: an owner at the top, a single pm beneath the owner, and everyone else is either a manager or an ordinary employee under a manager. The generic developer-discipline roles (qa, backend, frontend, designer) do not correspond to anything in this company and were never used by any business logic — reconnaissance confirmed that ToolPolicy (ADR-12) references only 'owner' and 'pm' by name, and nothing in app/ hardcodes the other role names. So the rename is safe: it touches seed data and test fixtures only, not authorization logic.

**Decision:** The role set is now exactly four: owner (level 100), pm / display_name "Project Manager" (level 60), manager (level 40), employee (level 20). qa, backend, frontend, and designer are removed. Levels keep gaps (per ADR-10's convention) so future roles can slot in without renumbering. display_names are stored in English (Owner, Project Manager, Manager, Employee) — Bulgarian UI labels live in the frontend i18n dictionary (roles.*), so translation has a single source and the DB stays language-neutral. Seed users reassigned: Иван → owner (unchanged), Елена → manager (email elena@manager.local), Петър → employee (email petar@employee.local). The database was reseeded from scratch (migrate:fresh --seed) — acceptable because this is dev with seed-only data.

**Consequences:** ADR-12's authorization is unchanged and still correct — owner and pm remain the two moderator roles, manager and employee are ordinary users who may only edit/delete their own tools (exactly the role qa/backend/frontend/designer played before). The full test suite stays green (15 tests, 34 assertions) after the change; owner/pm moderation tests were untouched, only a throwaway non-privileged fixture role was renamed to manager. ADR-10's structural decisions (roles as a separate table, many-to-many, level hierarchy, no permissions layer) all still hold — only the concrete role list is superseded. The frontend `Role` type (src/types.ts, currently owner|pm|qa|backend|frontend|designer) must be updated to owner|pm|manager|employee in the frontend phase, and role labels added to the dictionary.

**Alternatives considered:** A non-destructive migration that transforms existing roles in place — rejected in favor of migrate:fresh --seed because this is dev with only seed data, and fresh reseed is simpler and cleaner. Storing Bulgarian display_names in the DB — rejected to avoid duplicating translations across DB and i18n dictionary.

---

## ADR-17: Frontend authentication — token-only API client, React context, client-side route guards (realizes the deferred auth from ADR-15)

**Status:** Accepted

**Context:** ADR-15 deliberately deferred the real login flow, leaving a placeholder user in the navbar and a no-op Logout, to keep security-sensitive token work out of the UI-shell phase. This phase builds that real flow. The backend contract was confirmed by reading the actual code (not trusting the handover): `POST /api/login` returns `{ token, user }` (user with roles eager-loaded), 422 with a validation-errors body on bad credentials; `POST /api/logout` returns 204; `GET /api/user` returns the user with roles; everything else sits behind `auth:sanctum` as Bearer tokens (ADR-6). A design decision with a real trade-off had to be made up front: where the token lives on the client. Cookies (httpOnly) are the usual "most secure" answer but are ruled out by ADR-6 (no cookies/CSRF/SPA-stateful flow). In-memory is the XSS-safest remaining option but does not survive a page refresh. localStorage survives refresh at the cost of XSS exposure. A second issue surfaced during testing: the backend returns roles as objects (`{name, display_name, level, ...}`), but the frontend `Role` type and `formatRoles` expect role-name strings — a shape mismatch that broke rendering until normalized.

**Decision:** (1) A single API-client module `src/lib/api.ts` is the ONLY place that talks to Laravel and the ONLY place that touches the token. It exposes `login`, `logout`, `getCurrentUser` and internal `get/set/clearToken` helpers; components never call `fetch` to Laravel and never read/write the token directly. Bearer-only: attaches `Authorization: Bearer <token>`, never cookies, never `credentials: "include"`, never CSRF (ADR-6). localStorage access is SSR-guarded (`typeof window`). (2) The token is stored in **localStorage** under key `auth_token`. This is a conscious trade-off: cookies are excluded by ADR-6; in-memory would log the user out on every refresh; localStorage persists the session at the cost of XSS exposure, judged acceptable for an internal, behind-login tool, and contained by making `api.ts` the sole accessor. (3) `api.ts` normalizes the backend user into the frontend `User` shape (`{id, name, email, roles}`) by mapping `roles` to their `name` field, in BOTH `login` and `getCurrentUser`, so the rest of the frontend sees a clean, consistent contract identical to the old placeholder shape. (4) Auth state lives in a React context, `src/lib/auth-context.tsx` (`AuthProvider` + `useAuth`), mounted INSIDE `NextIntlClientProvider` in `[locale]/layout.tsx` (locale provider stays outermost so translations remain available to everything, including auth-aware components). On mount it calls `getCurrentUser()` once to restore the session after refresh; `isLoading` is exposed but children are always rendered (consumers decide) — the "Variant B" behavior: show nothing rather than a spinner while the initial check is in flight. (5) A reusable client guard `src/components/require-auth.tsx` (`RequireAuth`) redirects to `/login` (via the locale-aware `useRouter` from `@/i18n/navigation`) when `!isLoading && !user`, performing the redirect inside a `useEffect`, and renders children only when a user exists. The dashboard uses it; `/tools` and `/profile` will reuse it. (6) The login page `[locale]/login/page.tsx` is a client component using `useAuth().login`, showing the backend's 422 message on failure, with a submitting state; all text is translated via a new `auth.*` namespace section added to `common.json` (kept in the existing file, not a new namespace, to avoid an unverified multi-namespace loader change). (7) The dashboard stays a server component; its greeting was extracted into a small client component `src/components/dashboard-greeting.tsx` that reads the real user via `useAuth`, so the page keeps server rendering (`setRequestLocale`, `getTranslations`, static cards) while the user-dependent part is client. (8) A new semantic **error** color token was added to the design system (`--error` in `:root`/`.dark`, mapped to `--color-error` in the NON-inline `@theme` block per ADR-14), because the design system had no error color and the login error text was unreadable; login uses `text-error`. (9) The navbar now hides the nav links (Dashboard/Tools/Profile) when logged out, via `useAuth` in `NavLinksList` (covering both desktop and mobile at once); the logo stays always visible. The placeholder user (`PLACEHOLDER_USER`) is no longer imported anywhere; UserMenu shows the real user + a working Logout, or a "Sign in" link when logged out.

**Consequences:** The placeholder user area and no-op Logout flagged in ADR-15 are now wired to real auth; nothing else in the navbar's structure changed. Login, session-restore-on-refresh, logout, protected-route redirect, and the 422 error path were all verified end-to-end in a real browser. Client-side route guards are UX only, consistent with ADR-6/ADR-12 — real security stays on the backend (`auth:sanctum` + ToolPolicy); a bypassed guard reaches only an empty shell with no data. The `Role`-object-vs-string normalization in `api.ts` is the single point that absorbs the backend's richer role shape, so components stay simple. Future protected pages (`/tools`, `/profile`) reuse `RequireAuth` and the same `api.ts`; new forms reuse `text-error`. Role-filtering of nav links (the `requiredRole?` field from ADR-15) is still not implemented — links are shown/hidden by auth state only, not by role; that activates when a role-restricted section exists.

**Alternatives considered:** httpOnly cookies for the token — rejected by ADR-6 (the app is a token-only API client, not a Sanctum SPA-stateful/cookie flow). In-memory token storage — rejected because it does not survive a page refresh, forcing a re-login on every reload. A separate `auth.json` i18n namespace — deferred in favor of an `auth.*` section in the existing `common.json`, because multi-namespace loading was not verified and the existing single file is known to work; revisit if auth strings grow large. Making the whole dashboard a client component — rejected to preserve server rendering; only the greeting was extracted. Guarding routes in the layout — rejected because layouts don't re-render on client navigation, which can leave routes unprotected; the guard lives on the page/component.

---

## ADR-18: Read-only reference-data endpoints for categories and roles (prerequisite for the /tools UI)

**Status:** Accepted

**Context:** Planning the `/tools` page began with reconnaissance of the real backend rather than trusting the handover, and that reconnaissance corrected three assumptions. (1) `Tool` has no `category` column; categories and roles are many-to-many relations (`categories()`, `roles()`), eager-loaded by `ToolController` alongside `creator()`. (2) `ToolController@index` returns `paginate(15)`, so the tools response is an envelope (`{data, current_page, last_page, total, ...}`), not a bare array — the frontend must read `.data`. (3) `Tool::scopeFilter` reads exactly three query parameters: `search` (LIKE against name and description), `category` (matched against the category **slug**, not id), and `role` (matched against the role **name**, not display_name). Separately, `StoreToolRequest`/`UpdateToolRequest` accept `category_ids` and `role_ids` as arrays of ids validated with `exists:`. So creating a tool needs **ids**, while filtering the list needs **slug/name** — different identifiers for different operations. The blocking gap: the `categories` and `roles` tables exist and are already seeded (5 categories; 4 roles), but there was no route to read them — `route:list --path=api` showed only 9 routes. Without read endpoints the frontend cannot populate category/role filter dropdowns, and cannot offer the multi-selects the create/edit modal needs, because it has no way to learn which categories and roles exist or what their ids are. One ambiguity was also resolved: a tool's `roles` are the **same** four authorization roles from ADR-16 (owner/pm/manager/employee), not a separate "target audience" taxonomy.

**Decision:** Two read-only endpoints were added, deliberately kept minimal. `GET /api/categories` → `CategoryController@index` returns `Category::orderBy('name')->get(['id', 'name', 'slug'])`. `GET /api/roles` → `RoleController@index` returns `Role::orderByDesc('level')->get(['id', 'name', 'display_name', 'level'])`. Both routes live INSIDE the existing `auth:sanctum` group next to the tools routes — reference data for an internal tool has no reason to be public, and the frontend already sends Bearer automatically through `api.ts` (ADR-17), so authentication costs nothing. Both return **plain JSON arrays and are deliberately NOT paginated**, diverging from `/api/tools` on purpose: these are small, fixed reference lists feeding dropdowns, and paginating them would force `api.ts` to unwrap `.data` for no benefit. Columns are **explicitly enumerated** rather than returning whole models: `id` for `category_ids`/`role_ids` on create, `slug` for `?category=`, `name` for `?role=`, `display_name` for UI labels, `level` because it defines the hierarchy. Timestamps are excluded — they widen the frontend contract without serving it. Ordering is done on the backend (categories alphabetical for humans, roles by level descending) so the UI never has to sort. Nothing else was touched: no migrations, no seeders, no model changes, no changes to `Tool`, `ToolController`, `ToolPolicy`, or any auth code.

**Consequences:** The `/tools` phase is unblocked and can now be built complete in one pass — list, `?search=`, category/role filters, and a working create/edit modal — instead of shipping a narrow list first and widening it later. Both endpoints were verified manually with curl against a real Bearer token before any test was written (order matters: a test written against an unverified endpoint can lock in wrong behavior), confirming plain arrays, the exact column sets, and **401 without a token** — the guard was proven to bite rather than assumed from the diff looking correct. Five Pest tests in `tests/Feature/ReferenceDataTest.php` then locked the behavior in: the two 401s, the exact key set of each item, and roles ordered by level descending (verified by inserting roles out of order and asserting the returned order). Asserting the **exact** key set is intentional — if anyone later returns whole models, the test fails rather than silently widening the API. The tests create their own records and do not rely on seeded data. Full suite: 20 tests / 48 assertions green (15 pre-existing + 5 new), confirming the shared `routes/api.php` edit broke nothing in tools or auth. The 401 tests double as a regression trap against a future edit accidentally moving a route out of the `auth:sanctum` group.

**Alternatives considered:** Building a narrow `/tools` (list + search only) first and deferring these endpoints — rejected once reconnaissance showed the tables were already seeded, which made the endpoints trivial (two controllers, two routes, no migrations, no seed) and made building the list twice the more expensive path. Paginating these endpoints for consistency with `/api/tools` — rejected as cargo-cult consistency; pagination serves growing datasets, not fixed dropdown options. Leaving them public to avoid the token dance — rejected as a needless hole in the access model even though role names are not secret. Full CRUD for categories now (the developer's "Settings → Manage categories" idea) — deferred to its own later phase: it needs a policy restricting management to owner/pm plus a guarded admin UI, and building administration for data that is not yet displayed anywhere inverts the natural order.

---

## ADR-19: The /tools list — URL-driven filters, client-side fetching, and seeded dev data

**Status:** Accepted

**Context:** This is the first page in the app that renders real data. Three things had to be settled before any UI existed. First, the tools table was **empty** — discovered by curl, not assumed — so a list built against it would have shown an empty screen with no way to tell a bug from missing data. Second, the page cannot be a server component like the dashboard: the token lives in localStorage (ADR-17), which server components cannot read, so the request must originate in the browser. Third, `ToolController@index` returns a `paginate(15)` envelope and `Tool::scopeFilter` reads exactly `search`, `category` and `role`, where **category matches a slug and role matches a role name** while creating a tool needs **ids** (ADR-18) — two different identifier vocabularies that the frontend types had to keep straight.

**Decision:** (1) `ToolFactory` and an idempotent `ToolSeeder` seed 8 recognizable AI tools, attaching categories **by slug** and roles **by name** (never hardcoded ids), resolving `created_by` by email, and marking exactly one tool (Cursor) as `draft` so status behavior is exercisable. `ToolSeeder` is called last in `DatabaseSeeder`, after the role/user/category seeders it depends on — an ordering that, if wrong, would silently produce tools with no categories and no error. The seeder sets `created_by` after `Tool::create()` rather than through it, mirroring `ToolController@store`, because `created_by` is deliberately absent from the model's `#[Fillable(...)]` so no API request can forge an author. (2) Tool fields keep the backend's **snake_case** (`documentation_url`, `video_url`, `created_by`). `api.ts` already normalizes one thing — role objects to strings — because that fixes a real shape conflict; renaming fields for TypeScript aesthetics would add two-way mapping and a place for bugs with no benefit. (3) The new option type is named **`RoleOption`**, not `Role`: the existing `Role` is a role-name string union used by auth code, and `GET /api/roles` returns objects. Same word, two meanings — separate names. (4) `getTools` maps the Laravel envelope to a `ToolsPage` (`{tools, currentPage, lastPage, total}`) rather than returning `.data` alone. Pagination **controls** are deferred, but the page metadata is carried from day one so adding them later does not change the function signature; swallowing the envelope would have silently capped the UI at 15 tools. (5) Filter state lives in the **URL** (`?search=&category=&role=`), so links are shareable and a reload preserves the view. `tools-list.tsx` reads the params and re-fetches with them in the `useEffect` dependency array. (6) URL updates use `router.replace`, **not** `push` — filtering therefore does NOT create history entries, and the Back button leaves the page rather than stepping backwards through filters. This is a deliberate trade-off against the "history trap", where N filter changes require N+1 Back presses to escape a page; if reverting filters is ever wanted, a "clear filters" button is the right answer, not browser history. (7) The search input is debounced **500ms** and only queries at **3+ trimmed characters**; below that the param is *removed* rather than sent empty, so shortening the text reveals the full list instead of an unexplained empty screen. The threshold exists because the backend filter is `LIKE %term%`, where one or two characters match almost everything. The two selects update the URL **immediately** — debouncing an explicit choice reads as broken. (8) The debounce effect closes over `searchParams`, so it must depend on it; that dependency requires an equality guard (`if (queryString === searchParams.toString()) return;`) to avoid an infinite replace→rerun→replace loop. (9) `<ToolsFilters />` stays mounted in **every** state (loading, error, empty) so a filter can always be changed or cleared; the result area alone swaps. Empty-because-no-tools and empty-because-of-filters are distinct translated messages. (10) The error state shows a **translated** key, not the caught `Error.message`, whose text is English and would break localization. (11) `draft` tools are **shown**, with a badge using the existing peach token as a solid fill (`bg-secondary text-secondary-foreground`). Hiding them client-side would be dishonest — the data arrives either way — and would have to be undone when tool approval lands. No new design token was added; note that `text-secondary` (peach) and `text-text-secondary` (muted body text) are dangerously similar names for different things. (12) External tool links are plain `<a target="_blank" rel="noopener noreferrer">`; the locale-aware `Link` from `@/i18n/navigation` would prefix external URLs with the locale. `useSearchParams` is imported from `next/navigation` — it is not locale-aware and has no next-intl wrapper — while `useRouter`/`usePathname` still come from `@/i18n/navigation`.

**Consequences:** `/tools` renders 8 real tools with working search and filters, verified twice over: curl against the live API proved the filters compose as **AND** (`?category=code-assistants&role=owner` → exactly Claude, where a naive OR would have returned five — an earlier combination had accidentally identical intersection and union and proved nothing), and a 10-step Playwright MCP run covered the logged-out redirect, the 3-character threshold, param removal on clearing, immediate select filtering, both params held simultaneously, dropdowns pre-populating from a pasted URL, and a clean console. Because search matches `name` OR `description`, results can include tools whose *name* does not contain the term — intended behavior, occasionally surprising. `RequireAuth` and `api.ts` were reused unchanged, confirming the ADR-17 groundwork. **Known debt:** `tools.totalCount` uses a plain `{count}` placeholder and reads wrong for exactly one tool; ICU pluralization (`{count, plural, one {…} other {…}}`) is the fix, deliberately deferred rather than mixed into this phase. Still deferred: pagination controls (only visible past 15 tools), the create/edit modal, and category CRUD from ADR-18. On that last point a distinction was drawn: **categories** are free-form data and will get a "Settings → Manage categories" screen restricted to owner/pm, but **roles** will not become editable through the UI — they carry the `level` hierarchy that ToolPolicy depends on (ADR-16), and letting a user delete the role authorization stands on is not a feature. Roles change by migration and seeder.

**Alternatives considered:** Local `useState` for filters — rejected because shareable filtered links are a real need for an internal team tool, and retrofitting URL state onto local state is harder than starting with it. `router.push` for filters, or a hybrid pushing on select changes only — rejected per (6); a "clear filters" button is the cleaner path to the same goal. A 300ms debounce with no character threshold — rejected in favor of 500ms plus 3 characters, which suits a `LIKE`-based backend filter; 1000ms was considered and rejected as feeling stalled. Seeding tools by hand with curl — rejected because such data vanishes on the next `migrate:fresh` and nobody remembers what it was; a seeder is repeatable and the factory serves future tests. Hiding `draft` tools — rejected per (11). Adding a `--warning` token for the draft badge — deferred; the existing peach token says "different" without saying "error", and a new semantic token should wait until a genuine warning state exists.

---

## ADR-20: Departments as a third targeting dimension — flat table, slug-filtered, mirroring categories

**Status:** Accepted

**Context:** The company has 12 departments, and tools need to be targeted by department in addition to the four rank roles from ADR-16. An early idea — 20 flat roles crossing department with rank (marketing_manager, it_manager, ...) — was rejected before any code: it collapses two orthogonal dimensions (department × rank) into one name, produces a cartesian explosion, makes "a tool for all managers" ten checkboxes, and forces every new department to touch existing tools. Parsing rank out of a role name (`str_ends_with($name, '_manager')`) was also rejected — text-parsing identifiers breaks authorization silently. The chosen model keeps the four roles untouched (ADR-16 stands) and adds departments as a separate dimension. Reconnaissance of the real code — not the handover prose, which was wrong in two places — settled the details before planning: the existing pivots follow Laravel's alphabetical convention (`category_tool`, `role_tool`), so the new pivot is `department_tool` (d before t), not the handover's `tool_department`; and `Tool::scopeFilter` filters categories by slug via `whereHas`, the exact pattern departments needed. The two-vocabulary rule from ADR-18 applies again: creating/editing a tool needs **ids** (`department_ids`), filtering the list needs a **slug** (`?department=`).

**Decision:** (1) A flat `departments` table mirrors `categories` exactly — `id`, `name` (unique), `slug` (unique), timestamps — seeded idempotently via `updateOrCreate` keyed on slug. Names are Bulgarian (Магазинна мрежа, Обществени поръчки, ...); slugs are fixed English strings, so `DepartmentSeeder` iterates an explicit `slug => name` map rather than deriving slugs with `Str::slug`, which cannot transliterate Cyrillic to the required English slugs. The 12 departments: marketing, accounting, it, projects, commercial, sales, network, production, customer_support, administration, tender, telesales. (2) Departments are deliberately **flat — no parent_id, no hierarchy**. The real structure is a tree (tender, telesales, sales, and network all sit under the commercial block), but inheritance is done **manually**: a tool for the whole commercial block is explicitly checked for commercial + sales + network + telesales + tender. `parent_id` is an additive column to be added if and when tools are actually targeted at intermediate tree nodes — added against a real case, not a prediction. A recursive "department + descendants" filter is markedly more complex than `whereHas`, and buys nothing yet. (3) Tools attach to departments as a third many-to-many via the `department_tool` pivot (id PK, both FKs `cascadeOnDelete`, unique on `[tool_id, department_id]`, no timestamps — an exact mirror of `category_tool`, so `->sync()` works without `withTimestamps()`). `department_ids` is added to both Store/UpdateToolRequest (`nullable array`, each `exists:departments,id`), excluded from the mass-assign `except()` list in `ToolController`, and synced with the same `if ($request->has(...))` guard as categories/roles. `departments` is eager-loaded everywhere alongside categories/roles/creator. (4) A fourth filter block in `Tool::scopeFilter` reads `?department=<slug>` via `whereHas('departments', slug)`, mirroring the `category` block. Filters compose as **AND** across dimensions, so `?department=marketing&role=manager` intersects. (5) `GET /api/departments` → `DepartmentController@index` returns `Department::orderBy('name')->get(['id','name','slug'])` — a plain non-paginated array inside the `auth:sanctum` group, exact columns enumerated, per the ADR-18 reference-data pattern. Ordering is alphabetical by name for consistency with categories (Cyrillic collation orders acceptably). (6) A `users.department_id` nullable FK with `nullOnDelete` was also added — deleting a department nulls the user's department, never deletes the user — so users can later be assigned to a department. Seed users stay `department_id = null` for now; assigning them is a separate, unrequested change.

**Consequences:** The filter is a **search, not an access control** (ADR-19 holds): a marketing employee still sees IT tools when filtering by IT — roles and departments on a tool are a descriptive target, not a restriction. Making it "I only see mine" would be a ToolPolicy change, a separate decision, not requested. `ToolPolicy` was left untouched — store/update only sync pivots and never touch authorization. The whole write-and-filter path was proven with curl against a live Bearer token before any test was written (ADR-18's order): creating a tool with `department_ids: [1,3]` returned both departments in the response with correct pivot rows, `?department=marketing` returned exactly that tool, and the temp tool was deleted (204, cascade cleaning its pivots). Two Pest tests then locked it in: the basic slug filter, and — following the "a test that does not discriminate is useless" lesson — an AND-vs-OR test where the two answers differ (a tool matching both `department=marketing` and `category=writing`, plus a tool matching only the category, so AND returns 1 where OR would return 2). Full suite: 25 tests / 62 assertions green (23 pre-existing + 2), confirming the shared edits to `Tool`, the two Requests, and `ToolController` broke nothing. **This backend phase precedes the create/edit modal deliberately** — the modal will carry a department multi-select, and adding the third dimension after the modal was built would have meant reworking it. The frontend phase (Department type, `getDepartments` in api.ts, a third filter dropdown, dictionary entries, showing departments on the tool card) follows next.

**Alternatives considered:** 20 flat department×rank roles — rejected (cartesian explosion, ten-checkbox "all managers", every new department edits old tools). Parsing rank from role-name suffixes — rejected (text-parsing identifiers silently breaks auth). A hierarchical `departments` table with `parent_id` and a recursive filter — rejected now as building against a prediction; it wins only if tools are genuinely targeted at intermediate nodes, and `parent_id` stays additive for that day. Deriving slugs with `Str::slug` — impossible here (Cyrillic names, English slugs), hence the explicit map. Ordering `/api/departments` by seed id (the intuitive marketing-first order) — rejected for alphabetical-by-name, consistent with `/api/categories` and predictable in a dropdown. Splitting this into two commits (reference data, then filter) — rejected because departments as a feature only make sense with the filter; reference data alone is a half-feature, and one ADR describes the whole decision more coherently.

---

## ADR-21: The department filter UI — union-typed slugs, labelled card rows, and a whole-chain lesson

**Status:** Accepted

**Context:** ADR-20 built the department backend; this phase surfaces it. A translation decision came first: category names arrive from the backend in English and are shown verbatim, but department names arrive in **Bulgarian** (Маркетинг, Магазинна мрежа), because they are real organizational units with no natural English equivalents. Two options: show `department.name` as-is (zero duplication, but the English UI would display Cyrillic department names), or translate by slug through the i18n dictionary like `roles.*` (full bilingualism, at the cost of maintaining the names in three places — seeder plus both dictionaries). The developer chose translation by slug. That choice then collided with next-intl's **typed `t()`**: `t(\`roles.${role.name}\`)` compiles because `RoleOption.name` is the `Role` union, so next-intl can verify every possible expansion exists in the dictionary — but `Department.slug` was plain `string`, an infinite set, so `t(\`departments.${slug}\`)` failed to typecheck. This was initially misdiagnosed as a stale type cache; deleting `tsconfig.tsbuildinfo` changed nothing, which is what pointed at the real cause.

**Decision:** (1) A `DepartmentSlug` union type lists all 12 slugs, and `Department.slug` uses it instead of `string` — exactly the pattern `Role` already establishes. This makes `t(\`departments.${slug}\`)` typecheck and turns a mistyped slug into a compile error rather than a silently broken label at runtime. The trade-off is accepted deliberately: adding a department now touches four places (seeder, `bg/common.json`, `en/common.json`, the union), but each one guards against a different failure, and the compiler names the ones you forget. Casting the key (`as never`) was rejected — it would hide exactly the check that already protects roles. (2) `getDepartments()` in `api.ts` mirrors `getCategories()`; `ToolsQuery` gained `department`, and `getTools` forwards it, skipping empty values like the other params. Nothing in the token/`request()` layer was touched. (3) The filter is a **single-select** by design — one department narrows the list — which is a different control from the multi-select the create/edit modal will need. Value is the **slug** (matching the backend filter vocabulary), the option label goes through the dictionary, and the select updates the URL immediately with no debounce, like the existing two. `getDepartments()` joined the same `Promise.all` as categories/roles so there is one loading flag and no layout shift. (4) The tool card was **restructured**: three rows of visually identical badges (categories, departments, roles) gave the eye no way to tell the dimensions apart, so badges were replaced with **labelled rows** — a bold label above a comma-joined list, in the order Categories → Departments → Roles — plus difficulty as a single inline `Label: value` line, since it is one value and not a list. This is also markedly more compact for tools targeted at all 12 departments, where 12 badges dominated the card. Each row is guarded by `.length > 0` so no empty labels appear. (5) `line-clamp-3` truncates the description in the card: `description` is a MySQL `TEXT` column with no `max:` validation rule, so real descriptions will be long, and one long card would wreck the grid. Clamping by **lines** rather than characters was chosen because it adapts to card width and the full text stays in the data for the detail/edit views. (A `max:` rule on `description` is noted as debt for the modal phase.) (6) `ToolSeeder` now attaches departments **by slug** to all 8 seeded tools (ChatGPT and Claude to all 12, the coding tools to it/projects, Midjourney to marketing, and so on), mirroring how it already resolves categories by slug and roles by name. Applying it required `migrate:fresh --seed`, acceptable because every row in the dev database comes from a seeder.

**Consequences:** `/tools` now filters by department, and cards show which departments and roles each tool targets — roles were displayed for the first time here, having previously been invisible in the UI. **The phase's real lesson came from a bug the tooling could not catch:** the dropdown wrote `?department=` to the URL and `getTools` accepted a `department` argument, but `tools-list.tsx` — the component that actually reads the URL and calls the API — was never taught to read the param. Both ends of the chain existed and the middle was missing. `tsc` and `lint` both passed, because `department?: string` is optional and omitting it is perfectly valid TypeScript. It surfaced only in the browser, and only because the developer noticed that a tool without the IT department was still showing under an IT filter. Four edits fixed it (read the param, pass it, add it to the `useEffect` dependency array, include it in `hasActiveFilters`) — and the dependency array matters as much as the rest: without it the URL would change and the list would not refetch, which is more confusing than no filter at all. Verified in the browser: `role=manager` + `department=it` returned 4 tools before the fix and 3 after (the discriminating check — a combination where wrong and right answers differ), plus department-alone filtering, an intentionally empty result showing the *filtered* empty message rather than the *no tools at all* message, a pasted URL pre-selecting the dropdown, and the English locale rendering translated labels and department names. `npx tsc --noEmit`, `npm run lint`, and `npm run build` all clean.

**Alternatives considered:** Showing `department.name` straight from the backend — rejected by the developer in favor of dictionary translation, accepting the extra maintenance for a properly bilingual UI. Casting the translation key to bypass the type error — rejected as hiding a real check. Character-based description truncation (`slice(0, 200)`) — rejected in favor of CSS `line-clamp`, which reasons in rendered lines instead of a character count that means different things at different widths. Keeping the badge style and merely recoloring departments to distinguish them — rejected once labelled rows proved both clearer and more compact. Seeding a single throwaway tool with departments via curl just to see the UI — rejected in favor of extending `ToolSeeder`, so every future phase (the modal especially) is developed against data that actually exercises departments.

---

## ADR-22: Maximum length for tool description (max:5000)

**Status:** Accepted

**Context:** The `description` field was validated only as `required|string` (Store) and
`sometimes|string` (Update), with no upper bound. The database column is TEXT, which holds
65,535 BYTES. Cyrillic in UTF-8 takes 2 bytes per character, so the real capacity is roughly
32,000 characters, not 65,000. Without a cap, accidentally pasting an entire document into
the field is accepted silently. This debt was recorded during the departments phase and is
being closed now, before the add/edit tool modal, because the frontend needs to mirror the
same limit in `maxLength`.

**Decision:** `max:5000` characters, in StoreToolRequest AND in UpdateToolRequest.
The number is generous (~10,000 bytes of Cyrillic, six times below the column ceiling), so it
never blocks a legitimately long description, but it stops an absurd payload. The card already
truncates the description visually (line-clamp-3), so this limit is not style policing.

**Consequences:** Three new tests in tests/Feature/ToolApiTest.php, written as a boundary pair:
5000 characters → 201, 5001 characters → 422 with `description` present in errors; plus the
same check for UPDATE. The boundary pair is deliberate — a test using 10,000 characters would
pass even if the rule were `max:9999` and would prove nothing about the specific number. The
UPDATE test is equally deliberate: the rule lives in two files, and testing only CREATE would
leave a silent path for someone to remove the rule from UpdateToolRequest without any test
turning red. The frontend modal must use the same number for `maxLength`.

**Alternatives considered:**
- `max:1000` — tighter, but a real risk of hitting a legitimate description.
- `max:2000` — a middle ground with no clear advantage over 5000.
- No limit (the status quo) — rejected: the database accepts input until it blows up around
  32,000 characters with an opaque MySQL error instead of a clean 422 response.
- Frontend-only validation — rejected: trivially bypassed; security belongs on the backend.

---

## ADR-23: Add-tool form on a dedicated page, not in a modal

**Status:** Accepted — except the "category names are single-language by design" consequence,
which is **superseded by ADR-27** (names are now a JSON translation map in the database).
Every other decision in this ADR stands.

**Context:** Creating a tool needs ten inputs, including a 5000-character description and
three multi-selects (5 categories, 4 roles, 12 departments). The original plan was a modal.
Current UX guidance is consistent that complex multi-field forms belong on their own page,
and that no modal should occupy most of the screen — content that needs a full screen is a
page, not an overlay. Desktop modal patterns also break on mobile, where a virtual keyboard
fights an overlay. Reading the catalogue on a phone is a realistic scenario; authoring a
tool with a long description is a deliberate at-the-desk task, so the form does not need to
excel on mobile but must not be broken there either.

**Decision:** A dedicated route `/tools/new`, constrained to max-w-3xl. The form component
is deliberately mode-agnostic: it accepts `initialValues?: ToolPayload` and
`onSubmit: (payload) => Promise<unknown>` and knows nothing about create vs edit. A thin
client wrapper (`new-tool-form.tsx`) supplies `createTool` and the redirect. Multi-selects
are plain checkbox groups (`checkbox-group.tsx`, reused three times, text-sm, 3 columns for
categories and departments, 2 for roles, with select-all/clear only for the 12 departments).
Cancel uses `window.confirm` and only prompts when values differ from the initial state.
Success is signalled via a `sessionStorage` flag read once by the list.

**Consequences:**
- Phase 2 (edit) is one page plus one API function, with ZERO changes to the form. This was
  the main reason for the mode-agnostic split.
- `ToolPayload` marks every field required, nullable ones as `| null` rather than optional.
  This is deliberate: the controller uses `->safe()`, so an OMITTED field is not updated.
  If clearing a documentation link merely omitted the field, the old value would silently
  survive. The type system now forces the form to always send an explicit null.
- `tool-form.tsx` exceeds the 200-line guideline (~240). Accepted knowingly: the remaining
  duplication is seven label/input/error blocks, and extracting a wrapper was judged not
  worth the indirection for this file.
- Laravel returns validation messages in English, so a Bulgarian UI shows English field
  errors. Backend localisation is deferred; recorded as debt.
- Category names are NOT translated (unlike departments and roles, which go through `t()`
  on fixed slugs). Categories are free-form user data and will become editable via
  Settings, so a typed dictionary cannot cover them. Their names are therefore
  single-language by design and currently seeded in English.
- The list has no `orderBy`, and pages hold 15 tools. Beyond 15 records, a newly created
  tool lands on page 2 and will NOT be visible after the redirect, while the success banner
  still claims it was added. Known limitation; fixed by sorting newest-first or by adding
  pagination controls.

**Alternatives considered:**
- Modal with a larger max-width — rejected; contradicts the guidance above and adds focus
  trap, Escape handling and scroll lock for no benefit.
- Native `<select multiple>` — rejected; hostile on desktop, unusable on touch.
- Combobox with chips — rejected; either ~150 lines of hand-rolled accessibility or the
  project's first UI dependency.
- Carrying active filters through to the form and back after save — deliberately NOT built.
  Cancel returns via `router.back()`, which preserves them for free. After a successful
  save, restoring filters can be actively worse: adding a Marketing tool while filtered to
  IT would return the user to a list where the new tool is absent. The right behaviour is
  a question for real users, so no three-link chain is built against a guess.
- A toast context provider — rejected for now; it would touch `[locale]/layout.tsx` mid-phase
  for two call sites. Worth revisiting once there are more.
- `eslint-disable` or `queueMicrotask` for the `set-state-in-effect` warning on the banner —
  rejected. The rule was correct: the flag is read once and never changes, so a lazy
  `useState` initializer removes the extra render instead of hiding it.

---

## ADR-24: Tool editing at /tools/[id]/edit

**Status:** Accepted

**Context:** With creation in place (ADR-23), tools needed an edit path. The form component
was deliberately built mode-agnostic in the previous phase specifically so that this phase
would not have to touch it.

**Decision:** A dynamic route `/tools/[id]/edit` with a thin client wrapper
(`edit-tool-form.tsx`) that loads the tool, converts it to a `ToolPayload` and supplies
`updateTool` as the submit handler. `ToolForm` itself was NOT modified in this phase —
the mode-agnostic split paid off exactly as predicted.

An edit link appears on the tool card for the author, or for owner/pm, mirroring
ToolPolicy. This is UX only: the real guarantee is the backend policy, which is already
covered by tests. Hiding the button is courtesy, not security.

**Consequences:**
- `toPayload()` maps relations to id arrays (`categories` → `category_ids`, etc). This is
  the read/write asymmetry: reads return objects, writes want ids. Getting it wrong would
  not error — the form would open with empty checkboxes and a save would silently wipe
  every relation via `sync()`. Verified in the browser by REMOVING a role and confirming
  the removal persisted; a form that only sent additions would have looked identical on a
  pure add test.
- `ToolForm` must not render before the tool has loaded: it reads `initialValues` once, in
  a `useState` initializer, so an early render would leave it permanently blank.
- The page is a server component and cannot fetch the tool, because the auth token lives in
  localStorage (ADR-6). The heading therefore moved INTO the client component, which costs
  a brief flash of "Loading…" before the tool name appears. Accepted as the only way to
  show the name at all.
- The success banner key changed from `tool_created` ("1") to `tool_saved`
  ("created" | "updated"), keeping ONE mechanism instead of two parallel flags. The banner
  text uses an explicit ternary, NOT a template key `t(\`tools.form.${value}\`)`, which
  would not compile against next-intl's typed `t()` (the TS2345 trap from ADR-21).
- Deletion is deliberately NOT part of this phase: it is irreversible and deserves its own
  decisions (soft delete, pivot cleanup, confirmation).

**Alternatives considered:**
- Passing the already-loaded `Tool` from the card into the page — rejected; the edit URL
  must work on its own (refresh, shared link).
- A `mode: "create" | "edit"` prop on the form — rejected in ADR-23, and this phase
  confirmed the choice: zero changes to the form were needed.
- Sending only changed fields on update — rejected. `UpdateToolRequest` uses `sometimes`,
  so an omitted field is left untouched; sending the full payload makes behaviour
  predictable and is why `ToolPayload` has no optional fields.

---

## ADR-25: Rewriting CLAUDE.md as a verified behavioural contract, plus docs/pitfalls.md
**Status:** Accepted
**Context:** CLAUDE.md had drifted from the code. An audit against the actual files found
four defects, three of which would have caused real errors. The role list read
`(owner, backend, frontend, pm, qa, designer)` — an early guess that was never corrected —
while `RoleSeeder` defines exactly four roles with numeric levels: owner (100), pm (60),
manager (40), employee (20). The next planned feature (draft/publish) is entirely about
roles, so an agent following the file would have written policies against role names that
do not exist. The Commands section contradicted itself, banning `npm` on the host eight
lines above instructing `npm run dev` from `frontend/`. The definition of done required
"frontend tests" to pass, but `frontend/package.json` has no test script and no test
framework — an unsatisfiable rule, which is worse than no rule, because it teaches that
the rules in this file are aspirational. And i18n was absent entirely, despite
`messages/bg/common.json` being the TYPE SOURCE for `t()` and the most frequent source of
build failures in this project.
Every one of these was found by reading the actual file, not by reasoning about it.
**Decision:** CLAUDE.md rewritten (89 lines) around what changes behaviour, ordered by
importance rather than by convenience for a human reader. Three things are new.
First, an explicit *stop and ask* list of eight decision categories, chosen by
reversibility and ownership rather than by importance: schema changes beyond a nullable
column, anything touching permissions, new external dependencies, changes to an existing
API contract, product behaviour, introducing a new KIND of thing, revising a recorded ADR,
and any ambiguity in the task. Placing a file inside an existing pattern is explicitly NOT
in the list — a stop-and-ask list only works while every stop means something.
Second, an i18n section, given the trap's cost history.
Third, a rule that the agent appends to `docs/decisions.md` and `docs/pitfalls.md` itself,
but never edits CLAUDE.md on its own initiative: it proposes replacement text and waits.
An agent free to rewrite its own rules is not bound by them.
A new `docs/pitfalls.md` holds the accumulated project-specific traps, previously carried
only in session notes. It is pointed to, not inlined, so it can grow without spending
CLAUDE.md's attention budget.
No frontend test framework was added. Installing one is an ask-first decision and deserves
its own phase and its own ADR, rather than arriving as a side effect of a documentation fix.
**Consequences:**
- The definition of done now states what is actually verifiable: full Pest suite, plus
  `tsc --noEmit`, `lint`, `build`, plus a real browser check for UI work. It also states
  explicitly that no frontend test framework exists and that installing one requires asking.
- The file grew from 55 to 89 lines while removing content, because descriptive lines were
  traded for behavioural ones. Anything an agent can learn in a second from the filesystem
  was dropped.
- `docs/pitfalls.md` carries two verification commands: a dictionary sync check, and a
  file-size listing sorted by length. The size ceilings in CLAUDE.md are a request; the
  command is a measurement. Rules that are only requests decay silently — that is precisely
  how the role list stayed wrong.
- Staleness is now an explicit obligation: at the end of a phase, anything learned that
  contradicts CLAUDE.md, pitfalls, or an ADR must be reported rather than worked around.
- Local agent and permission configuration lives outside the repository and is intentionally
  not described here.

---

## ADR-26: Category names in Bulgarian; English slugs frozen as the identifier

**Status:** Accepted — **extended by ADR-27**: `name` is now a JSON translation map, so the
"English UI shows Bulgarian category names" consequence below no longer holds. The frozen slugs,
the explicit `slug => name` map and the `updateOrCreate` rename-in-place mechanism are unchanged.

**Context:** The five categories were seeded in English (Code Assistants, Image Generation,
Writing, Data & Analytics, Productivity). ADR-23 already established that category names are
**single-language by design** — unlike departments and roles, they are free user data that
will become editable through a Settings screen, so a typed i18n dictionary cannot cover them.
That ADR recorded the names as "currently seeded in English". This phase fills in the
intended value: Bulgarian. The decision recorded in ADR-23 is unchanged; only the data is.

Reconnaissance of the real `CategorySeeder` corrected the premise the task started from.
The seeder does not use `firstOrCreate` keyed on name — it uses
`updateOrCreate(['slug' => $slug], ...)`, keyed on slug, which looks safe. But the slug was
**derived from the name** with `Str::slug($name)`. A seeder keyed on a derived value is
effectively keyed on the value it derives from: changing the names would have changed every
key, producing five NEW rows and leaving the original five orphaned, still holding all 18
`category_tool` pivot rows. The failure would then have propagated silently, because
`ToolSeeder` attaches categories by **hardcoded English slug** via
`Category::whereIn('slug', ...)` — with new slugs that returns an empty collection and tools
get no categories and no error, the exact shape of the seeder-ordering trap already recorded
in `docs/pitfalls.md`.

**Decision:** (1) `CategorySeeder` iterates an explicit `slug => name` map instead of deriving
slugs, mirroring `DepartmentSeeder` exactly — the pattern ADR-20 established for the same
reason (`Str::slug` cannot produce the required English slugs from Cyrillic names).
(2) The five English slugs are **frozen unchanged**: `code-assistants`, `image-generation`,
`writing`, `data-analytics`, `productivity`. The slug is the wire vocabulary — ADR-18's "two
alphabets" rule — spoken by `ToolSeeder`, by `Tool::scopeFilter`'s `?category=` parameter, and
by the frontend filter's option values. It is never shown to a user, so translating it buys
nothing and breaks three things. (3) Names become Bulgarian: Асистенти за код, Генериране на
изображения, Писане и текстове, Данни и анализи, Продуктивност. (4) `updateOrCreate` keyed on
slug is retained deliberately — it is precisely what renames the five existing rows **in
place**, preserving their ids and pivot rows. Applied with
`sail artisan db:seed --class=CategorySeeder`; **no `migrate:fresh`**, so no tokens were
destroyed and nobody had to log in again. (5) Nothing else was touched: no migration, no
model, no controller, no test, no frontend file.

**Consequences:**
- Verified by snapshotting the table before and after: the same five ids, the same five slugs,
  the same per-category tool counts (3/2/4/3/6) and the same 18 `category_tool` rows, with only
  `name` changed. Zero orphans.
- Zero frontend changes were needed, which is itself the confirmation that ADR-19/ADR-21 got
  the contract right: `tools-filters.tsx` reads `category.slug` for the value and
  `category.name` for the label, both straight from `/api/categories`, and no category slug is
  hardcoded anywhere in `frontend/`.
- Zero test changes were needed. No test seeds; `ReferenceDataTest` and `ToolApiTest` create
  their own `Category` rows. Full suite green at 28 tests / 67 assertions.
- **The English UI now shows Bulgarian category names.** This is the accepted consequence of
  ADR-23, confirmed in the browser: on `/en` the chrome, roles and departments render in
  English (dictionary-translated on fixed slugs) while categories stay Cyrillic (free data).
  The only alternative would be a `name_en` column — a schema change and a separate decision.
- `CategoryController` orders by `name`, so the dropdown order changed to Bulgarian
  alphabetical (Асистенти, Генериране, Данни, Писане, Продуктивност). MySQL's collation sorts
  Cyrillic correctly, as ADR-20 already found for departments.
- **Open question, deliberately deferred to the Settings phase:** once categories are editable
  in the UI, `updateOrCreate` will overwrite a user-renamed category back to the seeded name on
  the next `db:seed`. The right answer then is `firstOrCreate` (create if missing, never
  overwrite). Not changed now — `updateOrCreate` is the mechanism this rename depends on.
- Pre-existing debt confirmed, not introduced: `pint --test` reports `no_unused_imports` for
  `CategorySeeder`, but it reports the same for the HEAD version of that file and for the
  untouched `DepartmentSeeder` and `ToolSeeder`. The unused `WithoutModelEvents` scaffold
  import has been in all three since they were written. Left alone as unrelated cleanup.

**Alternatives considered:**
- Bulgarian names AND Cyrillic-derived slugs — rejected. It requires either `migrate:fresh`
  (wipes every token) or a data migration, breaks `ToolSeeder`'s hardcoded slugs, invalidates
  existing filter URLs, and gains nothing, since slugs are invisible to users.
- A one-off data migration to rename the rows — rejected as unnecessary: `updateOrCreate` keyed
  on an unchanged slug already renames in place, and a migration would add a permanent file to
  express a one-time dev-data edit.
- Translating category names through the i18n dictionary like departments — impossible by
  construction (ADR-23): a typed dictionary cannot cover rows a user will create at runtime.

---

## ADR-27: Category names as a JSON translation map in the database; the frontend picks the language

**Status:** Accepted

**Context:** ADR-23 recorded that category names are **single-language by design** — unlike
departments and roles, which are translated through the typed i18n dictionary on a fixed slug,
categories are free user data that a later Settings phase will make editable, so a compile-time
dictionary cannot cover rows a user creates at runtime. ADR-26 then filled in the single
language (Bulgarian) and noted the accepted consequence out loud: **the English UI shows
Bulgarian category names**, with "the only alternative would be a `name_en` column — a schema
change and a separate decision". This phase is that decision. Translations live in the
database, not in language files, precisely because the categories will become editable through
the UI; a dictionary key cannot be created by a user at runtime.

Reconnaissance corrected the task's own premise. The task specified selecting the value with
`app()->getLocale()`, but `app()->getLocale()` in this project **always returns `en`**:
`backend/.env` sets `APP_LOCALE=en`, `bootstrap/app.php` has an empty `withMiddleware()`, and
`api.ts`'s `request()` sends only `Accept`, `Content-Type` and `Authorization`. The frontend's
locale lives in the URL (ADR-13, `localeDetection: false`) and never reaches Laravel. A literal
implementation would therefore have shown English names in the Bulgarian UI — both ends of the
chain present, the middle missing, exactly the failure shape recorded as process gotcha #1 in
`docs/pitfalls.md`. This was reported before any code was written and the developer chose where
the choice should happen.

**Decision:** (1) `categories.name` becomes a **JSON** column holding a translation map,
`{"bg": "...", "en": "...", "fr": "..."}`, cast with plain Laravel `'name' => 'array'` in
`protected function casts()` (the style `User` already uses). **No `spatie/laravel-translatable`
and no `category_translations` table** — for five rows a JSON column is enough, and both
alternatives are an ask-first dependency/schema decision for no gain here.
(2) **The frontend picks the language, not the backend.** `/api/categories` (and the
`categories` relation embedded in every tool) returns `name` as the whole map; a new
`frontend/src/lib/localized-name.ts` exposes `localizedName(name, locale)` which reads the
locale from next-intl (`useLocale()`, i.e. from the URL) and **falls back to `bg`** for a
missing translation. This keeps ONE source of truth for "the current language" — the URL. The
rejected alternative would have had to mirror the frontend locale into Laravel via a header and
a new middleware, creating a second source that must never drift, plus either an API Resource
layer (which this project does not have) or an override of the model's serialization to keep
`name` a string on the wire while it is an array in PHP.
(3) The **migration converts existing data in place** rather than dropping it: drop the unique
index, widen `name` to `TEXT`, `UPDATE categories SET name = JSON_OBJECT('bg', name)`, then
`MODIFY name JSON NOT NULL`. Raw statements are deliberate — MySQL cannot rewrite a column's
contents and its type in one `Schema::table()` call — and safe, because the project is MySQL
only (ADR-5) *including tests*: `phpunit.xml` overrides `DB_DATABASE`, never `DB_CONNECTION`.
The `TEXT` step is not cosmetic: the JSON wrapper adds ~10 characters and would truncate a name
near the `VARCHAR(255)` ceiling. `down()` reverses it through a temporary column, because
assigning `JSON_UNQUOTE(...)` back into a JSON column would store a quoted JSON string scalar.
(4) **The `categories_name_unique` index is dropped**: MySQL cannot place a UNIQUE index on a
JSON column, and the developer declined a generated column just for this. `slug` remains unique
and is the real identifier (ADR-26); nothing in the code validated `name` for uniqueness — not a
Form Request, not a controller.
(5) **Ordering:** `CategoryController` now orders by **`slug`**. The requested "order by the
value for the current language" is impossible in SQL under decision (2) — the backend does not
know the locale — so the backend guarantees a deterministic order and the **frontend** sorts the
dropdown and the checkbox group by the displayed name with `localeCompare(locale)`
(`sortByLocalizedName`). This is a deliberate, narrow deviation from ADR-18's "ordering is done
on the backend so the UI never has to sort": it is the only place the locale exists.
(6) The five English slugs stay frozen and `updateOrCreate(['slug' => $slug], ...)` is retained,
so ADR-26's rename-in-place mechanism keeps working; `CategorySeeder` now carries all three
languages. French is **data only** — adding `fr` to the UI switcher is a separate future task,
so `routing.locales` is untouched.

**Consequences:**
- **Existing data survived, proven by snapshot rather than by inspection** (the ADR-26 lesson):
  the same five ids, the same five slugs, the same per-category tool counts (3/2/4/3/6) and the
  same 18 `category_tool` rows, before and after both the migration and the seed. Zero orphans.
  Applied with `migrate` + `db:seed --class=CategorySeeder`; **no `migrate:fresh`**, so no tokens
  were destroyed.
- `frontend/src/types.ts` gains `LocalizedName = { bg: string; en?: string; fr?: string }` and
  `Category.name` is no longer a `string`. `bg` is **required** because it is the fallback; the
  other languages are optional because a category may genuinely lack a translation. This turned
  the type into the thing that *found* all three display sites: `tsc` failed at `tool-card.tsx`,
  `tools-filters.tsx` and `tool-form.tsx` and nowhere else, which is the check ADR-21's
  "chain with a missing middle" bug did not have.
- The `fr` values are in the database and reachable, but no UI renders them yet. Activating
  French is: add `"fr"` to `routing.locales` + a full `messages/fr/common.json`. The category
  names are already waiting; nothing in this phase needs revisiting.
- **The locale choice now lives in frontend code, where there is no test framework**
  (CLAUDE.md: installing one is an ask-first decision, deliberately not taken here). So the
  Pest tests assert what the backend actually guarantees — that the full map arrives decoded and
  unmodified — and the fallback was proven **in the browser** instead: a throwaway category with
  only `bg` rendered its Bulgarian name under `/en` (no blank label, no crash), then was deleted.
  This asymmetry is the real cost of decision (2) and is recorded here rather than glossed over.
- Three new Pest tests, each proven to fail without the change rather than assumed to
  discriminate: the translation map arrives intact (fails without the cast), a partial map is
  returned as-is with no server-side substitution (fails without the cast), and ordering is by
  slug — the last one inserted in neither slug nor name order, so it fails both against
  insertion order and against name-alphabetical order. Reverting the cast and the `orderBy`
  one at a time made all three go red, which is how it is known they test something.
- Five existing test fixtures that created a category with a string `name` were updated to
  arrays. Full suite: **31 tests / 74 assertions green** (28 pre-existing + 3).
- Verified in a real browser on both locales, which is the only check that can prove per-locale
  loading: `/bg/tools` shows Асистенти за код / Генериране на изображения / …, `/en/tools` shows
  Code Assistants / Image Generation / …, each dropdown alphabetical **in its own language**, the
  `?category=<slug>` filter unchanged (`code-assistants` → Claude, GitHub Copilot, Cursor), and
  the edit form pre-checking the right boxes with localized labels. Console clean.
  `npx tsc --noEmit` and `npm run lint` clean.
- Pint reports `no_unused_imports` on `CategorySeeder`, as it does for the four untouched
  sibling seeders and for the HEAD version of the same file — pre-existing scaffold debt
  confirmed, not introduced (the ADR-26 finding, re-confirmed).
- **The open question from ADR-26 is now larger, not smaller.** Once categories are editable in
  Settings, `updateOrCreate` will overwrite user-edited names on the next `db:seed` — and it will
  now overwrite *three languages at once*. `firstOrCreate` remains the answer for that phase.
- A second question the Settings phase must answer: whether `en`/`fr` are **required** when a
  user creates a category. Nothing enforces the map's shape today — there is no Form Request for
  categories, because there is no write endpoint. The frontend fallback means a missing
  translation degrades quietly rather than breaking, which is the right default for now but is
  not validation.

**Alternatives considered:**
- Backend resolves the locale via `Accept-Language` + a middleware, returning `name` as a plain
  string — rejected by the developer. It keeps the API shape and matches the task's literal
  wording, but needs a second source of truth for the current language, a new middleware, and
  either an API Resource layer or a serialization override; and it would leave the future
  Settings edit form unable to see the other translations it must edit.
- The same, with a `?locale=` query parameter — rejected: the parameter must be threaded through
  every call site (`ToolsQuery`, `getCategories`, `getTools`), which is more places to forget
  than one header.
- `spatie/laravel-translatable` — rejected by the developer (and an ask-first dependency).
- A `category_translations` table — rejected: correct at scale, pure overhead for five rows.
- A `name_en` column (ADR-26's suggestion) — rejected implicitly by choosing JSON: it does not
  generalise, and a third language would mean a third migration.
- A generated column with a UNIQUE index over `name->>'$.bg'` — rejected by the developer:
  complexity in the migration to restore a constraint that no code ever relied on.
- Sorting on the backend by `name->>'$.bg'` — rejected: it is deterministic but pins the order to
  Bulgarian in every language, and the frontend can order correctly for free.

---

## ADR-28: Category write endpoints — owner/pm policy, authorization before validation, deletion blocked while in use

**Status:** Accepted

**Context:** ADR-18 deferred category CRUD to "its own later phase: it needs a policy restricting
management to owner/pm plus a guarded admin UI". This is the backend half of that phase, and the
first write endpoint in the project outside tools. Reconnaissance of the real code established the
starting point: the only role check in the whole backend was `ToolPolicy::update` (`created_by ==
user || hasRole('owner') || hasRole('pm')`); there was no role middleware at all (`bootstrap/app.php`
has an empty `withMiddleware()`); `User::hasAtLeastLevel()` and `highestRole()` exist but are called
nowhere; and there was no `CategoryPolicy`, no Form Request for categories and no write route. The
developer settled the product rules up front: owner and pm get full CRUD, manager and employee get
403 on every write, deleting a category that tools still use is refused, `bg` is mandatory in the
translation map while `en`/`fr` are optional, unknown language keys are rejected, and the slug is
validated for format and frozen after creation.

**Decision:** (1) `CategoryPolicy` with `viewAny`/`view` open to any authenticated user (the filter
dropdown and the tool form need the list, ADR-18) and `create`/`update`/`delete` restricted to
`hasRole('owner') || hasRole('pm')` — **explicit role names, mirroring ToolPolicy (ADR-12)**; the
`level` helpers stay unused on purpose. (2) **Authorization lives in the Form Request's `authorize()`,
not in the controller body**, for `store` and `update`. This is a deliberate deviation from
`ToolController`'s `$this->authorize(...)` pattern and it buys something testable: `authorize()` runs
BEFORE validation, so a manager gets 403 whatever they send, where the controller-body pattern would
answer 422 first and hand the validation rules to a user who may not write at all. `destroy` has no
Form Request and authorizes in the controller. (3) The translation map is validated with
`'name' => ['required', 'array:bg,en,fr']` — Laravel's keyed `array` rule rejects unknown languages,
which would otherwise be stored and never rendered, since the frontend reads only those three.
`name.bg` is `required` because it is the fallback the frontend falls back TO (ADR-27); `name.en` and
`name.fr` are `['sometimes', 'string', 'filled', 'max:255']`, where `filled` is doing real work —
`""` is a valid string and would render as a blank label instead of falling back to Bulgarian.
`max:255` per language mirrors the original `VARCHAR(255)` and `StoreToolRequest`'s `name` rule; the
column is JSON now, so without a rule an entire document is accepted silently (the ADR-22 problem).
(4) The slug is validated with `regex:/^[a-z0-9]+(-[a-z0-9]+)*$/` plus `unique`. It is the wire
vocabulary (ADR-26), travelling in `?category=<slug>`; Cyrillic, spaces or capitals produce a filter
URL that looks fine and matches nothing, and `Str::slug` cannot derive it from a Bulgarian name, so
it is typed by hand and the format must be enforced. (5) The slug is **immutable after creation**:
`UpdateCategoryRequest` marks it `prohibited`, so an attempt to change it is a loud 422 rather than a
silently dropped field. Saved filter URLs stay valid and `CategorySeeder`'s `firstOrCreate` key stays
stable. (6) `name` is `required` on update, not `sometimes`: it is one JSON value, so a partial map
REPLACES the stored one and `{"bg": "..."}` would silently drop the English and French translations —
the same reasoning as `ToolPayload` having no optional fields (ADR-23). (7) `destroy` **refuses with
422 and the count** while tools use the category. The `category_tool` foreign keys cascade, so a
delete would quietly detach the category from every tool with no trace; `ValidationException::
withMessages` keeps the response shape identical to every other 422, which `api.ts` already handles.
No soft delete. (8) `store` and `update` return `$category->only(['id', 'name', 'slug'])` — the exact
shape `index` returns, so a category looks the same everywhere and timestamps do not leak into the
contract (ADR-18's enumerated-columns rule). (9) `CategorySeeder` switches from `updateOrCreate` to
**`firstOrCreate`**, closing the open question recorded in ADR-26 and enlarged in ADR-27: with
categories editable in Settings, `updateOrCreate` would overwrite an admin's renamed category — all
three languages at once — on the next `db:seed`.

**Consequences:**
- 28 new Pest tests in `tests/Feature/CategoryAdminTest.php`; full suite **59 tests / 150 assertions**
  green (31 pre-existing + 28).
- **Every guarantee was proven to go red on purpose**, one mutation at a time, rather than assumed to
  discriminate: opening the policy to everyone failed exactly the 7 authorization tests; relaxing the
  `prohibited` slug rule failed only the immutability test; `array` instead of `array:bg,en,fr` failed
  only the unknown-key test; removing the tool-count guard failed only the blocked-delete test. Each
  mutation broke its own test and nothing else.
- The 403-tests assert that **nothing was written**, not merely the status code — hiding a menu is not
  a guard, and neither is a status code with a side effect behind it.
- One test only passes because of decision (2): a manager sending an invalid payload gets **403, not
  422**. Under the controller-body pattern it goes red. That test is the reason the deviation is worth
  its inconsistency.
- The name-length rule is a **boundary pair** (255 accepted, 256 rejected), per the ADR-22 lesson: a
  10,000-character test would also pass against `max:9999`. The slug-format rule is likewise a pair —
  seven invalid forms rejected AND a valid hyphenated slug accepted, so a regex that rejects
  everything cannot pass.
- Verified with curl against the live API and a real Bearer token before this was written: employee
  403 (with a valid payload and with junk), owner 201 → 200 rename → 422 on a slug change → 422 on
  deleting a category with 3 tools, naming the 3 → 204 deleting the empty throwaway.
- **`firstOrCreate` was proven, not assumed**: category 1 was renamed through the API, `db:seed
  --class=CategorySeeder` was re-run, and the admin's name survived. The dev database was then
  restored and snapshotted — the same 5 ids, the same 5 slugs, the same per-category tool counts
  (3/2/4/3/6) and the same 18 `category_tool` rows as in the ADR-26 and ADR-27 snapshots.
- The seeder can no longer change a name in a database where the row already exists. That is the
  deliberate trade: seed authority given up in exchange for not destroying user data.
- **Debt handed to the frontend phase:** the 422 for a blocked delete carries an English prose message
  with the count embedded in it, so a Bulgarian UI cannot localise it — the same debt ADR-23 recorded
  for all Laravel validation messages. If the Settings UI needs a translated "used by N tools", the
  count has to travel as data, which is an API-shape decision for that phase.
- Pint reports `no_unused_imports` on `CategorySeeder`, exactly as it does for the HEAD version of the
  same file — pre-existing scaffold debt re-confirmed, not introduced (pitfalls #11).

**Alternatives considered:**
- Authorizing in the controller body like `ToolController` — rejected per (2); consistency loses to a
  guard that bites before validation, and the difference is provable in a test.
- A `role:owner,pm` route middleware instead of a policy — rejected: the project has no middleware
  alias and no role middleware at all, so this would introduce a second authorization mechanism
  alongside policies for one resource.
- Reviving `hasAtLeastLevel(60)` for "owner or pm" — rejected. It is terser but it silently grants the
  right to any future role seeded at level 60 or above, and ADR-12 chose explicit names for exactly
  that reason.
- Soft-deleting categories — rejected by the developer; blocking is honest and needs no schema change.
- Letting the delete cascade — rejected: tools would lose a category with no error and no record.
- An editable slug — rejected by the developer; a renamed slug breaks saved `?category=` URLs and
  makes the next `db:seed` recreate the original category as a NEW row, since `firstOrCreate` would
  not find the old key. The cost is that a typo'd slug cannot be corrected through the UI while any
  tool uses the category.
- Auto-generating the slug from the Bulgarian name — impossible by construction (ADR-26): `Str::slug`
  cannot transliterate Cyrillic into the required English slug.

---

## ADR-29: The Settings section — role-filtered navigation, a reusable role helper, and RequireRole

**Status:** Accepted

**Context:** This is the frontend groundwork for the Settings area and the **first role-restricted
section in the app**. ADR-15 built the navigation with a `requiredRole?: Role` field on every nav
entry and then left it unset on all of them, because no section was role-restricted yet; ADR-17
repeated that links are shown or hidden by auth state only, never by role. Reconnaissance confirmed
the field was still dead: `requiredRole` appeared exactly twice in `src/`, both times in its own
declaration, and `navbar.tsx` mapped `NAV_LINKS` without filtering. The only role check anywhere in
the frontend was inline in `tool-card.tsx` — `user.roles.includes("owner") || user.roles.includes("pm")`
— a hand-copy of `ToolPolicy` with no shared helper. The developer settled the behaviour: manager and
employee are redirected to the dashboard rather than shown a 403 screen, and Settings is a plain nav
link to a page with two sections, not a dropdown.

**Decision:** (1) **Dictionary keys went in first, as their own step** (`nav.settings` and a
`settings.*` section in both locales), before any code referenced them — `messages/bg/common.json` is
the type source for `t()`, so the reverse order fails to compile. Both dictionaries verified in sync
at 99 keys each. (2) A new `src/lib/roles.ts` holds `ADMIN_ROLES = ["owner", "pm"]` and
`hasAnyRole(user, roles)`. One place now names the administrator pair the backend policies check
(ToolPolicy ADR-12, CategoryPolicy ADR-28), instead of the role names being retyped at each call site.
(3) `NavLink.requiredRole?: Role` becomes **`requiredRoles?: readonly Role[]`** — a list, because the
admin sections are open to owner OR pm, which a single-role field cannot express. `navbar.tsx` filters
on it; both ends of the chain were written together, since a field that nothing reads is exactly the
"chain with a missing middle" from pitfalls #1. (4) `RequireRole` (`src/components/require-role.tsx`)
renders children only for a signed-in user holding one of `roles`, redirecting a signed-out visitor to
`/login` and a signed-in one without the role to the dashboard. It **covers the signed-out case itself
rather than requiring a `RequireAuth` wrapper**, so a page cannot end up half-guarded by using only one
of the two. (5) `/settings` is a server component following the `/tools` pattern (`setRequestLocale`,
`getTranslations`), wrapped in `RequireRole roles={ADMIN_ROLES}`, rendering the two section headings.
Neither section is a link yet, because neither screen exists. (6) `tool-card.tsx` now calls
`hasAnyRole(user, ADMIN_ROLES)` instead of its inline pair.

**Consequences:**
- All frontend role checks are **UX only** and this is stated in the code, not just in an ADR. The
  guard is the backend policy: a bypassed redirect reaches a shell whose API calls answer 403, which
  ADR-28's tests already prove.
- Verified in a real browser across **both roles that must not see it and both locales**, which is the
  only check that discriminates: owner on `/bg` sees the link and the page renders
  Настройки / Категории / Потребители; **employee** does not see the link, and typing `/bg/settings`
  directly lands on the dashboard; **manager** on `/en` likewise, redirected to `/en`; owner on `/en`
  sees Settings / Categories / Users. Console clean — 0 errors, 0 warnings across the whole run.
- `npx tsc --noEmit` and `npm run lint` clean.
- The `requiredRoles` rename touched every declaration site at once; `tsc` would have caught a missed
  one, since the old name no longer exists on the type.
- **Carried forward, recorded here because the project keeps no separate backlog file:**
  1. `ToolController` still authorizes in the controller body, so a non-admin sending an invalid tool
     payload gets a 422 listing the validation rules before the 403. The developer's decision is that
     ADR-28's Form Request placement is the direction of travel and `ToolController` should be aligned
     to it later — deliberately not done now, to keep this phase's diff to its own subject.
  2. CLAUDE.md's Architecture section requires business logic in `app/Actions/` or `app/Services/`.
     **Neither directory exists**, and `ToolController` and `CategoryController` both hold their logic
     inline. The rule is stale relative to the code. Creating those directories is an ask-first "new
     kind of thing", so nothing was created and CLAUDE.md was not edited. To be settled deliberately
     after the categories UI phase: either the code moves to the contract or the contract to the code.
  3. ADR-28's blocked-delete 422 carries an English sentence with the tool count inside it, so a
     Bulgarian UI cannot translate it. If the categories screen needs a localised "used by N tools",
     the count has to travel as a separate field in the response — an API-shape decision for that
     phase, not resolved here.

**Alternatives considered:**
- Keeping `requiredRole` singular and duplicating the Settings entry per role — rejected; it would
  render the link twice for a user holding both roles.
- A `canManageSettings(user)` predicate instead of a roles list — rejected: `NavLink` would then carry
  a function instead of data, and the nav table stops being declarative.
- Composing `<RequireAuth><RequireRole>` at every call site — rejected per (4); two wrappers that must
  both be remembered is a guard waiting to be half-applied.
- A dedicated 403 page for a signed-in user without the role — rejected by the developer; redirecting
  matches what `RequireAuth` already does, and one behaviour is easier to reason about than two.
- Making the two sections links now — rejected: they would be 404s until their phases land.

---

## ADR-30: `tools_count` on GET /api/categories, so the Settings screen can disable an impossible delete

**Status:** Accepted

**Context:** ADR-28 blocks deleting a category that tools still use, answering 422 with an English
sentence carrying the count. ADR-29 recorded the consequence as an open question for the categories
UI phase: a Bulgarian interface cannot translate that sentence, and — worse — the admin only learns
the category is in use *after* pressing a button that was never going to work. The alternative was to
leave the API alone and display Laravel's English message, the same debt ADR-23 accepted for form
validation errors. The developer chose to change the API instead: the list should show usage and the
delete button should be disabled for a category in use.

**Decision:** `CategoryController@index` returns a fourth field, `tools_count`, via
`Category::select(['id', 'name', 'slug'])->withCount('tools')->orderBy('slug')->get()`. The explicit
`select()` comes **before** `withCount()` on purpose: `withCount` sets the query's select list, after
which the column array passed to `get()` is silently ignored and the response widens to every column,
timestamps included. The ordering is not stylistic — reversing the two calls drops the subquery
entirely and `tools_count` disappears from the response.

**Consequences:**
- The response shape of an existing endpoint changed, so both consumers change with it: the frontend
  `Category` type and the Settings screen (ADR-31). The tools filter and the tool form also read this
  endpoint and simply receive one extra field they ignore.
- **`ReferenceDataTest`'s exact-key assertion caught the widening immediately** — it went red the
  moment `tools_count` appeared, which is precisely why ADR-18 asserted the exact key set rather than
  a subset. It was updated deliberately, not loosened.
- A new test asserts a **used category reports 2 and an empty one reports 0** — two counts that differ
  from each other and from the number of categories, so neither a hardcoded zero nor a miscounted
  join passes.
- Both changes were proven to go red on purpose: removing `withCount` fails both category tests, and
  **swapping `select()` and `withCount()` fails them too** — confirming the ordering comment describes
  real behaviour rather than folklore.
- Verified against the live API: the five categories report 3/2/4/3/6, matching the per-category tool
  counts snapshotted in ADR-26 and ADR-27, and summing to exactly the 18 `category_tool` rows.
- Full suite: **60 tests / 153 assertions** green. Pint clean on both touched files.
- The 422 from `destroy` is unchanged and still English. It is now a fallback rather than the primary
  path: the UI should prevent the attempt, and the 422 remains the guarantee for anyone calling the
  API directly or racing a concurrent edit.

**Alternatives considered:**
- Leaving the API unchanged and rendering Laravel's English 422 — rejected by the developer; it tells
  the admin only after the failed attempt and cannot be localised.
- Returning the count as a separate field inside the 422 body instead — rejected: it still only
  answers after the click, and it invents a non-standard error shape for one case.
- A dedicated admin endpoint carrying usage data — rejected as premature for one extra integer;
  the existing consumers are unharmed by a field they ignore.
- `withCount` without the explicit `select()` — rejected: it returns whole models and breaks the
  enumerated-columns contract from ADR-18.

---

## ADR-31: The category management screen — inline form, a slug that cannot be sent, and usage-aware deletion

**Status:** Accepted

**Context:** ADR-28 built the category write endpoints and ADR-30 added `tools_count`; this phase is
the screen. ADR-18 first named "Settings → Manage categories" as a deferred idea, and ADR-19 drew the
line that still holds: categories become editable through the UI, roles never do, because roles carry
the `level` hierarchy authorization depends on. Two shapes had to be chosen. ADR-23 put the tool form
on its own page because it has ten inputs, a 5000-character description and three multi-selects; a
category has three names and a slug, so that reasoning does not carry over and the developer chose an
inline form. The second was raised by the developer directly: the slug is immutable after creation, and
a form that renders the field greyed out but still sends its value is a facade — the backend rejects it
with 422, but the UI should never offer it.

**Decision:** (1) One page, `/settings/categories`, guarded by `RequireRole roles={ADMIN_ROLES}`
(ADR-29), holding the list and a single form panel that is either "new" or "edit". (2) **The slug is
absent from the edit path at every level**, not merely disabled: `UpdateCategoryPayload` has no `slug`
field, so `updateCategory` cannot carry one; `CategoryForm` renders the slug as **text** in edit mode
rather than as a disabled input, so no value is collected; and the create branch is the only caller
that passes a slug at all. A disabled input would still hold a value and invite someone to wire it up.
(3) `toCategoryName` (`src/lib/category-name.ts`) builds the translation map and **omits** languages
left blank rather than sending `""`, because the backend rejects a present-but-empty translation
(ADR-28) and a missing translation is the state the frontend already handles by falling back to
Bulgarian (ADR-27). (4) The delete button is **disabled when `tools_count > 0`**, so the impossible
action is never offered; `window.confirm` guards the possible one, reusing the ADR-23 precedent rather
than introducing a dialog component. `deleteCategory` still handles 422, because the count can go stale
between load and click. (5) Counts use **ICU pluralisation**
(`{count, plural, one {…} other {…}}`), deliberately not repeating the `tools.totalCount` bug recorded
as known debt in ADR-19, where a plain `{count}` placeholder reads wrong for exactly one item.
(6) `CategoryForm` receives a `key` tied to the category being edited, because it reads its initial
values once in a `useState` initializer — the ADR-24 trap; without the remount, switching to another
category would keep the previous one's values. (7) `settings.categories` and `settings.users` changed
from strings to objects in both dictionaries, and `/settings` now links to the categories screen.

**Consequences:**
- **The slug rule was verified at the wire, not by eye.** The edit request body was read from the
  network panel: `{"name":{"bg":"Преименувана тестова","en":"Test category"}}` — no `slug` key, and no
  empty `fr`. Reading the rendered form would only have shown that the field looks read-only.
- Verified end to end in a real browser on both locales: the list shows all five categories with counts
  3/2/3/4/6 and **every delete button disabled** because every category is in use; creating with a
  Cyrillic slug returns a field-level error under the slug plus a translated summary; fixing it creates
  the category, which appears as "Не се използва" with delete **enabled** — the discriminating
  difference; renaming re-sorts the row into its correct alphabetical position; cancelling the confirm
  dialog issues **no** DELETE request at all; accepting it removes the row.
- The **singular** plural branch was proven by constructing the case the seed data cannot show: a
  throwaway category with exactly one tool rendered "Използва се от 1 инструмент" and "Used by 1 tool",
  then both were deleted. Without it, every visible count was ≥ 2 and the `one` branch would have been
  shipped unexecuted.
- The English locale sorts by the English names (Code, Data, Image, Productivity, Writing) — a
  different order from Bulgarian, which is the check that per-locale sorting is real (ADR-27).
- Dev data was restored: the same five categories, the same slugs, the same counts, summing to the same
  18 `category_tool` rows.
- Console clean apart from the deliberate 422 logged by the browser during the invalid-slug test — a
  4xx while testing validation is success, not failure (pitfalls #8).
- `npx tsc --noEmit` and `npm run lint` clean. Dictionaries in sync at 119 keys each.
- **`categories-admin.tsx` is 170 lines, over the 150 ceiling for components.** It was split once
  already — `CategoryRow` came out of it — and what remains is form-state orchestration plus the list,
  coupled through the handlers. Splitting further would prop-drill four callbacks to buy nothing, so it
  stays and is reported rather than hidden. Roughly 35 of those lines are comments recording the two
  traps above.
- The English validation messages from Laravel are still shown per field (the ADR-23 debt), now
  alongside a translated summary line.

**Alternatives considered:**
- Separate `/settings/categories/new` and `/[id]/edit` routes mirroring ADR-23/ADR-24 — rejected by the
  developer: the reason those exist is a ten-field form, and three names plus a slug do not need a
  screen of their own or two navigations per rename.
- A disabled slug input in edit mode — rejected by the developer as a facade; the value would still be
  collected and one careless change would start sending it.
- Sending `en: ""` and letting the backend reject it — rejected: the admin would get a validation error
  for leaving an optional field blank.
- Patching local state after save instead of reloading — rejected: `tools_count` is computed by the
  backend, so a local patch would show a stale count on the row just edited.
- Letting the delete button stay enabled and explaining the failure afterwards — rejected in ADR-30;
  that is the whole reason `tools_count` was added.

---

## ADR-32: Users are deactivated, not deleted — `is_active` on users, login blocked, no per-request check

**Status:** Accepted

**Context:** This is the first phase of user management (part 3), and the backend foundation for it.
Reconnaissance of the real code established the starting point rather than assuming it: `users` had
`id, name, email, email_verified_at, password, remember_token, timestamps` plus the `department_id`
added in ADR-20 (nullable, **never used** — every user row is null); there was **no `UserPolicy`, no
`UserController` and not a single `/api/users` route**; `GET /api/user` is a closure returning the
whole model; and `AuthController::login` checked only the password, with Sanctum tokens carrying no
post-issue validity check of any kind. The developer settled the product rules up front: a user is
never deleted, only deactivated; a deactivated user cannot log in, does not appear in pickers, and
remains the author of the tools they created. This phase implements only the flag and the login
block — the management endpoints (list/create/update/activate/deactivate) and their policy are the
next phase, deliberately kept out so this diff stays on one subject.

**Decision:** (1) A boolean `is_active`, **NOT NULL, default true**, added `after('password')`.
A boolean rather than a `deactivated_at` timestamp: it maps one-to-one onto what the UI shows and
what queries filter on, and the project has no audit trail anywhere else (only `created_by`), so a
timestamp would store a value nothing reads. This exceeds "adding a nullable column" and was
therefore approved by the developer, not decided here. (2) `is_active` is **deliberately outside
`#[Fillable]`**, exactly like `Tool::created_by` (`docs/pitfalls.md`): the flag will be changed only
by a dedicated authorized action, never by whatever a request body happens to carry. (3) The model
carries **both** a `'is_active' => 'boolean'` cast and a `protected $attributes = ['is_active' =>
true]` default — see the consequences; these are two different guarantees, not redundancy. (4) Login
is refused for a deactivated user with a `ValidationException` (422, `email` key), so the body shape
is identical to every other validation error and `api.ts` already renders it — no frontend change was
needed. The message is explicit ("This account has been deactivated…") rather than the generic
credentials error: this is an internal tool, and an employee who cannot log in needs to know to call
an administrator instead of assuming they mistyped. It is English, the ADR-23 debt, accepted.
(5) **The check runs AFTER the password check, and the order is part of the requirement**: verifying
the password first means an unauthenticated caller cannot learn from the response whether a given
email exists and is deactivated. (6) **No middleware and no per-request check was added.** An
existing token stays valid; the guarantee that a deactivated user holds no token comes instead from
the deactivation *action* deleting their tokens, which lands in the next phase. This is a guarantee
by construction — login is the only way a token is issued, so the only path into "inactive with a
live token" is editing the database by hand.

**Consequences:**
- Six new Pest tests in `tests/Feature/UserAccountStatusTest.php`; full suite **66 tests / 167
  assertions** green (60 pre-existing + 6). Pint clean on all five touched files.
- **The planned test "a deactivated user with a valid token is rejected" was dropped, not quietly
  weakened.** Under decision (6) it does not describe what the code does; it belongs to the next
  phase as "deactivating deletes the tokens, and the old token then answers 401". Writing it here
  would have asserted a guarantee the system does not make.
- **Every guarantee was proven to go red on purpose**, one mutation at a time: removing the login
  check failed only the deactivated-login test; moving the check above the password check failed only
  the state-leak test; dropping the model default failed only the in-memory assertion; dropping the
  cast failed both boolean assertions; dropping the column default made the raw insert error with
  MySQL 1364. Each mutation broke its own test.
- **A column default is a DATABASE default, and Eloquent never reads it back after an insert.** The
  test written to assert "a new user is active by default" went red on the *model*, not the database:
  the stored row said 1 while the in-memory instance said `null`. That null is falsy, so
  `! $user->is_active` would read a brand-new user as deactivated, and the create endpoint of the
  next phase would have returned `"is_active": null` against a row that says true. Fixed at the cause
  with `protected $attributes`, not by refreshing the model in the test. Recorded in
  `docs/pitfalls.md`.
- The two defaults are asserted **separately**, because they are separate guarantees: the model
  default via `User::factory()->create()`, and the column default via a raw `DB::table()->insert()`
  that bypasses the model entirely. Either assertion alone would pass with the other mechanism
  missing.
- Verified against the live API with curl before this was written: an active login returns 200; the
  same credentials after flipping the flag in the dev database return 422 with the deactivation
  message; a **wrong** password on the same deactivated account returns the *credentials* message, so
  the state does not leak; and a token minted before deactivation **still answers 200** — decision (6)
  demonstrated rather than assumed. The dev database was restored and snapshotted: the same three
  users, all `is_active = 1`.
- `#[Fillable]` is unchanged, so nothing in the existing write paths can set the flag. The next phase
  must set it by direct property assignment, not through mass assignment.
- The column widens `GET /api/user` and the `user` object in the login response, both of which return
  the whole model. This was approved as an additive change (ADR-31-era rule: an existing response
  shape is an ask-first decision); no consumer breaks, because the frontend ignores unknown fields.

**Carried forward to the next phase (the project keeps no separate backlog file):**
1. **`UserSeeder` still uses `updateOrCreate` keyed on email.** Once administrators can rename users
   and change their roles through Settings, the next `db:seed` will overwrite an admin's edit and
   reset the password to `password` — precisely the problem ADR-28 (9) solved for categories by
   switching to `firstOrCreate`. Not changed here, because nothing is editable yet; it must be
   decided in the phase that makes users editable.
2. **No `pm` user is seeded.** The three seeded users are owner, manager and employee, so the
   pm-specific rules agreed for the next phase (a pm may not touch an owner at all) cannot be
   exercised in the browser against dev data. Whether to seed a pm user, and a deactivated user to
   make the list screen's marked state visible, is seed-data product input for that phase.
3. `department_id` remains unused on every user. The agreed plan assigns it in the user form; if that
   phase grows too large, the developer named it as the first thing to drop.

**Alternatives considered:**
- A `deactivated_at` nullable timestamp — rejected per (1); it is the cheapest schema change under
  CLAUDE.md's rules and records *when*, but nothing in this project reads an audit timestamp.
- A middleware (or a Sanctum token-validity callback) rejecting every request from an inactive user —
  rejected: it introduces a second authorization mechanism for an outcome that deleting the tokens
  already achieves, and the project has no middleware aliases at all (`bootstrap/app.php` has an empty
  `withMiddleware()`).
- The generic "credentials are incorrect" message for a deactivated account — rejected by the
  developer; account enumeration is not a meaningful threat behind a corporate login, and the silence
  costs a real employee a support call.
- Setting `is_active` in `UserFactory::definition()` instead of on the model — rejected: it would make
  every test pass while production code kept the null, which is the opposite of what a test is for.

---

## ADR-33: User management endpoints — one rule per endpoint, a pm that cannot touch an owner, and deactivation that revokes tokens

**Status:** Accepted

**Context:** ADR-32 added the `is_active` flag and blocked a deactivated login. This phase is the
management surface on top of it: list, create, edit, change roles, reset password, activate,
deactivate. It is the largest authorization surface in the project so far, and the developer settled
every rule before any code was written — owner and pm manage users; a pm may not act on an owner at
all; only an owner grants or revokes the `owner` role; nobody changes their own roles or deactivates
themselves; anybody who may manage users may reset their OWN password, because `/profile` does not
exist and there is no self-service reset, so an admin without that path is permanently locked out on
a forgotten password; users are never deleted.

One question could not be answered from the settled rules and was put back to the developer: if name,
email, department and roles travel in a single `PUT`, then "an admin may edit their own name but not
their own roles" has to be enforced by the *frontend omitting a field*, which is the "chain with a
missing middle" failure recorded as process gotcha #1 — `tsc` cannot see it and nothing goes red when
it is forgotten. The developer chose to split the endpoints instead.

**Decision:** (1) **Seven routes, one concern each**, so every rule is a separate policy ability with
its own test rather than a branch inside a shared handler: `GET /api/users`, `POST /api/users`,
`PUT /api/users/{user}` (name, email, department only), `PUT /api/users/{user}/roles`,
`PUT /api/users/{user}/password`, `POST /api/users/{user}/activate`,
`POST /api/users/{user}/deactivate`. **There is no DELETE route and its absence is commented in
`routes/api.php`**, so the omission reads as a decision rather than an oversight.
(2) `UserPolicy` composes each ability from three predicates: `isAdmin` (explicit `hasRole('owner')
|| hasRole('pm')`, never a level threshold — ADR-12's reasoning), `mayActOn` (**a pm may not act on an
owner at all** — one rule instead of three exceptions, chosen by the developer over partial
permissions), and a self-exclusion on `updateRoles` and `deactivate` only. `updatePassword`
deliberately has no self-exclusion.
(3) **Authorization lives in the Form Requests' `authorize()`**, the ADR-28 placement, so a manager
gets 403 whatever they send instead of a 422 that hands the validation rules to someone who may not
write at all. `activate`/`deactivate` carry no payload and therefore have no Form Request; they
authorize in the controller body, the same split `CategoryController::destroy` already uses.
(4) The "only an owner touches the owner role" rule depends on the *payload*, so it lives in
`authorize()` too, reading the submitted ids defensively (`Arr::wrap` + int cast) because the payload
is unvalidated at that point. It is written **symmetrically** — the requested set must not change
whether the target holds the owner role — even though the revoke direction is today also blocked
upstream by `mayActOn`, so the guarantee survives if that upstream rule is ever relaxed.
(5) On `PUT /api/users/{user}`, `role_ids` and `password` are **`prohibited`**, mirroring the
immutable category slug (ADR-28): they have their own endpoints with their own rules, and a silently
ignored field is how a caller comes to believe it changed something it did not.
(6) **`deactivate` deletes the target's Sanctum tokens.** This is the entire mechanism by which a
deactivated user loses access, since ADR-32 deliberately declined a per-request middleware.
(7) A private `userPayload()` in the controller defines the exact wire shape — `id, name, email,
is_active, department_id, roles[{id, name, display_name, level}]` — rather than returning the model,
so timestamps, the password hash and the belongsToMany `pivot` object never enter the contract. The
list is a **plain array, deliberately not paginated** (ADR-18's reasoning: tens of employees, not
thousands) and **includes deactivated users**, because reactivating one has to be possible somewhere.
(8) `UserSeeder` switches from `updateOrCreate` to **`firstOrCreate`**, with roles attached only when
the row was just created — the ADR-28 (9) decision, now due because users become editable in this
phase. Two users were added: a pm (`maria@pm.local`) and a deactivated employee
(`georgi@inactive.local`), so the pm/owner rules and the deactivated row have something to be
verified against in the browser rather than only in Pest.

**Consequences:**
- Two new test files, **58 new test cases**; full suite **124 tests / 330 assertions** green
  (66 pre-existing + 58). There is a 403 test for every one of the seven operations, and each asserts
  that **nothing was written**, not merely the status code.
- **Ten mutations, each red on its own test and nothing else:** removing the token deletion; `sync` →
  `syncWithoutDetaching`; dropping the self-exclusion from `deactivate`; dropping it from
  `updateRoles`; making `mayActOn` always true; removing the owner-role check from create; removing it
  from the roles endpoint; dropping the two `prohibited` rules; lowering the password minimum to 7;
  and **moving authorization out of the Form Request into the controller body** — which reds exactly
  the "403, not 422" test, re-confirming ADR-28's placement is load-bearing rather than stylistic.
- Making `mayActOn` always true did **not** red "a pm cannot change an owner's roles": decision (4)'s
  symmetric check blocks it independently. The defence in depth is real, and it is visible because the
  mutation was run.
- **The token test initially failed and the code was innocent.** Laravel's test client keeps the
  guard's resolved user between requests inside one test, so the second call answered as the owner
  from the first call instead of resolving the revoked Bearer token. curl against the live API proved
  the real behaviour (200 → deactivate → 401 with the same token); the test was fixed with
  `$this->app['auth']->forgetGuards()`, not weakened. Recorded in `docs/pitfalls.md`.
- **`department_id` is outside `#[Fillable]`**, like `is_active`, so mass assignment silently drops
  it. The controller sets it by direct property assignment in both `store` and `update`. This was
  caught by the implementing agent, not by the specification. Recorded in `docs/pitfalls.md`.
- **`PUT /api/users/{user}` CLEARS the department when `department_id` is omitted**, because the rule
  is `nullable` and `validated()` returns null for an absent key. This is the ADR-23 contract on
  purpose — `ToolPayload` has no optional fields for exactly this reason — and it means the frontend
  form must always send an explicit value. Flagged here for the UI phase.
- Verified against the live API with curl: employee 403 on listing and 403 (not 422) on a junk create
  payload; pm 403 on updating, deactivating and role-granting against the owner, 403 on deactivating
  herself and on changing her own roles, but 200 on editing her own name; `role_ids` on the update
  endpoint answers 422 naming `role_ids`; the list returns a plain array of five users with exactly
  the documented keys, ordered by name in Bulgarian collation, with the deactivated user reporting
  `is_active: false`.
- **`firstOrCreate` was proven, not assumed**, the ADR-28 way: a user was renamed through the API,
  `db:seed --class=UserSeeder` was re-run, and the admin's name survived while the two new users were
  created (3 → 5 users, 3 → 5 `role_user` rows). The dev database was then restored to the seeded
  names.
- Pint clean on all nine new or edited files. `UserSeeder` still reports `no_unused_imports`, which was
  confirmed pre-existing by running Pint against the HEAD version of the same file and against the two
  untouched sibling seeders — the ADR-26/27/28 finding, re-confirmed and again left alone (pitfalls
  #11). Pint's automatic fix was applied and then **reverted**, because the only thing it changed was
  that unrelated scaffold import.
- The frontend phase can now be built against a complete backend: `getUsers`, `createUser`,
  `updateUser`, `updateUserRoles`, `updateUserPassword`, `activateUser`, `deactivateUser`.

**Alternatives considered:**
- One `PUT /api/users/{user}` carrying roles as well, rejecting the request when a user sends roles
  for their own account — rejected by the developer: it distributes one rule across both ends of the
  wire, and nothing goes red if the frontend forgets to omit the field.
- Partial permissions for a pm over an owner (may edit the name, may not change roles) — rejected by
  the developer in favour of one blanket rule; exceptions are what make an authorization matrix
  unreviewable.
- Reviving `hasAtLeastLevel(60)` for "owner or pm" — rejected again, for ADR-28's reason: it silently
  grants the right to any future role seeded at level 60 or above.
- A `role:owner,pm` middleware instead of a policy — rejected: the project has no middleware aliases
  at all, and this would be a second authorization mechanism beside policies.
- Paginating the user list — rejected per ADR-18: pagination serves growing datasets, not a company
  roster of tens.
- API Resource classes for the wire shape — rejected: the project has no Resource layer, and adding
  one is an ask-first "new kind of thing". A private method on the controller does the same job for
  one resource.

---

## ADR-34: The user management screen — three save blocks in one panel, a panel that holds an ID, and an owner option that is absent rather than disabled

**Status:** Accepted

**Context:** ADR-33 built seven endpoints; this phase is the screen, and the last part of the user
management feature. ADR-31 set the model for a Settings screen (one page, an inline form panel, a
list of rows) and the developer asked for the same model here. Two shapes had to be chosen that the
categories screen never faced. First, a user has **three** independent saves — fields, roles,
password — because ADR-33 split them into three endpoints with three different authorization rules;
the categories screen has one form and one endpoint. Second, several controls must be visible but
unusable depending on who is looking: a pm may not touch an owner at all, nobody may deactivate
themselves or change their own roles, and only an owner may grant the owner role.

**Decision:** (1) **The dictionary went in first, as its own step** — 35 keys under `settings.users.*`
in both locales before a single component referenced them, because `messages/bg/common.json` is the
type source for `t()` and the reverse order does not compile. Both files verified in sync at 154 keys.
(2) One page `/settings/users` guarded by `RequireRole roles={ADMIN_ROLES}`, `max-w-4xl` (wider than
categories — the rows carry more), with `/settings` finally linking to it.
(3) **The edit panel is one card with three visually separated blocks, each with its own submit
button — one block per endpoint.** The developer chose this over four buttons on every row, which at
five users would put twenty buttons on one screen. Each block owns its own submitting/error/field-error
state so one block's failure never renders under another.
(4) **The panel holds a user ID, not a user object.** Storing the object froze a copy taken when the
panel opened, so after saving a rename the heading kept announcing the old name while the row beneath
already showed the new one. Deriving the user from the reloaded list by id removes the entire class of
staleness rather than resyncing it after the fact. This was found in review, not in the browser.
(5) **For a non-owner actor the `owner` checkbox is OMITTED from the role options, not disabled**, with
a hint saying why — the same reasoning ADR-31 applied to the immutable category slug: a disabled input
still holds a value and invites someone to wire it up. A pm can never legitimately grant the role, and
can never open the panel of a user who already holds it, so the option has no honest use.
(6) Four UX-only predicates in `src/lib/roles.ts` — `isOwnerUser`, `canManageUser`, `canEditUserRoles`,
`canDeactivateUser` — mirroring `UserPolicy`, so the row and the panel cannot disagree about who may do
what. Every one is commented as UX only; the guard is the backend, proven by ADR-33's tests.
(7) `window.confirm` on deactivate only. Deactivation revokes the target's tokens and ends their
sessions immediately, which deserves a question; activation is harmless and reversible, and asking
about a harmless action teaches people to click through without reading.
(8) `UpdateUserPayload.department_id` is `number | null`, never optional — omitting the key CLEARS the
department on the backend (ADR-33), so the type forces the form to always send an explicit value. The
same contract as `ToolPayload` (ADR-23), for the same reason.

**Consequences:**
- Nine new components plus additions to `api.ts`, `types.ts` and `roles.ts`. `AdminUser` is
  deliberately not called `User`: that name is already the signed-in user in `api.ts`, whose `roles`
  are plain strings rather than objects — the same-word-two-shapes trap that produced `RoleOption`
  in ADR-19.
- `npx tsc --noEmit` and `npm run lint` clean. Dictionaries in sync at 154 keys each.
- **Verified in a real browser across three roles and both locales**, driving the app with a
  curl-minted token in `localStorage` rather than the login form (pitfalls #3a). As **owner**: five
  users ordered by name in Bulgarian collation, the deactivated seed user badged, and the owner's own
  row with Deactivate disabled; opening their own panel shows details and password but **no roles
  block**, replaced by the sentence explaining why. As **pm**: the owner's row has both buttons
  disabled carrying the owner-protection hint while the pm's own row carries the *self* hint — two
  different hints on two different rows, which is the check that discriminates; and the roles form
  offers three options with the owner hint where the owner sees four options and no hint. As
  **employee**: `/bg/settings/users` lands on the dashboard and the Settings link is absent from the
  navbar.
- **The full lifecycle was driven end to end through the UI**: creating with a 5-character password
  returned a field-level error plus the translated summary and wrote nothing; fixing it created the
  user with the IT department and the Employee role; that user then logged in through the API with the
  password the admin chose; cancelling the deactivate confirm left the row untouched; accepting it
  flipped the badge to Deactivated and the button to Activate; and the user could then no longer log
  in. The test user was removed directly from the database afterwards — deliberately not through the
  UI, because there is no delete and there should not be one.
- Badge colours were checked as **computed styles**, not by eye (the ADR-14 method): active is
  `rgb(52, 189, 149)` = the mint accent `#34BD95`, deactivated is `rgb(240, 153, 123)` = the peach
  secondary `#F0997B`, both from the semantic tokens with no hardcoded hex.
- The English locale renders roles, departments, badges and hints translated, while user *names* stay
  as stored — they are data, not interface.
- Console clean across the whole run apart from the single deliberate 422 from the short-password test
  (pitfalls #8). No hydration warnings.
- **Known limitation, accepted rather than hidden: the navbar keeps showing the old name after an
  admin renames themselves**, until a reload. `auth-context` exposes no refresh function, and adding
  one is a change to shared authentication code that does not belong in the middle of this phase. The
  row and the panel both update immediately; only the navbar copy is stale.
- The password form clears itself after a successful save via a version counter used as its `key` —
  the ADR-24 remount mechanism, used here to force a reset rather than to prevent one.
- **Two files exceed the size guideline and are reported rather than hidden:** `users-admin.tsx` at
  264 lines (ceiling 150) and `user-create-form.tsx` at 190. The orchestrator holds four independent
  block states plus the list lifecycle, coupled through handlers; the list was already split out into
  `user-list.tsx`. Extracting the handlers into a custom hook would fit the ceiling, but this project
  has no custom hooks anywhere, so introducing the first one is an ask-first "new kind of thing" and
  was not done unilaterally. `tool-form.tsx` (289 lines) is the existing precedent for a field-heavy
  component kept whole.
- Dev data was restored and snapshotted: the same five users, the same names, Георги still deactivated,
  five `role_user` rows, no orphans.

**Alternatives considered:**
- Four buttons per row (details, roles, password, deactivate), each opening its own small form —
  rejected by the developer: fewer pixels per panel, but twenty buttons on a five-row screen.
- Keeping the user object in panel state and resyncing it from the list after each save — rejected in
  favour of deriving it, which cannot go stale in the first place.
- Disabling the owner checkbox for a pm instead of omitting it — rejected per (5), the ADR-31 precedent.
- Confirming activation as well as deactivation — rejected: a confirmation on a harmless action trains
  people to dismiss confirmations.
- Sorting the department dropdown by the translated label — rejected for consistency: every other
  department control in this app (the tools filter, the tool form) renders them in backend order, and a
  third convention costs more than the ordering gains.
- Adding a `refresh()` to `auth-context` so the navbar tracks a self-rename — deferred, see the known
  limitation above.

---

## ADR-35: Draft visibility and publishing rights — manager reads drafts, only owner and pm publish (partially supersedes ADR-11 and ADR-12)

**Status:** Accepted

**Note on order:** this ADR is written BEFORE the code, unlike every ADR above it. The developer
asked for the decision to be recorded and reviewed first, because it revises two accepted ADRs and
touches authorization. Implementation follows as two phases (backend, then frontend), each with its
own commit; the verification notes this file usually carries will be appended to those phases'
ADRs, not retrofitted here.

**Context:** ADR-11 gave `tools` a `status` column (`draft`/`published`, default `published`) and
called it "a cheap hook for later moderation" to be activated on Day 9. Day 9 never came, and
reconnaissance established that the hook was never wired to anything: `Tool::scopeFilter`
(`app/Models/Tool.php:35`) has clauses for `search`, `category`, `role` and `department` and none for
`status` or `created_by` — it does not even receive the user; `ToolController@index` paginates
whatever the scope returns; `ToolController@show` calls **no `authorize()` at all**, so any
authenticated user can fetch any draft by id; `ToolPolicy::viewAny`/`view` are bare `return true`;
`status` is in the model's `#[Fillable]` and both Form Requests validate it as `in:draft,published`
with no role condition; `ToolPolicy::update` is the only gate on writing, and a policy method
receives no payload, so it cannot distinguish "rename this tool" from "publish this tool". Verified
against the live API: signed in as `petar@employee.local` (employee), `GET /api/tools` returned all
10 tools including two drafts authored by the owner, and `GET /api/tools/1` on a foreign draft
returned 200. There are zero tests covering status behaviour or draft visibility.

The developer settled the product rules. Drafts are unfinished work that needs review before the
company sees it, and publishing is an editorial act over the shared catalog — the two are different
acts and get different rights.

**Decision:**

(1) **Visibility, by role.** `owner`, `pm` and `manager` see every tool, including every draft.
`employee` sees all `published` tools plus their own drafts (`created_by == user->id`) and no other
drafts. Tools with a null `created_by` are drafts nobody authored and are therefore visible only to
the first three roles — the same treatment ADR-12 gave them for writes.

(2) **The rule applies to `index` AND `show`.** `show`'s missing authorization is a hole, not a
shortcut: closing it in `index` alone would leave `/api/tools/1` as an unguarded read of every
draft in the system. `ToolPolicy::view` gets the real condition and `ToolController@show` gets the
`$this->authorize('view', $tool)` call it never had.

(3) **`index` narrows in the QUERY, not after fetching.** A new `Tool::scopeVisibleTo(Builder,
User)` is applied before `scopeFilter`. Filtering a fetched page in PHP would make `total`,
`last_page` and the page size lie — a 15-row page rendering 12 rows silently tells the employee that
three tools exist which they may not see, and the pagination metadata that ADR-19 deliberately
carried from day one would become wrong. `scopeFilter` is left untouched: it expresses the user's
search, this expresses their identity, and merging the two would let a request parameter influence
what the identity rule returns.

(4) **The narrowing is derived from the token, never from a request parameter.** No `?status=` is
added to `GET /api/tools` for this purpose and `getTools` in `src/lib/api.ts` gains no `status`
argument. A client-supplied filter is one the client can omit.

(5) **Publishing: only `owner` and `pm` may set `status` to `published`, ever.** `employee` and
`manager` create drafts only and can never move a tool to `published` — **including a tool they
authored themselves.** Authorship grants editing (ADR-12, unchanged); it does not grant publication.

(6) **The status rule lives in the Form Requests' `authorize()`, not in `ToolPolicy::update`.** A
policy ability receives the model and the user but not the payload, so it structurally cannot see
that `status` is the field being changed. `StoreToolRequest::authorize()` and
`UpdateToolRequest::authorize()` — both currently bare `return true` — take the check, which is the
ADR-28 precedent and buys the same thing it bought there: `authorize()` runs BEFORE validation, so
the answer cannot depend on what else is in the request. A named `ToolPolicy::publish(User)` ability
holds the actual role test so the rule has one home and the frontend has one thing to mirror.

(7) **Absent status is forced to `draft` for `employee` and `manager`; an explicit
`status: published` from them is 403.** The form will not offer them the field (point 8), so the
normal path sends nothing and gets a draft. A request that explicitly asks to publish is refused
rather than silently downgraded: coercing it would return `201` to a caller who asked for publication
and believes they got it. For `owner` and `pm` an absent status keeps falling through to the column
default `published` from ADR-11, which is exactly the behaviour they should have.

(8) **No migration.** The database default stays `published`. Because non-admins never reach the
default (point 7 forces their value in the application layer) and admins should get it, the column
is already correct. ADR-11's promise that moderation could be activated "without a migration" holds
literally.

(9) **The status select is OMITTED from the form for `employee` and `manager`, not disabled** — the
ADR-31 and ADR-34(5) precedent: a disabled input still holds a value and invites someone to wire it
up, and there is no honest use for this one. `owner` and `pm` keep the two-option select.

(10) **Explicit role names, not `level`.** The see-everything set is
`hasRole('owner') || hasRole('pm') || hasRole('manager')` and the publish set is
`hasRole('owner') || hasRole('pm')`, mirroring ToolPolicy (ADR-12), CategoryPolicy (ADR-28) and
UserPolicy (ADR-33). `User::hasAtLeastLevel()` would express "level >= 40" more briefly and stays
unused on purpose: every authorization rule in this project reads as a list of role names, and one
rule written the other way is worse than the repetition.

(11) **Refusing a hidden draft returns 403**, the Laravel default from `authorize()`, not a 404
disguise. This is an internal catalog behind a login where the existence of an unfinished entry is
not sensitive, and a 404 that means 403 misleads the next person to debug it.

**What this changes and what it leaves standing:**

- **ADR-11 is partially superseded.** Superseded: "Read access to the catalog is universal" and
  "role-based restrictions, if any, will apply only to write/moderate actions" — reads are now scoped
  by identity for `employee`. Still standing: the whole table shape, the `draft`/`published` enum,
  the `published` column default, `created_by` as the audit trail, both pivots, and roles-on-a-tool
  as descriptive tags rather than an access filter. The `role_tool` pivot still has nothing to do
  with who may read a tool; this ADR uses the user's OWN roles, which is a different relation.
- **ADR-12 is partially superseded.** Superseded: point (1), "Read (index, show): any authenticated
  user, all tools." Also narrowed in substance though not in text: "Create: any authenticated user"
  remains true — anyone may still create a tool — but a non-admin's tool now necessarily starts as a
  draft. Still standing, unchanged: edit/delete belongs to the author or to `owner`/`pm`; tools with
  a null `created_by` are writable only by `owner`/`pm`; checks use role names rather than levels;
  and the principle that the frontend's role checks are UX only.
- **ADR-20's parenthesis is now answered.** It recorded that narrowing the list to "I only see mine"
  "would be a ToolPolicy change, a separate decision, not requested." This is that decision, and it
  is deliberately NOT "only mine": published tools stay universally readable, which is the catalog's
  entire purpose.

**Why manager sees drafts but cannot publish.** These are two different acts. Reading a draft is
taking part in preparing it — a manager is who an employee asks to look at an entry before it goes
out, and a reviewer who cannot open the thing under review is not a reviewer. Publishing decides
what the whole company sees in a shared catalog, which ADR-12 already assigned to `owner` and `pm`
as its editors. Granting the manager sight without granting them the button is the point, not an
oversight: it keeps one editorial voice over the catalog while letting review happen close to the
work. It also keeps the manager's WRITE rights exactly equal to an employee's — the only difference
between the two roles in this feature is what they can see — so nothing here turns `manager` into a
half-administrator, and `ADMIN_ROLES` keeps meaning the pair it has always meant.

**Why an employee sees their own drafts but not other people's.** An employee must be able to find
and finish their own unfinished entry; hiding it would make `draft` unusable for the only people who
routinely produce one, and their work would vanish from the list the moment they saved it. Other
people's drafts are a different thing entirely — unreviewed, possibly wrong information presented in
a catalog whose value is that what it contains has been vetted.

**Consequences:**

- Two policy abilities gain real bodies (`ToolPolicy::view`, plus a new `publish`), `viewAny` stays
  `true` because the scope, not the ability, does the narrowing for a list.
- The frontend needs a second role grouping beside `ADMIN_ROLES` in `src/lib/roles.ts` — the
  see-everything trio — plus a UX-only `canPublish` predicate. Both are UX only, as everything in
  that file is; the guarantee is the backend.
- `tool-card.tsx`'s draft badge (ADR-19 point 11) becomes meaningful rather than merely honest: it
  now marks something not everyone can see.
- The tests must discriminate, per the lesson recorded repeatedly in this file: an employee seeing
  9 of 10 tools where a manager sees 10 (counts that differ), an employee's own draft present while
  a foreign draft is absent from the same response, `GET /tools/{foreign draft}` as 403 where the
  same id is 200 for a manager, an employee's `PUT {status: published}` on their OWN tool as 403 —
  the case that would pass under a naive "authors may edit their tools" reading — and a non-admin
  `POST` without a status landing as `draft` while an `owner`'s lands as `published`.
- Pagination totals stay truthful for every role, which is the whole reason for point (3).
- Seed data already exercises this: three drafts across two authors (owner's ChatGPT and Cursor,
  an employee's own), so the difference between roles is visible in the dev database without
  arranging anything.

**Alternatives considered:**

- **Employee sees only published tools, including not their own drafts** — rejected: their own
  saved work would disappear from the list, making the draft state unusable for exactly the people
  who produce drafts.
- **Employee sees only tools they created** — rejected, and it was never the proposal: a catalog
  whose readers each see a private slice is not a shared catalog. ADR-20 flagged this shape as a
  possible future direction; it is explicitly not the direction taken.
- **Manager may publish** — rejected: it would put three roles in editorial control of what the
  company sees and would collapse the distinction between reviewing work and deciding the catalog.
- **Status open at creation, frozen at update** — the variant where an `employee` (and a `manager`)
  chooses the status when creating a tool but may never move an existing one from `draft` to
  `published`: access to the field at creation, only the transition forbidden. Rejected in favour of
  the variant adopted in points (5) and (7), because the adopted one has no middle cases: one rule,
  "employee/manager are always draft", instead of "allowed at creation, forbidden on change".
- **`level >= 40` instead of naming the three roles** — rejected per point (10); consistency with
  every other policy in the project beats brevity in one of them.
- **Putting the status rule in `ToolPolicy::update`** — rejected because it cannot work: the method
  never sees the payload. A policy that silently cannot enforce the rule it appears to own is worse
  than no policy.
- **Silently coercing a non-admin's `status: published` to `draft`** — rejected per point (7): the
  API would answer `201` to a request it did not honour.
- **Filtering the fetched page in PHP instead of in the query** — rejected per point (3); it breaks
  the pagination contract.
- **A `?status=` parameter so the client asks for what it should see** — rejected per point (4);
  visibility derived from a parameter is visibility a caller can decline.
- **404 instead of 403 for a hidden draft** — rejected per point (11) for an internal, behind-login
  catalog.
- **Changing the column default to `draft`** — rejected as unnecessary per point (8), and it would
  have been an ask-first schema change for no gain: `owner` and `pm` want the current default.
