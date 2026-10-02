# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is the Laravel + Livewire "Flux" starter kit (`laravel/livewire-starter-kit`), currently unmodified beyond
scaffolding — there is no app-specific domain code yet (no custom models beyond `User`, no business routes beyond
auth/settings/dashboard). Treat `app/`, `routes/`, `resources/views/livewire/` as the starting skeleton to build
"OpenCafe" on top of, following the existing starter-kit conventions rather than introducing new patterns.

## Commands

### PHP / Laravel
- Run all tests: `php artisan test --compact` or `vendor/bin/pest`
- Run a single test file: `vendor/bin/pest tests/Feature/Auth/AuthenticationTest.php`
- Run tests matching a name: `vendor/bin/pest --filter=testName`
- Create a Pest feature test: `php artisan make:test --pest SomeFeatureTest` (don't prefix with `Feature/`)
- Format code (required after editing any PHP file): `vendor/bin/pint --dirty --format agent`
- List routes: `php artisan route:list` (filter with `--path=`, `--name=`, `--method=`)
- Tinker: `php artisan tinker --execute 'User::count();'`

### JS / assets
- Dev build: `npm run dev` (Vite)
- Production build: `npm run build`
- Full local dev stack (server + queue + logs + vite, concurrently): `composer run dev`

### Local setup notes
- SQLite DB lives at `database/database.sqlite`.
- Flux UI Pro components require composer auth credentials (`composer config http-basic.composer.fluxui.dev <user> <license-key>`) — CI pulls these from GitHub secrets.

## Architecture

**Stack**: Laravel 12 (streamlined structure — no `app/Http/Kernel.php`; middleware/providers are registered in
`bootstrap/app.php` and `bootstrap/providers.php`), Livewire + **Volt** (single-file components), **Flux UI**
(`livewire/flux`) for the component library, Tailwind v4 via `@tailwindcss/vite`, Pest for testing.

**Volt routing/mounting**: Volt components are plain `.blade.php` files registered directly as routes via
`Volt::route('path', 'dot.notation.view')` in `routes/web.php` / `routes/auth.php` — there are no matching
Livewire component classes. `VoltServiceProvider` mounts component roots from
`resources/views/livewire/` and `resources/views/pages/`. All current Volt views live under
`resources/views/livewire/{auth,settings}/`.

**Auth flow**: Entirely driven by Volt components under `resources/views/livewire/auth/*` (login, register,
forgot/reset password, confirm-password, verify-email) wired up in `routes/auth.php`. Email verification's
non-Volt step (`verification.verify`) uses a real controller: `app/Http/Controllers/Auth/VerifyEmailController.php`.
Logout is a Livewire "action" class (`app/Livewire/Actions/Logout.php`) bound directly as a POST route handler.

**Settings pages**: `routes/web.php` registers `settings/{profile,password,appearance}` as Volt routes under
`resources/views/livewire/settings/`, sharing `resources/views/components/settings/layout.blade.php`.

**Layouts**: Two Blade layout families under `resources/views/components/layouts/`: `app` (sidebar + header, for
authenticated pages) and `auth` (card/simple/split variants, for the auth screens). Pages select a layout via
Volt's `#[Layout(...)]` or the Blade `<x-layouts.*>` component.

**Flux icons**: Custom SVG icons not shipped with Flux are added as Blade files under
`resources/views/flux/icon/*.blade.php` and referenced as `<flux:icon.name>`.

## Conventions and skills

Detailed, enforced-by-skill conventions already exist in `.claude/skills/` and apply automatically — read the
relevant skill before working in that area rather than re-deriving conventions:
- `laravel-best-practices` — Eloquent, migrations, validation, caching, queues, routing, security, style
- `volt-development` — functional vs. class-based Volt components
- `fluxui-development` — Flux component usage/composition
- `tailwindcss-development` — utility-class conventions
- `testing-best-practices` — coverage, naming, isolation, assertions

Key points not to rediscover by reading code:
- PHP: constructor property promotion, explicit return types/param type hints on all methods, curly braces always,
  casts defined via a `casts()` method (not the `$casts` property).
- Laravel: use `php artisan make:*` generators (`--no-interaction`) instead of hand-writing boilerplate files; use
  named routes + `route()` for links; Eloquent API Resources + versioning for any API.
- Pint must be run (`vendor/bin/pint --dirty --format agent`) after any PHP edit, before considering work done.
- Tests: Pest only, feature tests over unit tests by default, use model factories (check for factory states first).
- This project has no `.ai/rules` directory currently — if one appears later, read `.ai/rules/index.md` first per
  the Laravel Boost guidelines in `AGENTS.md`.
