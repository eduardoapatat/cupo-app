# Cupo

A fictional app (portfolio project) for small group-class studios for adults (pilates, yoga, functional training, dance): owners define plans, schedule and rules; the front desk enrolls students, sells plans, takes payments and balances the cash register; instructors see their sessions and mark attendance; students book, join waitlists and pay. Laravel 13 + Inertia/React starter kit, PostgreSQL 18. One single authenticated app: no public site, no marketplace between studios and no platform super admin.

## Where things stand

- Progress lives in `docs/roadmap.md` (phases and checkpoints with checkboxes). Read it first to know the current phase; update it in the commit that closes a checkpoint.
- Work one checkpoint at a time and stop for the user's explicit "sigue" before starting the next one. Plan each new phase before coding it.

## Commands

- Services: `docker compose up -d pgsql mailpit` (PostgreSQL with `cupo_app` and `testing` databases, Mailpit UI at http://localhost:8025). Optional database UI: `docker compose up -d adminer` (http://localhost:8080, server `pgsql`). PichangApp uses the same ports, so run one project at a time.
- Dev server: `composer dev` (app at http://localhost:8000, queue, Vite and pail).
- Fresh checkout: `composer install`, `pnpm install`, `php artisan key:generate`, `pnpm run build`, `php artisan migrate`. Without the build, Inertia page tests fail with `ViteManifestNotFoundException`.
- Checks: `composer test` (Pint, PHPStan level 7, Pest) and `composer ci:check` (adds `vp check` and `tsc`; this is what CI runs). Fix JS/MD/YAML formatting with `pnpm run check:fix`.

## Language rules

- English: code, comments, docblocks, identifiers, table and column names, API fields, and these AI guideline files.
- Spanish: everything users see (UI text, product URLs, emails, validation messages), plus `README`, `docs/`, ADRs and commit messages.
- UI text is hardcoded in Spanish in the React components (no i18n library). Backend strings live in `lang/es/*.php` and `lang/es.json`. Use a neutral "tú" tone. See `docs/decisions/0001-interfaz-solo-en-espanol.md`.
- Fortify auth URLs stay in English (`/login`, `/settings/...`); product URLs are Spanish (`/inicio`, `/estudios/{slug}/...`).

## Invariants

- Times are stored in UTC (`timestamptz`) and interpreted in `config('studio.timezone')` (`America/Santiago`) using each date's own offset.
- Money is CLP as integers. Never floats.
- Business rule defaults (cancellation window, booking cutoff, waitlist cutoff, unlimited plan daily limit, expiry warning) come from `config/studio.php`. Once a studio exists, its own rules apply. Never hardcode those numbers.
- The role lives in the membership (user × studio), never on the user. Permissions are granted per action (`StudioPermission` enum, one Gate per permission, Policies per resource), never with `if role === ...`. The matrix in `docs/roles-y-permisos.md` is the source of truth for the authorization tests.
- A resource from another studio answers 404 (`Response::denyAsNotFound()`), not 403.
- Confirmed bookings never exceed a session's capacity, guaranteed by the database under real concurrency (phase 2).
- Data minimization: no national ID (RUT), birth date or health data, and no minors. See the "Datos personales" section of `docs/architecture.md`.
- Tests run against PostgreSQL (`testing` database), never SQLite, because the domain relies on PostgreSQL constraints.

## Before you change something, read

- Architecture, code map and cross-cutting concerns: `docs/architecture.md`.
- Roles, menus, arrival screens and the permission matrix: `docs/roles-y-permisos.md`.
- Why something unusual is the way it is: `docs/decisions/NNNN-*.md`. Add a new ADR (MADR 4, Spanish) when you make a deliberate trade-off.
- Product and visual system: `PRODUCT.md` and `DESIGN.md`.
- Keep `docs/` in sync in the same commit as the code it describes.

## UI rules

- Only the starter kit's shadcn/ui components, as they are. Add missing ones with the shadcn CLI, unmodified; if the CLI writes `import { cn } from "cn"`, change it to `@/lib/utils` and do not accept the `cn` dependency it adds.
- Achromatic system ("The Club Ledger" in `DESIGN.md`): no blue, red only for destructive actions and errors.
- Every menu item of every role exists; items not built yet are dimmed with «Pronto».

## Working rules

- Never read or edit `.env*` files. If a variable must change, tell the user the name and value to set.
- Commits: Conventional Commits in Spanish, imperative, lowercase, one logical change per commit.
- Never push, amend or use `--no-verify` unless the user asks. The repository stays private until phase 7.
- Demo data is fictional: no real brands, people or personal data in code, seeders or history.
- Ask before building anything not in the plan (pages, buttons, modes such as "view as"); do not assume.
