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
