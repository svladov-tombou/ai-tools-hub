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
