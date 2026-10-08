# Pesonal — project guide

Personal expense tracker. Mobile-first web app, single user, no auth, runs locally. Receipt/thermal-paper look, deadpan funny tone.

Monorepo: `api/` (Laravel — intentionally swappable for another backend later) · `web/` (Nuxt 4 + PrimeVue) · `design/` (UI specs). Runs natively on Windows (see Working environment). DB is SQLite. Contracts & roadmap live **outside the repo** in `../docs/pesonal/` (`C:\Users\Aby\Documents\aby\tests\docs\pesonal\`), not in git.

**The API source of truth is `../docs/pesonal/api-contract.md`.** Code the frontend against that document, never against Laravel specifics — the backend is a drop-in swap. Key rules: money is integer **centavos** in `amount` (`15000` = ₱150.00); currency is PHP only; every response wraps in `{ "data": ... }` (lists add `meta`); categories carry `color` + `emoji`; expenses read a nested `category` object and write `category_id`. The dashboard is one call: `GET /api/stats/monthly?month=YYYY-MM`.

## How we build the UI (read this first)

**Claude implements, the owner supervises.** He learned the frontend by building the dashboard and the app shell himself; from here he reviews rather than writes.

**Work in small, checkable steps.** One screen — or one coherent slice of one — per turn, then stop and report. Never batch several screens into a single turn, and never run ahead to the next roadmap item without being asked. The owner's instructions are step-by-step by design.

The loop:

1. Claude posts a **design spec** (an HTML mockup) in `design/` if the screen doesn't have one.
2. Claude **implements** the slice in `web/`, following the conventions below.
3. Claude **verifies** the slice, then **commits it straight to `main`** in the format below. No feature branch, no PR, no waiting for approval. The owner reviews afterwards with `git log` / `git show`; anything he wants changed becomes a follow-up commit.
4. Claude **stops and reports** what changed and why, naming the commit.

**Claude commits; the owner pushes and runs the app.** Commits stay local: Claude never pushes unless the owner asks for it in that conversation. Claude does **not** start, stop or restart the dev servers (`composer run dev`) — the owner runs it in his own terminal. When a step needs one restarted — a new top-level dir, a dependency, a `nuxt.config.ts` or `.env` change — **stop and hand the owner the exact copy-pasteable command(s)** (he forgets them), then verify once he says it's done. Checks that don't touch the running servers are fine: `npx nuxt typecheck`, `php artisan test`, `curl` against `:3000` / `:8080`.

Commit format: a `[feat]` / `[fix]` tag alone on the first line, then brief lowercase `- bullet` lines. **No co-author trailer** (this overrides the default).

**Explain the reasoning, not just the result.** The owner is comfortable reviewing frontend code but is still learning the tooling around it — PHP and Laravel tooling, git plumbing, Nuxt internals, TypeScript. Annotate commands in plain language and say *why* a non-obvious choice was made. He asks follow-up questions and expects real answers; "it works" is not one.

**Verify before claiming.** Run the check, read the output, then report. If something is unverified, say so.

Design specs live in `design/`. Status and the screen order live in `../docs/pesonal/ROADMAP.md`.

## Frontend conventions (Nuxt 4 + PrimeVue, code in `web/app/`)

- **SFC block order:** `<script>` → `<template>` → `<style>`. Always.
- **Keep components thin — but don't over-split.** Lift real logic into composables; the `useXxx.ts` (logic — context access, computeds) + `useXxxUI.ts` (UI state — refs like `is_open`, handlers like `handleClick`) pair is a tool you reach for when a component has genuine UI orchestration, **not** a rule for every component. Simple local UI state can just be `ref`s in `<script setup>`. When you do extract, the component only imports and destructures; `useI18n` lives in the UI composable, destructured as `{ t: translate }`. In a composable's return, list refs/computeds first, then a blank line, then functions.
- **Extract a region into its own component** when it's reused, large enough to clutter its parent, a clear nameable thing, or has its own state/logic — *readability alone is a valid reason*, not just reuse. Skip it for tiny logic-free one-offs (same don't-over-split rule). Build inline when unsure; lift it out once it earns it. A screen like the dashboard should read as an assembly of region components (`<DashboardHeader />`, `<TotalReceipt />`, …) in `components/<feature>/`. Nuxt prefixes component names by folder, so `components/dashboard/Header.vue` → `<DashboardHeader>` — don't repeat the prefix in the filename.
- **Shared state across a component tree = provide/inject context, not `useState`.** Wrap the composable in a `*Context.ts`: a `composable_context = ReturnType<typeof useX>` alias, a `const key = Symbol('x')`, a single `provideX()` that calls `useX()` and `provide(key, flow)` then returns it, and a `useXContext()` that injects and throws `new Error('useXContext')` if missing. Both exports are arrow functions. The composable and its `*Context.ts` live together in a `context/` folder. The parent that owns the state's lifetime calls `provideX()` once; descendants call `useXContext()`. **This is the default, not a law** — it fits state scoped to one component tree. When state genuinely outgrows it (large or complex, or truly global: read across unrelated screens, surviving navigation), reach for **Pinia** rather than bending context around it. Judgement call each time; say which one you picked and why.
- **Named functions** use `function name() {}`, not `const name = () =>`. Arrows are fine for inline callbacks (`map`, `computed`, `watch`) and for the `*Context.ts` exports. If a name must be snake_case (e.g. `open_modal`), use the arrow form — lint forces `function` declarations to camelCase.
- **Comment only what the code can't say.** Cut anything that restates a type, a name, or the line under it — `/** The full resource returned by /api/categories */` above `interface Category` is noise. Keep the ones that stop a bug coming back: **units** (`amount` is centavos), **contract traps** (`meta.total` is a row count, not a sum of amounts), and **the reason behind a non-obvious choice** (why calendar dates skip the timezone). One line beats a block. The `/** States */` · `/** Computed */` · `/** Functions */` section markers in composables stay.
- **No trailing newline** at the end of any file.
- Apply personal style first, then run eslint to catch project-specific overrides.

## Folder structure & data layer

- Nuxt defaults under `web/app/` (`pages/`, `components/`, `composables/`, `utils/`, `layouts/`), grouped into **feature subfolders**. The app shell (bottom nav + FAB) lives in `layouts/default.vue`, never in a page.
- **The data layer is three thin layers, each ignorant of the one above.** `app/utils/api.ts` is the **transport**: it owns the base URLs (a server render and the browser use different ones — see the gotchas), unwraps the `{ data }` envelope, and maps the contract's error shape to `ApiRequestError` — nothing else calls `$fetch` raw, so swapping the backend touches this file alone. `app/repositories/<resource>.ts` holds **one plain exported function per endpoint** (`listExpenses`, `createExpense`, …) — paths, query params and payload types live here and nowhere else. `app/composables/` owns **reactivity** — `useAsyncData`, refs, computeds, grouping — and calls repositories, never URLs. Repositories aren't auto-imported; import them explicitly.
- **Contract types** (`Category`, `Expense`, `MonthlyStats`, …) live in Nuxt 4's `shared/` dir; mirror `../docs/pesonal/api-contract.md`.
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

## Working environment

Everything runs **natively on Windows** — no Docker, no WSL. The repo is at `C:\Users\Aby\Documents\aby\tests\pesonal`; `git`, Node and PHP 8.4 (`C:\php84`) are all Windows installs.

One command runs everything in a single terminal, started by the owner:

```
cd api && composer run dev
```

It runs `recurring:materialize` once, then keeps three processes up through `concurrently`: `api` (`php artisan serve --port=8080`), `schedule` (`php artisan schedule:work`, which fires the standing-order job hourly) and `web` (`npm run dev` in `../web`). Ctrl+C stops all three; Windows may ask "Terminate batch job (Y/N)?" — answer Y.

The app is `http://localhost:3000`; the API is `http://localhost:8080` (`public.apiBase` in `nuxt.config.ts`).

### Gotchas that have already cost real time

- **Ports 8000 and 8001 belong to Homestead.** The owner's VirtualBox VM (another project, usually running) forwards them, which is why the API is on 8080. If `artisan serve` is started without `--port`, it silently drifts to the next free port and the frontend misses it.
- **A terminal opened before an install keeps the old PATH.** After installing or switching PHP/Node, open a new terminal; an old one still finds the previous version (`php -v` saying 8.3 means a stale terminal, not a broken install).
- **Node must be 22.18+.** Nuxt pulls in Babel 8, which declares `node ^22.18.0 || >=24.11.0`; older 22.x installs with `EBADENGINE` warnings.
- **Keep `package-lock.json` in step with `package.json`.** `npm ci` refuses to install when they disagree (that happened once: `vue-chartjs` was added without its lock entry). After adding or removing a dep, commit both files.
- **A new top-level directory needs a dev-server restart.** Nuxt scans `shared/`, `server/`, `layouts/` at boot; HMR won't pick up a folder that didn't exist. Symptom: `#shared` alias unresolved, or a new `server/api/` route 404s.
- **Stale Vite module graph.** If the *browser* shows an error but a direct `Invoke-WebRequest`/`curl` to the same URL returns correct HTML, the code is fine and the dev server's in-memory graph is stale — restart `npm run dev`. That disagreement between the two is the diagnostic; if both fail, it's a real bug.
- **Data lives in Laravel's SQLite file.** `api/database/database.sqlite` (gitignored) survives restarts. Reset to the seed with `cd api && php artisan migrate:fresh --seed`, which wipes everything entered through the UI.
- **Larastan needs more than PHP's default 128M.** Run it as `composer analyse` (in `api/`), which passes `--memory-limit=512M`; at 128M a cold run crashes its worker (`exit code 255`).

### Checks

```
cd web && npx nuxt typecheck
```

```
cd api && php artisan test
```

```
cd api && composer analyse
```

The typecheck prints nothing and exits 0 on success. Run both before committing — **Vite strips types without checking them**, so type errors never surface at runtime and the dev server will happily serve broken code.

**TypeScript is pinned to `^5` deliberately.** TypeScript 7 is the native Go compiler; its npm package doesn't ship `lib/tsc`, which `vue-tsc` requires. `vue-tsc`'s peer range (`>=5.0.0`) accepts TS 7 without warning and then fails at runtime. Don't let a dependency bump move it to 7.

There is **no ESLint configured** yet, despite the convention above mentioning it.

## Verifying your work

- **Drive the UI, don't just read the HTML.** Most of this app's behaviour is client-side — sheets, the keypad, pagination, filters. Use the browser tools to click and type, then read the result.
- **Vue emits dynamic classes before static ones.** `:class="{ selected: … }"` on `class="option"` renders `class="selected option"`. Regex counting `class="option` silently undercounts and has produced false "bugs" more than once.
- **Vue reactivity is async.** Reading the DOM in the same tick as a click shows the *old* value. Click, then read in a separate step. If a value looks unchanged, suspect the read before the code.
- Both list screens `await useAsyncData`, so data is server-rendered. `status === 'pending'` only appears on a *client* refetch (month/category change) — loading skeletons are a transition state, not a page-load state.