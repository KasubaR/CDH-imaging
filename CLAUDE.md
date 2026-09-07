# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

CDH is a Laravel 13 / PHP 8.5 app for transferring diagnostic images (X-rays, etc.) between hospital
departments. Departments request examinations for patients, upload images against those examinations, and
send/receive/forward transfers of those images to other departments. There is no public registration —
every account is provisioned by an admin, and sign-in is by `username` (not email).

## Commands

```sh
composer run dev      # serve + queue listener + Vite dev server together (php artisan dev)
npm run dev            # Vite dev server only
npm run build           # production frontend build

composer run test       # config:clear then php artisan test
php artisan test --filter=test_admin_can_log_in_and_is_redirected_to_admin_dashboard
php artisan test tests/Feature/AuthenticationTest.php
vendor/bin/phpunit --filter=testName

vendor/bin/pint --dirty --format agent   # required after touching any PHP file
```

`composer run setup` bootstraps a fresh clone (installs deps, copies `.env`, generates the key, migrates,
builds frontend assets).

Local dev DB is MySQL (see `.env`: `DB_CONNECTION=mysql`, database `cdh`). Tests always run against SQLite
in-memory regardless of `.env` (`phpunit.xml` hardcodes `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`), and
`Tests\TestCase` calls `withoutVite()` in `setUp()` so feature tests never need a built frontend.

## Authorization model

Two independent layers gate access — both keyed off `App\Enums\UserRole` and `App\Enums\Permission`:

1. **Route middleware** (aliased in [bootstrap/app.php](bootstrap/app.php)):
   - `admin` → [EnsureUserIsAdmin](app/Http/Middleware/EnsureUserIsAdmin.php) — requires `role === admin`.
   - `permission:<slug>` → [EnsureUserHasPermission](app/Http/Middleware/EnsureUserHasPermission.php) — requires
     the slug to be one of the `App\Enums\Permission` cases and to be present on the user.
2. **Gates** defined in [AppServiceProvider::boot()](app/Providers/AppServiceProvider.php) for finer-grained
   abilities used in controllers/views (`manage-departments`, `view-images`, `send-transfers`, etc.). Some
   gates layer a department flag on top of the permission check — e.g. `send-transfers` requires both the
   `send` permission *and* `department.can_send`; `receive-transfers` mirrors this with `can_receive`.

`User::hasPermission()` short-circuits to `true` for admins — admins implicitly hold every permission and
never need rows in the `permission_user` pivot. Staff permissions are explicit rows, assigned via
`$user->permissions()->sync(...)` (see [PermissionSeeder](database/seeders/PermissionSeeder.php) and
`DatabaseSeeder`).

Login additionally requires `is_active` on the user and (for staff) `canAccessDepartment()`, which checks the
assigned department itself is `is_active` — see [User.php](app/Models/User.php) and
[LoginRequest::authenticate()](app/Http/Requests/Auth/LoginRequest.php). Rate limiting is per
`username|ip` via `RateLimiter`.

There are **two separate login controllers/routes** sharing the same `LoginRequest`:
- `/` → [LoginController](app/Http/Controllers/Auth/LoginController.php) — any active user, redirects to
  `admin.dashboard` or `department.index` based on role.
- `/admin` → [AdminLoginController](app/Http/Controllers/Auth/AdminLoginController.php) — logs the attempt
  out again and rejects with a validation error if the authenticated user isn't an admin.

`bootstrap/app.php` sets `redirectGuestsTo('/login')` and a role-aware `redirectUsersTo()` closure, so
`Route::redirect('/login', '/')` and these two redirect rules are what keep guests/authed users on the right
landing page — check there before changing default-redirect behavior.

## Domain model

```
Department ─┬─< User >─< Permission (pivot: permission_user)
             ├─< Examination >─< Image
             │        │  (requesting_department_id, requested_by)
             │        └─< Transfer >─< TransferRecipient
             │               (from_department_id, sent_by)
             └─< TransferRecipient
Patient ────< Examination >─── ExaminationType
User ───────< AuditLog, Notification
```

- An `Examination` belongs to a `Patient` and an `ExaminationType`, and is requested by a `User` on behalf of
  a `requesting_department_id`.
- `Image` rows point at an `Examination` and store disk/path/mime/size — file storage is disk-backed, not
  inline. There's no `Services/` code yet (the directory is a placeholder); upload/storage logic doesn't
  exist yet.
- A `Transfer` moves an `Examination`'s images out of `from_department_id`; `TransferRecipient` rows are the
  fan-out to each destination department per transfer.
- `AuditLog` and `Notification` both belong to `User` but currently have no writer code — only
  models/migrations/factories exist for them.

Only [DepartmentController](app/Http/Controllers/Department/DepartmentController.php) and
[XrayController](app/Http/Controllers/Xray/XrayController.php) exist for the staff-facing side today, and
both just render a static view (`department.index`, `xray.index`) — the transfer/upload/inbox workflows
implied by the CSS (`pages/inbox.css`, `pages/viewer.css`) and model layer are not wired up yet.

## Frontend

No JS framework and no Tailwind — hand-written CSS with a design-token base. `resources/css/app.css` just
`@import`s, in order: `tokens.css` → `base.css` → `layout.css` → `components.css` → per-page files
(`inbox`, `viewer`, `dashboard`, `auth`). Keep new page styles in their own `pages/*.css` file and reuse
tokens/components rather than hardcoding values. Vite entry points are `resources/css/app.css`,
`resources/js/app.js`, and `resources/js/viewer.js` ([vite.config.js](vite.config.js)); Blade pulls them in
with `@vite(...)`.

## Laravel Boost

This project uses [Laravel Boost](https://laravel.com/docs/boost) — an MCP server plus the guidelines block
below (auto-managed; `php artisan boost:install` rewrites everything between the
`<laravel-boost-guidelines>` tags in place, so don't hand-edit inside them). It covers command/testing/style
conventions already — this file only documents things Boost's generic guidelines can't know: what this app
actually does and how its pieces fit together.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

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

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

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

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

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

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
