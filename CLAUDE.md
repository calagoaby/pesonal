# Pesonal — project guide

Personal expense tracker. Mobile-first web app, runs locally. Receipt/thermal-paper look, deadpan funny tone.

**Multi-user, invite-only.** Sign-up is closed to strangers: the owner adds an email with `pesonal:invite`, and only then can that person register (or sign in with Google/Facebook for the first time). An invited person gets a private ledger: every category, expense and standing order belongs to one user, and someone else's id answers 404. Auth is **Sanctum cookie sessions** (no tokens in the browser): email + password through Fortify (`/api/auth/*`), Google and Facebook through Socialite. The data routes need a signed-in, *verified* user.

Monorepo: `api/` (Laravel — intentionally swappable for another backend later) · `web/` (Nuxt 4) · `design/` (UI specs). Runs natively on Windows (see Working environment). DB is SQLite. Contracts & roadmap live **outside the repo** in `../docs/pesonal/` (`C:\Users\Aby\Documents\aby\tests\docs\pesonal\`), not in git.

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

## Frontend conventions (Nuxt 4, code in `web/app/`)

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
- **The data layer is three thin layers, each ignorant of the one above.** `app/utils/api.ts` is the **transport**: it owns the base URLs (a server render and the browser use different ones — see the gotchas), sends the session cookie and the `X-XSRF-TOKEN` header (one retry on a 419), unwraps the `{ data }` envelope, and maps the contract's error shape to `ApiRequestError` — nothing else calls `$fetch` raw, so swapping the backend touches this file alone. `app/repositories/<resource>.ts` holds **one plain exported function per endpoint** (`listExpenses`, `createExpense`, …) — paths, query params and payload types live here and nowhere else. `app/composables/` owns **reactivity** — `useAsyncData`, refs, computeds, grouping — and calls repositories, never URLs. Repositories aren't auto-imported; import them explicitly.
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

Signature: flat — no soft shadows; dashed perforation dividers, a torn zig-zag hero edge, barcode foot. Icons are `@primeicons/vue` SVG components (`import Home from '@primeicons/vue/home'`); PrimeVue itself was removed.

The mockups in `design/` read in these var names — anything still literal px there (icon sizes, hairlines, barcode art) is an intentional one-off. Snap off-scale values to the nearest step; never mint a token per pixel.

## Working environment

Everything runs **natively on Windows** — no Docker, no WSL. The repo is at `C:\Users\Aby\Documents\aby\tests\pesonal`; `git`, Node and PHP 8.4 (`C:\php84`) are all Windows installs.

One command runs everything in a single terminal, started by the owner:

```
cd api && composer run dev
```

It runs `recurring:materialize` once, then keeps four processes up through `concurrently`: `api` (`php artisan serve --port=8080`), `schedule` (`php artisan schedule:work`, which fires the standing-order job hourly), `queue` (`php artisan queue:listen --tries=1`, which sends the queued verify and reset mail) and `web` (`npm run dev` in `../web`). Ctrl+C stops all four. In PowerShell or cmd, Windows may ask "Terminate batch job (Y/N)?" — answer Y; a closing "Script … returned with error code" line after Ctrl+C is expected, not a failure. If that first `recurring:materialize` fails (e.g. migrations not run yet), nothing starts — fix it or run `php artisan migrate` first.

The app is `http://localhost:3000`; the API is `http://localhost:8080` (`public.apiBase` in `nuxt.config.ts`).

### Accounts and sign-in

- **`api/.env` keys added for auth** (also in `.env.example`): `FRONTEND_URL=http://localhost:3000` (CORS origin, and where reset/verify/social redirects land) · `SANCTUM_STATEFUL_DOMAINS=localhost:3000` (requests from here get the session) · `APP_URL=http://localhost:8080` (social callback URLs are built from it) · `SESSION_DOMAIN=` (empty, host-only cookie) · `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` · `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET`. Empty provider keys send that button back to `/login?error=failed`. Any `.env` change needs a restart.
- **Provider callback URLs** to register with Google / Meta: `http://localhost:8080/api/auth/google/callback` and `…/facebook/callback`.
- **Mail goes to the log, via the queue.** Verify and reset mail is queued, so it reaches `api/storage/logs/laravel.log` only once the `queue` process runs it (no worker, no email); copy the link from there (see the `&amp;` gotcha).
- **`ADMIN_EMAILS`** (comma-separated, in `api/.env`; restart after a change) names the owner(s). An admin email counts as invited, and a *verified* admin sees More → Admin → Invites, which lists, adds and removes invites through `/api/admin/*`. To everyone else (signed out, unverified, not on the list) those routes answer the same status and JSON body (404) as a route that doesn't exist. There is no database flag: taking admin away is an `.env` edit.
- **Sign-up is invite-only.** `php artisan pesonal:invite {email}` adds an email to the list (case-insensitive), `pesonal:invites` lists it and shows who has opened an account, `pesonal:uninvite {email}` removes one. Registering locally needs `pesonal:invite <email>` first, or the form answers 422 `This ledger is invite-only…`. First-time Google/Facebook sign-in with an uninvited email lands on `/login?error=not_invited`. Existing accounts are unaffected by an uninvite. `register` and `forgot-password` share one 6/min-per-IP budget (`auth-mail`); past it is a 429.
- **Ledger values are encrypted in the database.** `expenses.amount`/`note`, `recurring.amount`/`note` and `categories.name` hold Laravel `Crypt` ciphertext (`APP_KEY`), read and written through `MoneyCast`/`EncryptedText`; `categories.name_index` is a per-user HMAC fingerprint that keeps names unique (case-sensitive, trimmed). The API is unchanged. **`APP_KEY` is now the only way to read anyone's ledger** — keep a copy outside Render; losing it loses the data. `php artisan pesonal:encrypt-ledger` encrypts any plaintext stragglers and repairs fingerprints (idempotent; prints counts and ids, never values): it runs at every container boot and daily at 00:10 Manila in production. Seeders write ciphertext too.
- **`php artisan pesonal:claim {email}`** gives every category, standing order and expense with no owner to that account, merging same-named categories into the account's own, and keeps every row's timestamps. Only needed for data from before multi-user (or a restored old backup); `user_id` is now NOT NULL, so new rows always have an owner.
- **`php artisan migrate:fresh --seed` wipes every account**, not just the ledger, and creates one verified demo account, `demo@pesonal.test` / `password`, owning all the seed data.

### Gotchas that have already cost real time

- **Ports 8000 and 8001 belong to Homestead.** The owner's VirtualBox VM (another project, usually running) forwards them, which is why the API is on 8080. If `artisan serve` is started without `--port`, it silently drifts to the next free port and the frontend misses it.
- **A terminal opened before an install keeps the old PATH.** After installing or switching PHP/Node, open a new terminal; an old one still finds the previous version (`php -v` saying 8.3 means a stale terminal, not a broken install).
- **Node must be 22.18+.** Nuxt pulls in Babel 8, which declares `node ^22.18.0 || >=24.11.0`; older 22.x installs with `EBADENGINE` warnings.
- **Keep `package-lock.json` in step with `package.json`.** `npm ci` refuses to install when they disagree (that happened once: `vue-chartjs` was added without its lock entry). After adding or removing a dep, commit both files.
- **`APP_ROUTES_CACHE` must be a relative path on Windows.** An absolute `C:/...` value is treated as relative and prefixed with the app folder.
- **`/privacy` is open in every auth state** (the guard's `OPEN` list in `middleware/auth.global.ts`), signed in or out, even with the API down.
- **A new top-level directory needs a dev-server restart.** Nuxt scans `shared/`, `server/`, `layouts/` at boot; HMR won't pick up a folder that didn't exist. Symptom: `#shared` alias unresolved, or a new `server/api/` route 404s.
- **Stale Vite module graph.** If the *browser* shows an error but a direct `Invoke-WebRequest`/`curl` to the same URL returns correct HTML, the code is fine and the dev server's in-memory graph is stale — restart `composer run dev`. That disagreement between the two is the diagnostic; if both fail, it's a real bug.
- **"504 Outdated Optimize Dep" on the first load after a fresh install.** Vite pre-bundles dependencies on first use and the page that triggered it never hydrates — it renders but ignores clicks. One browser refresh fixes it. Dev only.
- **Data lives in Laravel's SQLite file.** `api/database/database.sqlite` (gitignored) survives restarts; its ledger values are encrypted with the local `APP_KEY` in `api/.env`. Reset to the seed with `cd api && php artisan migrate:fresh --seed`, which wipes everything entered through the UI — accounts and login sessions included (sessions live in the same file).
- **Larastan needs more than PHP's default 128M.** Run it as `composer analyse` (in `api/`), which passes `--memory-limit=512M`; at 128M a cold run crashes its worker (`exit code 255`).
- **Larastan's result cache can hide errors.** A "0 errors" straight after a migration change may be a cache hit; run `vendor/bin/phpstan clear-result-cache` before trusting it.
- **Windows PHP ships no CA bundle.** Any outbound HTTPS from PHP fails with `cURL error 60 … unable to get local issuer certificate` — first seen on the social sign-in token exchange (Composer brings its own bundle, which hid it). Fix: `cacert.pem` from curl.se into `C:\php84\extras\ssl\`, point `curl.cainfo` and `openssl.cafile` in `C:\php84\php.ini` at it, restart.
- **Links copied from `laravel.log` need `&amp;` → `&`.** The log holds the email's HTML, so signed links read `…&amp;signature=…`; pasted as-is they fail with "Invalid signature."
- **The social routes live in `routes/web.php`, not `api.php`** (still at `/api/auth/{provider}/…`). They're top-level browser navigations with no `Origin` header, so Sanctum's stateful check would give them no session, and Socialite's state check needs one.
- **Sanctum only gives a session to requests from the frontend.** In tests, chain `$this->fromFrontend()` (sets `Origin`/`Referer` to `:3000`) or the session never starts; and after register/login/logout call `$this->app['auth']->forgetGuards()` before re-reading `/api/user`, or the cached guard user makes the test prove nothing. PHPUnit also skips CSRF — probe 419s with curl and an `Origin: http://localhost:3000` header.
- **`SessionGuard::logout()` after deleting the user re-inserts the row** (it cycles the remember token and `save()`s the deleted model). Use `logoutCurrentDevice()`, as `DeleteAccountController` does.
- **Never `LIKE` or `substr` a date column.** Filter by a half-open range (`>= first of the month`, `< first of the next`; `App\Support\Month`). `substr` on a `date` column breaks on Postgres, and `LIKE` only works there through an implicit text cast (and can't use the index). The old `2026-13` rollover was Carbon's month parsing, not `LIKE`.
- **Postgres returns `SUM`/`COUNT` as strings.** Cast aggregates before they reach JSON.
- **Never `SUM`, `ORDER BY`, `WHERE` or `pluck` on an encrypted column** (amount, note, category name): the database only holds ciphertext. Totals are summed in PHP (`StatsService`); category names are compared through `name_index`. Raw `DB::table`/`insert()` writes bypass the casts, so they must encrypt themselves (see `App\Support\LedgerRewrite` and the seeders).
- **Explicit route bindings live in `AppServiceProvider`, never in `routes/*.php`.** Production runs `route:cache`, which skips the route files, so a `Route::bind` there vanishes (Undo and restore would break). Ids are also guarded numeric, because Postgres errors on `'abc'` where SQLite shrugs; a non-numeric id is a 404.
- **`winget install PostgreSQL.PostgreSQL.17` sets no password you know.** Reset it: `pg_hba.conf` to `trust`, `ALTER USER`, back to `scram-sha-256`, restart the service.
- **A pasted `PS C:\…>` prompt makes PowerShell run `Get-Process`** (`PS` is its alias). Paste commands without the prompt.
- **Nuxt dev listens on IPv6 `[::1]:3000`.** Probe `http://localhost:3000`, not `127.0.0.1`.
- **Check a mockup by looking** (screenshot or layout measurements), not by reading its text: a broken layout reads fine as text.
- **SQLite `->change()` rebuilds the table (copy → drop → rename) and Laravel runs SQLite migrations without a transaction**, so a failure mid-way loses the table. Follow `2026_10_13_000004_require_user_id_on_ledger_tables.php`: foreign keys off *outside* a transaction (the pragma is ignored inside one), rebuild in one transaction, `PRAGMA foreign_key_check` before committing.

### Postgres tests

Production will be Postgres (Neon), so the API suite runs on both drivers. Locally: Postgres 17 at `127.0.0.1:5432`, database `pesonal_test`, credentials in the gitignored `api/.env.testing.pgsql` (never open or print it). `composer test:pgsql` runs the suite against it; `api/tests/pgsql.php` reads only the `DB_*` keys and never echoes them. Filters go after `--`: `composer test:pgsql -- --filter=Stats`. CI's `api` job runs both drivers.

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

```
cd api && composer test:pgsql
```

The typecheck prints nothing and exits 0 on success. Run both before committing — **Vite strips types without checking them**, so type errors never surface at runtime and the dev server will happily serve broken code.

**TypeScript is pinned to `^5` deliberately.** TypeScript 7 is the native Go compiler; its npm package doesn't ship `lib/tsc`, which `vue-tsc` requires. `vue-tsc`'s peer range (`>=5.0.0`) accepts TS 7 without warning and then fails at runtime. Don't let a dependency bump move it to 7.

**Lint:** `cd web && npm run lint` (ESLint through `@nuxt/eslint`). `web/eslint.config.mjs` encodes the block order, no trailing newline and camelCase function rules. Run it with the typecheck before committing.

## Verifying your work

- **Drive the UI, don't just read the HTML.** Most of this app's behaviour is client-side — sheets, the keypad, pagination, filters. Use the browser tools to click and type, then read the result.
- **Vue emits dynamic classes before static ones.** `:class="{ selected: … }"` on `class="option"` renders `class="selected option"`. Regex counting `class="option` silently undercounts and has produced false "bugs" more than once.
- **Vue reactivity is async.** Reading the DOM in the same tick as a click shows the *old* value. Click, then read in a separate step. If a value looks unchanged, suspect the read before the code.
- Both list screens `await useAsyncData`, so data is server-rendered. `status === 'pending'` only appears on a *client* refetch (month/category change) — loading skeletons are a transition state, not a page-load state.