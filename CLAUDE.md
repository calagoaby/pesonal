# Pesonal — project guide

Personal expense tracker. Mobile-first web app, single user, no auth, runs locally. Receipt/thermal-paper look, deadpan funny tone.

Monorepo: `api/` (Laravel — intentionally swappable for another backend later) · `web/` (Nuxt 4 + PrimeVue) · `docs/` (contracts & roadmap) · `design/` (UI specs). Runs via Docker. DB is SQLite.

**The API source of truth is `docs/api-contract.md`.** Code the frontend against that document, never against Laravel specifics — the backend is a drop-in swap. Key rules: money is integer **centavos** in `amount` (`15000` = ₱150.00); currency is PHP only; every response wraps in `{ "data": ... }` (lists add `meta`); categories carry `color` + `emoji`; expenses read a nested `category` object and write `category_id`. The dashboard is one call: `GET /api/stats/monthly?month=YYYY-MM`.

## How we build the UI (read this first)

**Claude implements, the owner supervises.** He learned the frontend by building the dashboard and the app shell himself; from here he reviews rather than writes.

**Work in small, checkable steps.** One screen — or one coherent slice of one — per turn, then stop and report. Never batch several screens into a single turn, and never run ahead to the next roadmap item without being asked. The owner's instructions are step-by-step by design.

The loop:

1. Claude posts a **design spec** (an HTML mockup) in `design/` if the screen doesn't have one.
2. Claude **implements** the slice in `web/`, following the conventions below.
3. Claude runs the git mechanics: feature branch off `main`, commits in the format below, push, open the PR. **The owner reviews and merges — Claude never merges and never pushes to `main`.**
4. Claude reports what changed and why, then waits.

Commit format: a `[feat]` / `[fix]` tag alone on the first line, then brief lowercase `- bullet` lines. **No co-author trailer** (this overrides the default).

Design specs live in `design/`. Status and the screen order live in `docs/ROADMAP.md`.

## Frontend conventions (Nuxt 4 + PrimeVue, code in `web/app/`)

- **SFC block order:** `<script>` → `<template>` → `<style>`. Always.
- **Keep components thin — but don't over-split.** Lift real logic into composables; the `useXxx.ts` (logic — context access, computeds) + `useXxxUI.ts` (UI state — refs like `is_open`, handlers like `handleClick`) pair is a tool you reach for when a component has genuine UI orchestration, **not** a rule for every component. Simple local UI state can just be `ref`s in `<script setup>`. When you do extract, the component only imports and destructures; `useI18n` lives in the UI composable, destructured as `{ t: translate }`. In a composable's return, list refs/computeds first, then a blank line, then functions.
- **Extract a region into its own component** when it's reused, large enough to clutter its parent, a clear nameable thing, or has its own state/logic — *readability alone is a valid reason*, not just reuse. Skip it for tiny logic-free one-offs (same don't-over-split rule). Build inline when unsure; lift it out once it earns it. A screen like the dashboard should read as an assembly of region components (`<DashboardHeader />`, `<TotalReceipt />`, …) in `components/<feature>/`. Nuxt prefixes component names by folder, so `components/dashboard/Header.vue` → `<DashboardHeader>` — don't repeat the prefix in the filename.
- **Shared state across a component tree = provide/inject context, not `useState`.** Wrap the composable in a `*Context.ts`: a `composable_context = ReturnType<typeof useX>` alias, a `const key = Symbol('x')`, a single `provideX()` that calls `useX()` and `provide(key, flow)` then returns it, and a `useXContext()` that injects and throws `new Error('useXContext')` if missing. Both exports are arrow functions. The composable and its `*Context.ts` live together in a `context/` folder. The parent that owns the state's lifetime calls `provideX()` once; descendants call `useXContext()`.
- **Named functions** use `function name() {}`, not `const name = () =>`. Arrows are fine for inline callbacks (`map`, `computed`, `watch`) and for the `*Context.ts` exports. If a name must be snake_case (e.g. `open_modal`), use the arrow form — lint forces `function` declarations to camelCase.
- **No trailing newline** at the end of any file.
- Apply personal style first, then run eslint to catch project-specific overrides.

## Folder structure & data layer

- Nuxt defaults under `web/app/` (`pages/`, `components/`, `composables/`, `utils/`, `layouts/`), grouped into **feature subfolders**. The app shell (bottom nav + FAB) lives in `layouts/default.vue`, never in a page.
- **One API wrapper** (`app/utils/api.ts`) owns the base URL, unwraps the `{ data }` envelope, and maps the contract's error shape — components never call `$fetch` raw. Thin per-resource composables (`useMonthlyStats`, `useExpenses`, `useCategories`) build on it, so swapping the backend touches one file.
- **Contract types** (`Category`, `Expense`, `MonthlyStats`, …) live in Nuxt 4's `shared/` dir so the app and the mock `server/api/` routes share one definition; mirror `docs/api-contract.md`.
- Pure helpers (`formatPeso`, `percent`) live in `utils/`. Graduate to `app/features/` or Nuxt Layers only if this outgrows the flat structure.

## Design tokens — Receipt Roll

Theme: a deadpan thermal-paper receipt. Flat surfaces, dashed perforation dividers, mono numerals.

| Role | Value |
|---|---|
| App bg (desk) / receipt paper | `#EAE5D9` / `#FBF8F1` |
| Ink / faded / perforation | `#1C1A17` / `#8A857B` / `#D8D2C4` |
| Accent — stamp red (CTA · FAB · over-budget) | `#C8472E` |
| Under-budget (spent less = good) / highlighter (peak day) | `#2F7D52` / `#E8B23A` |
| Categories | Housing `#534F8C` · Food `#C9692E` · Groceries `#5E7A3E` · Transport `#3F6F97` · Fun `#B0577B` · Other `#8A857B` |
| Type — numbers/totals/headers | Mono (`Spline Sans Mono`), `tabular-nums`; section headers mono ALL-CAPS, letter-spacing .08em |
| Type — body/names/notes | Sans (`Plus Jakarta Sans`) |
| Type scale | CSS vars in `main.css`: `--text-hero` 34 · `--text-title` 18 · `--text-body` 14 · `--text-label` 12 (section headers, captions) · `--text-micro` 11 |
| Radius | CSS vars: `--radius-card` 6 (paper slip) · `--radius-pill` 999 (rubber stamp · pills · FAB) |
| Spacing | CSS vars: `--space-xs` 4 · `--space-s` 8 · `--space-m` 12 · `--space-l` 16 · `--space-xl` 20 · `--space-2xl` 24; screen gutter 16 |

Signature: flat — no soft shadows; dashed perforation dividers, a torn zig-zag hero edge, barcode foot. The PrimeVue preset at `web/app/theme/pesonal-preset.ts` bakes the stamp red into the `primary` palette.

The mockups in `design/` read in these var names — anything still literal px there (icon sizes, hairlines, barcode art) is an intentional one-off. Snap off-scale values to the nearest step; never mint a token per pixel.

## Run

Whole stack via Docker from the repo root (`docker compose up`). Confirm the API and web ports in `docker-compose.yml`.