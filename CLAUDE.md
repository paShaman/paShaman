# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- PHP >= 8.5, Laravel ^13.17
- Filament ^5.6 (admin panel)
- Inertia.js ^2 + Vue 3 (Composition API) + Tailwind CSS 4
- Vite 8 (the only build tool)
- MySQL, file-based cache/sessions
- `danog/madelineproto` — Telegram bot integrations (see "Red zones")

`minimum-stability` in composer.json is `dev`: the project deliberately runs cutting-edge dependency versions, so breaking changes are possible.

## Commands

PHP/Composer/Artisan run via **PowerShell** (Herd-managed PHP on Windows), not via the Bash tool.

```powershell
php artisan ...        # artisan commands
composer ...           # composer commands
```

```bash
npm run dev      # Vite dev server (HMR)
npm run build    # production build into public_html/build
```

There is currently no test runner in the project.

## Project structure

`public_html/` is the web server's public directory (the equivalent of `public/` in a stock Laravel app), configured explicitly in `vite.config.js` (`publicDirectory: 'public_html'`). Laravel's entry point is `public_html/index.php`.

```
app/
├── Models/                      # Project, Money, Page, User — plain-looking Eloquent models,
│                                 # but most business logic actually lives inside them (see below)
├── Http/Controllers/            # HomeController, ProjectController — thin, render Inertia pages
├── Http/Middleware/              # HandleInertiaRequests — shares site.social on every page
├── Filament/                     # Isolated admin area (Livewire/Blade), see below
├── Providers/Filament/           # PaShamanPanelProvider — Filament panel config (/paShaman)
└── Image.php                     # GD helpers (blur/pixelate) used for "Hidden" placeholder projects

resources/
├── js/
│   ├── app.js                   # createInertiaApp, resolves pages via import.meta.glob
│   ├── Pages/                   # Home.vue, Project.vue, 404.vue — one per route
│   ├── Components/{about,project,projects,shared}/, Layouts/
├── css/app.css                  # Tailwind entry
└── views/app.blade.php          # the single Blade file (@inertia + @vite)

config/site.php                  # startYear/countries (homepage counters), social links
routes/web.php                   # all routes; routes/api.php is currently unused (Inertia props instead)
```

### Red zones — do not touch without an explicit user request

- `vendor/`, `node_modules/`
- `public_html/scripts/` — a separate ecosystem outside Laravel with its own `_env.php`, containing Telegram (MadelineProto), Yandex Alice (`hermes-alice.php`), Alfa-Bank (`alfa.php` + certificates in `scripts/cert/`) and AWG/VPN stats integrations. Unrelated to the main application.
- `public_html/gimnasia2004/` — archive of the old HTML site, pending migration to Inertia
- `public_html/tree/`, `public_html/images/`
- `app/Filament/` — isolated Livewire/Blade admin area, **not** being migrated to Inertia

## Architecture

The public site is entirely Inertia + Vue 3; the admin panel is entirely Filament (Livewire/Blade) — two isolated worlds that should not be mixed. New public-site code should be Inertia/Vue 3 only; do not add new Blade templates (except the single existing `resources/views/app.blade.php`).

Data reaches the public-site frontend through Inertia props from controllers (`Inertia::render(...)`) — there's no general JSON API for the SPA. `HomeController::projectsApi`/`tagsApi` exist specifically as AJAX endpoints for client-side pagination/filtering of the projects grid, not as a general-purpose API layer.

### The `Project` model holds the core domain logic

`app/Models/Project.php` is not a thin Eloquent model but effectively a service: `getList()` (pagination/search/tag filtering), `getTags()` (tag aggregation with an exclude list), `getProjectDetail()` (detail page data: prev/next, `_verN` versions, yearly variants like `-2023`/`-2024`, list of "works" tagged `работа`). Tags are stored as a space-separated string in the `tags` column — keep that in mind when touching filtering logic.

Project `active` values: `0` — not shown, `1` — normal, `2` — "hidden" (rendered publicly as a pixelated "Hidden" placeholder, only revealed with the `full` cookie, which is set by visiting `/full`).

### Filament panel

Panel id/path is `paShaman` (`/paShaman`), provider is `App\Providers\Filament\PaShamanPanelProvider`. Resources are Money (with stats/chart widgets) and Users; custom form/table schemas are split into `Schemas/`/`Tables/` subfolders per resource (Filament 5 convention).

## Working rules

- Never commit `.env`, never hardcode secrets in code.
- Max 3 attempts to solve a problem the same way — after that, change approach or ask the user; don't loop.
- All Git operations (commit, push, merge, reset, etc.) are performed by the user themselves; the agent may read history (`git log`, `git diff`) for analysis only.
