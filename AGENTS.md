<laravel-boost-guidelines>
=== .ai/project rules ===

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
- All shadcn components import Radix from the unified `radix-ui` package, as the new-york style does since February 2026 (migrated with `shadcn migrate radix`). Do not add `@radix-ui/react-*` packages; if a component ever arrives with one, run `pnpm dlx shadcn@latest migrate radix` and remove the package.
- Achromatic system ("The Club Ledger" in `DESIGN.md`): no blue, red only for destructive actions and errors.
- Every menu item of every role exists; items not built yet are dimmed with «Pronto».
- The Impeccable skill (`/impeccable shape`, `harden`, `audit`) is used for UX, not for restyling. It is not versioned: install it per machine with `npx impeccable install --providers=claude --scope=project`. Its skill folder, agents and hooks stay out of git.

## Working rules

- Never read or edit `.env*` files. If a variable must change, tell the user the name and value to set.
- Commits: Conventional Commits in Spanish, imperative, lowercase, one logical change per commit.
- Never push, amend or use `--no-verify` unless the user asks. The repository stays private until phase 7.
- Demo data is fictional: no real brands, people or personal data in code, seeders or history.
- Ask before building anything not in the plan (pages, buttons, modes such as "view as"); do not assume.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
