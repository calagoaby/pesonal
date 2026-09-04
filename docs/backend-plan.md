# Pesonal backend — implementation plan

Step 17 of `docs/ROADMAP.md` swaps the Nuxt frontend from its throwaway mock routes
(`web/server/api/`) onto a real Laravel API. This document is the spec for building that
API. It was derived by reading the mock routes, `docs/api-contract.md`, `web/shared/types/`,
and the deterministic seed in `web/server/utils/mockDb.ts` — but **`docs/api-contract.md`
remains the source of truth**; where this plan and the contract disagree, the contract wins
and this doc should be corrected.

**How to use it.** Build in the order under "Build order" below. Each resource section gives
the exact routes, validation rules (with messages), status codes, and response shapes to
reproduce. The mock routes are the behavioural reference — the Laravel seeder must reproduce
`mockDb.ts` so the app looks identical the moment the base URL flips. The mocks are preserved
in git history (last present at `c63a57d`) and, until step 17 merges, on disk.

The Laravel app is at `api/` (Laravel 13, PHP 8.3, SQLite). API routing is **not yet enabled** —
see Foundation.

---

## Foundation & cross-cutting

This section covers the wiring every endpoint depends on: turning on API routing, cross-origin access, the response envelope, the validation-error shape, the database, the schema, and the Eloquent models. Per-endpoint validation rules, messages, and JSON payloads live in the resource sections that follow; here we establish the machinery those sections assume.

### 1. Enabling API routing

`api/bootstrap/app.php` currently registers only `web`, `commands`, and `health` — there is **no `api` route file wired up**, so `routes/api.php` (even once created) is dead. Laravel 13 does not scaffold `routes/api.php` by default.

Change `withRouting(...)` to add the `api` argument:

```
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',   // ← add this line
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Then create `api/routes/api.php` (the resource sections define its contents). Routes registered through this file are automatically prefixed with `/api` and given the `api` middleware group — which matches the contract's "all endpoints under `/api`" rule, so **do not add a manual `/api` prefix** inside the route file or you get `/api/api/...`.

**Already handled — do not duplicate:** the `withExceptions` block already calls `shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'))`. This forces every exception under `/api/*` to render as JSON (the `{ "message": ... }` / `{ "message", "errors" }` shapes) instead of an HTML error page, regardless of the request's `Accept` header. That is exactly what the contract needs; leave it as-is.

**Container note (for the owner, not code):** `routes/api.php` is a new route file and `bootstrap/app.php` is boot-time config. After this change the API container must be recreated, not hot-reloaded — hand the owner `docker compose up -d api` (or a `php artisan optimize:clear` inside the container) when it's time to test.

### 2. CORS

The browser runs at `http://localhost:3000` (Nuxt) and will call the API at `http://localhost:8000` (Laravel) — a **cross-origin** request. Without CORS headers the browser blocks every call. Laravel ships the `HandleCors` middleware in the global stack by default (Laravel 13 keeps it registered), but it reads `config/cors.php`, which **does not exist in this repo yet** (confirmed: no `api/config/cors.php`). Laravel falls back to its packaged default config when the file is absent; that default allows all origins for `api/*`, which technically works, but publish an explicit config so the setup is legible and pinned:

Create `api/config/cors.php` with:

- `'paths' => ['api/*']` — only API routes get CORS headers (health/web untouched).
- `'allowed_methods' => ['*']` — GET, POST, PATCH, DELETE, plus the OPTIONS preflight.
- `'allowed_origins' => ['http://localhost:3000']` — the Nuxt dev origin. (Widen later if the app is served elsewhere; avoid `['*']` combined with credentials.)
- `'allowed_headers' => ['*']` — lets `Content-Type: application/json` through the preflight.
- `'exposed_headers' => []`, `'max_age' => 0`.
- `'supports_credentials' => false` — this app has **no auth and no cookies** (single user, no Sanctum session); keep it false. `true` is incompatible with a wildcard origin anyway.

No Sanctum is needed — there is no authentication in v1. `HandleCors` alone is sufficient. `config/cors.php` is read at boot/config-cache time, so recreating or clearing config on the API container is required after adding it.

**Frontend swap (context, not our change):** `docker-compose.yml` currently sets `NUXT_PUBLIC_API_BASE: ""` for the `web` service (empty → the app hits its own in-process mock routes under `web/server/api`). When these endpoints are live the owner flips that to `NUXT_PUBLIC_API_BASE: "http://localhost:8000"` — browser-side calls go to the host-published port, so it's `localhost`, not the compose service name `api`. That env change needs `docker compose up -d web` (restart won't reload compose env). Flag this to the owner; it is not something the Laravel side does.

### 3. Response envelope — `{ data }` and `{ data, meta }`

Every response wraps in `{ "data": ... }`; list endpoints add a sibling `"meta"`. The nested-vs-flat asymmetry (Expense/Recurring **read** a nested `category` object but **write** `category_id`) makes this a transformation problem, not just a wrapper problem. **Recommendation: Eloquent API Resources + ResourceCollections**, one per resource. Rationale:

- Resources already emit a top-level `data` key by default — the single-resource envelope is free.
- They give one authoritative place to shape each row: cast `amount` to a plain int, format dates, and embed the nested `category` via a `CategoryRefResource` (id/name/color/emoji **only**, no `created_at` — see §7). This keeps the read/write asymmetry in one file per resource instead of scattered array-building in controllers.
- A `ResourceCollection` produces `{ "data": [...] }`, and when you return `SomeResource::collection($paginator)` over a `LengthAwarePaginator`, Laravel appends a `meta` (and `links`) block automatically — but its default `meta` shape does **not** match the contract (contract wants exactly `current_page`, `per_page`, `total`, `last_page`; Laravel's default paginator meta also includes `from`, `to`, `path`, `links`, and a nested `links` object). So for the paginated `GET /api/expenses`, **override the collection's `meta`** to emit exactly the four keys the frontend's `ListMeta` type declares, and drop `links`. Do this in the ExpenseCollection's `paginationInformation()` / `toArray()` rather than leaking Laravel's default.

A global response macro is the alternative but is rejected: it wraps but does not transform, so you'd still hand-build the nested-category shape in every controller. Resources do both. Keep controllers thin — they query, paginate, and return a Resource/Collection.

Two envelope traps to honor everywhere:

- `meta.total` is a **row count**, not a sum of `amount`. (Called out in the contract and the `ListMeta` type.)
- The nested `category` in a read is the **ref** shape (4 fields), never the full Category (which has `created_at`).

### 4. The 422 validation-error shape

The contract's error body is exactly:

```
{ "message": "...", "errors": { "field": ["msg", ...] } }
```

**Laravel's default `ValidationException` renderer already produces exactly this shape** — `message` plus an `errors` map of field → array-of-strings, at HTTP 422 — when the request is treated as JSON. Because `shouldRenderJsonWhen(is('api/*'))` is set (§1), every `/api/*` validation failure renders as JSON automatically. **No custom exception handler, no formatter override, and no wrapper is needed for 422.** Use `$request->validate([...])` or FormRequest classes and the shape falls out for free.

One nuance to carry into the endpoint sections: the **mock** returns a fixed top-level message `"The given data was invalid."` and its own per-field messages (e.g. `"The color must be a hex value."`, `"The name field is required."`). Laravel's default top-level message is `"The given data was invalid."` in most versions — matches. Per-field messages differ from Laravel's stock wording; where a specific message matters, the endpoint section will pin it via custom validation messages. The **shape** is settled here; the **strings** are per-endpoint.

Non-422 errors (`404`, `409`) carry `message` alone, no `errors` key. Emit these with `response()->json(['message' => '...'], 404|409)` or by throwing/aborting with a message. Examples reproduced from the mock: category 404 → `"That category is gone."`; category delete 409 → `` `Still tags {n} {expense|expenses}. Move or delete those first, then it can go.` `` (these belong to the Categories section but confirm the non-422 body is `{ message }` only).

### 5. SQLite — migrate & seed workflow

`DB_CONNECTION=sqlite` is already set in `api/.env` (the MySQL vars are commented out), and `api/database/database.sqlite` **already exists** (~98 KB on disk). So the connection is real and file-based; no `DB_DATABASE` path is needed — Laravel defaults the sqlite database to `database/database.sqlite`.

Workflow:

- Build schema + seed in one shot: **`php artisan migrate --seed`** (runs pending migrations, then `DatabaseSeeder`).
- Reset during development: `php artisan migrate:fresh --seed` (drops all tables, re-migrates, re-seeds). Use this freely — the data is disposable, mirroring the in-memory mock.
- The seeder should reproduce `mockDb.ts`: the **8 categories** with their exact ids/names/colors/emoji (Housing `#534F8C`🏠, Groceries `#5E7A3E`🛒, Food `#C9692E`🍔, Transport `#3F6F97`🚗, Fun `#B0577B`🎉, Utilities `#7A6A3E`💡, Health `#2F7D52`💊, Other `#8A857B`🧾) and the **6 recurring templates** (§7 seed table below). Expenses can be seeded from the same deterministic rules or left to accumulate live — the frontend does not depend on a specific expense corpus, only on shape.

Commands run **inside the API container**; hand the owner `docker compose exec api php artisan migrate --seed` when the migrations/seeder are ready. Do not run mutating artisan commands as part of writing this spec.

### 6. Timestamps

The contract returns full ISO 8601 UTC for `created_at` / `updated_at` (e.g. `2026-06-17T10:03:00Z`). **Laravel does this by default** — every model with `timestamps` casts these to `Carbon` and serializes them as ISO 8601 in JSON. Ensure the app timezone stays UTC (`config/app.php` `'timezone' => 'UTC'`, Laravel's default) so the `Z` suffix is correct. `date` on an expense is a **calendar date only** (`YYYY-MM-DD`, no time, no tz) — cast it to `date` (not `datetime`) and serialize as `Y-m-d` in the Resource so no timezone shift ever creeps in. Same for recurring `start_date` / `end_date`.

### 7. Migrations overview

Three domain tables. Money columns are **integer centavos** — use `bigInteger` (a year of rent-scale rows sums past the 32-bit signed limit; `amount` itself is small but keep totals safe and be uniform). All tables carry Laravel `timestamps()` (`created_at`, `updated_at` — `nullable timestamp`s that Laravel manages).

| Table | Column | Type | Null? | Default | Notes |
|---|---|---|---|---|---|
| **categories** | `id` | `id()` (bigIncrements) | no | — | PK |
| | `name` | `string` | no | — | **unique** (contract: name unique) |
| | `color` | `string(7)` | no | — | hex `#RRGGBB` |
| | `emoji` | `string` | no | — | single emoji (store as utf8mb4-safe string; SQLite is fine) |
| | `created_at` / `updated_at` | `timestamp` | yes | — | `timestamps()` |
| **expenses** | `id` | `id()` | no | — | PK |
| | `amount` | `bigInteger` | no | — | centavos, `> 0` (enforced in validation) |
| | `date` | `date` | no | — | calendar `YYYY-MM-DD`, no time |
| | `note` | `text` (or `string`) | **yes** | null | optional free text |
| | `category_id` | `foreignId` | no | — | FK → `categories.id` |
| | `recurring_id` | `foreignId` | **yes** | null | FK → `recurring.id`; set only when materialized from a template |
| | `created_at` / `updated_at` | `timestamp` | yes | — | |
| **recurring** | `id` | `id()` | no | — | PK |
| | `amount` | `bigInteger` | no | — | centavos, `> 0` |
| | `note` | `text` (or `string`) | **yes** | null | optional |
| | `day_of_month` | `unsignedTinyInteger` | no | — | 1–31, clamped to month length at materialization |
| | `start_date` | `date` | no | — | |
| | `end_date` | `date` | **yes** | null | null = open-ended |
| | `active` | `boolean` | no | `true` | paused templates kept, not materialized |
| | `category_id` | `foreignId` | no | — | FK → `categories.id` |
| | `created_at` / `updated_at` | `timestamp` | yes | — | |

**Table name:** the resource is `recurring` (contract paths are `/api/recurring`). Name the table **`recurring`** and point the model's `$table` at it, or name it `recurring_expenses` and set `protected $table = 'recurring'`? — recommend the table be **`recurring`** to match the domain word used everywhere; set `Recurring` model `$table = 'recurring'` explicitly since Laravel would otherwise pluralize the model name oddly.

**Foreign keys & constraints:**

- `expenses.category_id` → `categories.id`: **`restrictOnDelete()`** (or no cascade). The contract requires `DELETE /api/categories/{id}` to return **409 when any expense references it** — so the DB must not silently cascade-delete or null out. Enforce the 409 in the controller (count referencing expenses first, as the mock does), and back it with a restricting FK so a stray delete can't orphan data.
- `expenses.recurring_id` → `recurring.id`: nullable, **no FK constraint**. The Recurring DELETE invariant (and the mock) require already-materialized expenses to keep their `recurring_id` **pointing at the now-deleted template id** — the stamp must *not* be nulled. `nullOnDelete()` would clear it (wrong), and a restricting FK would block the template delete. So the column is a plain nullable `foreignId` with no `constrained()`; orphaned ids are allowed by design. (Corrected from an earlier draft that recommended `nullOnDelete()` — see the Recurring DELETE section.)
- `recurring.category_id` → `categories.id`: same restrict posture as expenses (a category in use by a template shouldn't vanish; the mock's 409 check counts expenses only, but restricting here is safe — decide in the Categories section whether templates also block deletion; **flag:** the mock's 409 check looks at `expenses` only, not `recurring`, so a category used solely by a template would currently delete — recommend the contract's intent (409 "still referenced") extend to recurring too, but match the mock's expenses-only behavior unless the owner says otherwise).

**Indexes:**

- `categories.name` unique (from the unique rule).
- `expenses(date)` — the list sorts by `date desc, id desc` and filters by `month` (a date range); index `date`. A composite `(date, id)` index optionally supports the exact sort.
- `expenses.category_id`, `expenses.recurring_id` — FK columns (Laravel indexes FKs by default when using `foreignId()->constrained()`).
- `recurring.category_id` — FK.

**Migration order:** `categories` first, then `recurring`, then `expenses` (expenses references both). If `foreignId()->constrained()` is used, the referenced tables must exist first.

**Seed reference — recurring templates** (reproduce exactly from `mockDb.ts`, all `start_date` in 2026, category by seeded id):

| id | note | amount | day | start_date | end_date | active | category_id |
|---|---|---|---|---|---|---|---|
| 1 | Apartment rent | 1500000 | 1 | 2026-01-01 | null | true | 1 (Housing) |
| 2 | Internet | 180000 | 5 | 2026-03-01 | null | true | 6 (Utilities) |
| 3 | Spotify | 19400 | 15 | 2026-02-01 | null | true | 5 (Fun) |
| 4 | Gym | 150000 | 15 | 2026-01-01 | null | **false** | 7 (Health) |
| 5 | Water bill | 62000 | 20 | 2026-01-01 | null | true | 6 (Utilities) |
| 6 | Netflix | 108600 | 20 | 2026-01-01 | 2026-12-01 | true | 5 (Fun) |

### 8. Models & relationships

Three Eloquent models under `app/Models/`.

- **Category**
  - `hasMany(Expense::class)` and `hasMany(Recurring::class)`.
  - `$fillable = ['name', 'color', 'emoji']`.
  - No casts needed beyond timestamps.

- **Expense**
  - `belongsTo(Category::class)` and `belongsTo(Recurring::class)` (the latter nullable — `recurring_id` may be null).
  - `$fillable = ['amount', 'date', 'note', 'category_id', 'recurring_id']`.
  - `$casts = ['amount' => 'integer', 'date' => 'date']`. `amount` must serialize as a bare integer (centavos), never a float or formatted string. `date` casts to a date-only Carbon; serialize `Y-m-d` in the Resource.
  - **Write vs read asymmetry** lives in the Resource + controller: the controller validates and mass-assigns `category_id`; the ExpenseResource embeds `category` via a CategoryRef resource and never exposes `category_id` on read.

- **Recurring** (`protected $table = 'recurring'`)
  - `belongsTo(Category::class)`; `hasMany(Expense::class)` (the rows it materialized).
  - `$fillable = ['amount', 'note', 'day_of_month', 'start_date', 'end_date', 'active', 'category_id']`.
  - `$casts = ['amount' => 'integer', 'day_of_month' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'active' => 'boolean']`. `active` **must** serialize as a JSON boolean (`true`/`false`), not `1`/`0` — the frontend `RecurringExpense.active` is `boolean`; the cast guarantees this.

Eager-load `category` on all reads (`with('category')` or `->load('category')`) so the nested object is present without an N+1, and so the Resource can shape the ref. Recurring and Expense reads both embed the ref; the Category's own `created_at` is excluded from the ref (only present on the standalone Category resource).

**Files referenced:** `api/bootstrap/app.php` (routing change), new `api/config/cors.php`, new `api/routes/api.php`, migrations under `api/database/migrations/`, seeder `api/database/seeders/DatabaseSeeder.php`, models under `api/app/Models/`, Resources under `api/app/Http/Resources/`.

---

## Categories endpoints

CRUD for the category set. The frontend treats categories as a **small, unpaginated** collection: `GET /api/categories` returns every row with **no `meta`**. All responses obey the global rules — single resources wrap in `{ "data": ... }`, the 422 body is exactly `{ "message", "errors" }`, and every non-422 error carries `message` alone. Color/emoji are first-class category fields; there is **no usage-count field** on the resource (see note below).

### Data model — `categories` table

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | integer | PK, auto-increment | server-assigned |
| `name` | string | not null | contract marks **unique** — the mock does **not** enforce it (see flag) |
| `color` | string (7) | not null | hex `#RRGGBB`, e.g. `#534F8C` |
| `emoji` | string | not null | one emoji |
| `created_at` | timestamp | not null | ISO 8601 UTC on read |
| `updated_at` | timestamp | — | present in Laravel's default timestamps; **not serialized** on the category resource (contract shape omits it) |

**Serialized shape** (API resource) — exactly these five keys, in this order, nothing more:

```
{ "id": int, "name": string, "color": string, "emoji": string, "created_at": ISO-8601-UTC }
```

`updated_at` exists on the row for PATCH bookkeeping but must **not** appear in the JSON (the frontend `Category` type is `CategoryRef` + `created_at` only). No `expense_count` / usage field is ever included — DELETE's 409 conveys the count in prose instead.

Seed (matches `mockDb.ts`, `created_at` = `2026-01-01T00:00:00Z` for all): Housing `#534F8C 🏠`, Groceries `#5E7A3E 🛒`, Food `#C9692E 🍔`, Transport `#3F6F97 🚗`, Fun `#B0577B 🎉`, Utilities `#7A6A3E 💡`, Health `#2F7D52 💊`, Other `#8A857B 🧾` (ids 1–8 in that order).

---

### GET `/api/categories` — list all (unpaginated)

- **200**. Body `{ "data": Category[] }`. **No `meta`** — intentional; the frontend's list composable expects a bare `data` array here, unlike expenses/recurring.
- **Ordering:** the mock returns the seed array as-is, i.e. **`id` ascending**. The contract does not specify an order. Recommend an explicit `orderBy('id')` (ascending) so results are deterministic and match the seed the frontend was built against.
- No query params, no filtering.

### POST `/api/categories` — create

- **Body:** `{ name, color, emoji }`. (`CategoryPayload`.) No `id`/`created_at` accepted.
- **Validation** — run all rules, collect every failure, return them together. Exact messages the frontend surfaces:

| Field | Rule | Exact message |
|---|---|---|
| `name` | required, non-empty after trim (mock: must be a string and `.trim()` truthy) | `The name field is required.` |
| `color` | required, matches `/^#[0-9A-Fa-f]{6}$/` (mock validates format only; a missing/empty value fails the same regex test) | `The color must be a hex value.` |
| `emoji` | required, non-empty string (mock: any truthy string) | `The emoji field is required.` |

- **On failure → 422**, body exactly:
  ```
  { "message": "The given data was invalid.", "errors": { "<field>": ["<message>"] } }
  ```
  `message` is the literal `The given data was invalid.` (matches contract's example). Only failing fields appear in `errors`.
- **On success → 201**, body `{ "data": Category }` with the full five-key shape. Server sets `id`, trims `name` before storing (`body.name.trim()`), stores `color`/`emoji` verbatim, and stamps `created_at` = now (ISO 8601 UTC).

### GET `/api/categories/{id}` — single

- In the contract's endpoint table but **has no mock route** (only list/post/patch/delete exist under `web/server/api/`). Implement it to the contract regardless.
- **404** if not found — reuse the message `That category is gone.` for consistency with PATCH/DELETE.
- **200** → `{ "data": Category }`, same five-key shape.

### PATCH `/api/categories/{id}` — partial update

- **404 first**, before reading/validating the body: if no row with `id`, return **404** `{ "message": "That category is gone." }`. (Mock resolves `id` via `Number(param)`; a non-numeric id yields `NaN` and thus 404.)
- **Partial:** validate a field **only if it is present** (`!== undefined`). Sending `{ "color": "#000000" }` alone is valid and leaves `name`/`emoji` untouched.
- **Per-field rules and messages are identical to POST**, but gated on presence:
  - `name` present → must be a non-empty (trimmed) string, else `The name field is required.`
  - `color` present → must match `/^#[0-9A-Fa-f]{6}$/`, else `The color must be a hex value.`
  - `emoji` present → must be a truthy string, else `The emoji field is required.`
- **On failure → 422**, same `{ "message": "The given data was invalid.", "errors": {…} }` shape.
- **On success → 200**, body `{ "data": Category }` reflecting the merged row. Apply `name.trim()` on write. Recommend touching `updated_at` (still not serialized).
- **Contract-vs-mock note on stale refs:** the mock mutates the category in place, but expense rows *snapshot* the category object at creation, so already-recorded expenses keep the old name/color. That is a mock artifact of storing objects by value. In Laravel, expenses reference the category by `category_id` and read it via relation, so a rename **will** reflect on existing expenses. This is a behavior difference, not a contract rule — the contract says nothing here. **Real DB behavior (live reference) is correct; do not replicate the mock's snapshot.**

### DELETE `/api/categories/{id}` — delete (409 if referenced)

- **404 first:** no such row → **404** `{ "message": "That category is gone." }`.
- **Referential guard → 409:** if **any expense** references this category, refuse. Count the referencing expenses (`expenses where category_id = id`), then return **409** with the count embedded in `message` (the error shape carries no data field, so the count lives in prose). Exact wording, singular/plural aware:
  - one expense: `Still tags 1 expense. Move or delete those first, then it can go.`
  - N expenses (N≠1): `Still tags {N} expenses. Move or delete those first, then it can go.`
  - Template: `Still tags ${used} ${used === 1 ? 'expense' : 'expenses'}. Move or delete those first, then it can go.`
- **Recurring templates:** the mock only counts `expenses`, **not** `recurring`. To match, count expenses only. However, a recurring template also holds a `category_id` FK, so deleting a category still referenced by a standing order would orphan/break it. **Recommend** the guard also count recurring templates (or rely on a DB FK `restrict`), but if you do, keep the message's noun accurate. Flag for the owner: mock ignores recurring; decide whether the guard should include them.
- **On success → 204**, empty body (return `null` / no content). Hard delete row.

---

### Cross-cutting flags (mock vs contract)

1. **`name` uniqueness.** Contract: `name` is `unique`. Mock: **not enforced** — you can create two "Food" categories. Recommendation: **contract wins** — add a `unique:categories,name` rule. Choose the 422 message for the frontend; suggest `The name has already been taken.` (Laravel default) under `errors.name`. Confirm wording with the owner since the mock never exercised this path.
2. **Emoji "single emoji".** Contract: "required, single emoji". Mock: only checks a **truthy string** (multi-char passes). Recommendation: keep validation permissive (required non-empty string) to match the mock and avoid brittle grapheme counting; treat "single emoji" as a UI concern. Flag if the owner wants server-side enforcement.
3. **`color` message wording.** Mock message is `The color must be a hex value.` — not Laravel's default regex message. Use this literal string so the frontend shows what it was built against.
4. **ID generation.** Mock computes `Math.max(...ids) + 1` in JS (works only because the seed is non-empty; an empty set would yield `-Infinity`). **Use the database auto-increment PK instead** — monotonic, gap-tolerant after deletes, and safe when the table is empty. Do not reproduce the `Math.max` scheme.
5. **Envelope discipline.** GET list is the one endpoint that returns `data` with **no `meta`**; every other category response is `{ "data": <object> }` except 204 (empty) and error bodies (`{ "message", … }`).

---

## Expenses endpoints

Base path `/api`. Every response wraps in `{ "data": ... }`; the list endpoint adds `meta`. All money is integer **centavos** (`15000` = ₱150.00) — never a float, never a peso value. Currency is fixed PHP with no per-row currency field. Reads return a **nested `category` object**; writes accept **`category_id`** only. `recurring_id` is read-only and never set through these endpoints (it is stamped only by the recurring-materialization job).

### Data model — `expenses` table

| Column | Type | Notes |
|---|---|---|
| `id` | integer PK, auto-increment | server-assigned |
| `amount` | integer | centavos, strictly `> 0`. Store the integer; do **not** use a decimal/money column that could round. |
| `date` | date (`YYYY-MM-DD`, no time) | the calendar date of the spend |
| `note` | string, nullable | free text |
| `category_id` | integer FK → `categories.id` | required; must reference an existing category |
| `recurring_id` | integer FK → `recurring.id`, nullable | set only by the materialization job; never writable via HTTP here |
| `created_at` / `updated_at` | timestamp | serialized as full ISO 8601 UTC, e.g. `2026-06-17T10:03:00Z` |

**Serialization (every read of an Expense).** Eager-load the category and emit exactly:

```json
{
  "id": 2000,
  "amount": 68000,
  "date": "2026-07-31",
  "note": "Lunch with team",
  "category": { "id": 3, "name": "Food", "color": "#C9692E", "emoji": "🍔" },
  "recurring_id": null,
  "created_at": "2026-07-31T01:00:00Z",
  "updated_at": "2026-07-31T01:00:00Z"
}
```

The nested `category` is the **ref shape** — `id`, `name`, `color`, `emoji` only. Do **not** include the category's `created_at` inside the nested object (mirror `CategoryRef`; `mockDb.findCategory` strips it). Serialize timestamps with a trailing `Z`, not a `+00:00` offset, to match the contract examples and seed.

---

### `GET /api/expenses` — list (paginated, filterable)

All query params optional.

| Param | Parse rule | Default | Bounds |
|---|---|---|---|
| `month` | string `YYYY-MM` | none (no month filter) | matched by `date` string prefix — see below |
| `category_id` | integer | none | any positive int; a non-numeric or `0` value means "no filter" |
| `page` | integer | `1` | floored at `1` (values `< 1` → `1`) |
| `per_page` | integer | `20` | capped at **100** (`MAX_PER_PAGE`); values above are clamped down, not rejected |

**Filter semantics (reproduce the mock exactly):**

- `month`: keep a row when `expense.date` **starts with** the `month` string (`date.startsWith(month)`). In SQL this is `date LIKE '2026-06%'` (or `strftime('%Y-%m', date) = :month`). Note the mock does **no** validation of the `month` format — a garbage value simply matches nothing and returns an empty page (still `200`, not `422`). Replicate that: an unparseable/malformed `month` is not an error, it just filters everything out.
- `category_id`: the mock coerces with `Number(query.category_id) || undefined`, so `0`, empty, or non-numeric ⇒ no filter. When present, keep rows where `expense.category.id === category_id`. A `category_id` that matches no category is **not** an error here — it returns an empty result set at `200`.
- `month` and `category_id` combine with AND.

**Sort order:** `date` **desc**, then `id` **desc**. (The mock's underlying array is maintained in exactly this order; enforce it explicitly in the query — `ORDER BY date DESC, id DESC` — do not rely on insertion order.)

**Pagination math (offset/limit, 1-based page):**

- `start = (page - 1) * per_page`
- Return the slice `[start, start + per_page)` of the filtered+sorted set.
- `meta`:
  - `current_page` = the requested (floored) `page`
  - `per_page` = the effective (capped) `per_page`
  - `total` = **row count** of the filtered set (`matched.length`) — **NOT a sum of `amount`.** This is the single most important trap: `total` is how many expenses match, never a peso total.
  - `last_page` = `max(ceil(total / per_page), 1)` — so an empty result still reports `last_page: 1`, never `0`.
- A `page` beyond `last_page` is **not** an error: the slice is simply empty, `data: []`, `200`, with `current_page` echoing the requested page.

**Response shape:**

```json
{
  "data": [ /* Expense objects, newest date first */ ],
  "meta": { "current_page": 1, "per_page": 20, "total": 137, "last_page": 7 }
}
```

Always emit `meta` even when `data` is empty.

---

### `POST /api/expenses` — create → **201**

Request body (`ExpensePayload`): `amount`, `date`, `category_id`, `note?`.

**Validation** — accumulate all failures into one `errors` object, then return `422` if any exist. Exact messages (copy verbatim; these are the mock's strings, not Laravel's defaults):

| Field | Rule | Exact message |
|---|---|---|
| `amount` | must be an **integer** and `> 0` (mock: `Number.isInteger(amount) && amount > 0`) | `The amount field must be a positive number of centavos.` |
| `date` | must be a string matching `^\d{4}-\d{2}-\d{2}$` | `The date field must be a YYYY-MM-DD calendar date.` |
| `category_id` | must match an existing category id | `The selected category is invalid.` |

Notes on validation to match the mock precisely:

- `amount` rejects non-integers (floats like `150.5`), zero, and negatives. A string `"150"` is **not** an integer and must fail — do not coerce. (Laravel: `required|integer|min:1`, but override the message.)
- `date` is a **regex/format** check only — the mock does not verify the date is real (`2026-13-40` passes the regex). To match behavior exactly, validate format only. *(Recommendation: the contract calls it a "calendar date"; a real-date check is defensible, but if you add one, keep the same message. Flagging the divergence — the mock is format-only.)*
- `note` is **not validated** — absent, a string, or `null` all pass.
- Missing `amount`/`date`/`category_id` produce the same messages as invalid ones (the mock treats `undefined` as a failing value on all three).

**422 body** — exactly this shape, no `{ data }` envelope:

```json
{ "message": "The given data was invalid.", "errors": { "amount": ["The amount field must be a positive number of centavos."] } }
```

The mock uses the message **`The given data was invalid.`** (not Laravel's default `The given data was invalid` without period, and not per-Laravel wording). Set the response status to `422` and return the bare object — do **not** run it through Laravel's exception envelope if that would alter the shape.

**On success:**

- Create the row with `note = body.note ?? null` (absent note ⇒ stored `null`).
- `recurring_id` is always `null` on manual creates — never accept it from the body.
- Stamp `created_at` / `updated_at` = now (ISO 8601 UTC, `Z` suffix).
- Return `201` with `{ "data": <Expense> }`, the full nested-category serialization above.
- The new row must appear in the correct sort position on the next list call (date desc, id desc) — with a real DB and `ORDER BY`, this is automatic; no manual re-sort needed (the mock's `splice` is only because it holds an in-memory array).

---

### `GET /api/expenses/{id}` — single

- Found → `200`, `{ "data": <Expense> }` (full nested serialization).
- Not found → **404**, body `{ "message": "That entry is not on the roll." }` (exact string; no `errors` key).

---

### `PATCH /api/expenses/{id}` — partial update → **200**

Order of operations (match the mock):

1. **Not found first** → `404` with `{ "message": "That entry is not on the roll." }` *before* reading/validating the body.
2. **Partial validation** — validate **only fields present** in the body. A field is "present" when its key exists and is not `undefined`. Same rules and same exact messages as POST:
   - `amount` present → must be integer `> 0` → `The amount field must be a positive number of centavos.`
   - `date` present → must match `^\d{4}-\d{2}-\d{2}$` → `The date field must be a YYYY-MM-DD calendar date.`
   - `category_id` present → must resolve to an existing category → `The selected category is invalid.`
   - Any field **absent** is skipped entirely (no "required" errors on PATCH).
3. Any errors → `422` with `{ "message": "The given data was invalid.", "errors": {...} }`.

**Apply semantics — only sent fields change:**

- `amount` ← `body.amount ?? existing.amount`
- `date` ← `body.date ?? existing.date`
- `category` ← re-resolved from `body.category_id` when that key is present (and valid); otherwise unchanged.
- **`note` — the critical case:** the frontend now sends `note: null` to *clear* a note, and this must be honored and distinguished from an **absent** key:
  - key **absent** (`body.note === undefined`) → keep `existing.note` unchanged.
  - key **present and `null`** → set `note` to `null` (clears it).
  - key **present and a string** → set that string.
  - Mock logic: `note: body.note !== undefined ? (body.note ?? null) : existing.note`. In Laravel this means you cannot use plain `$request->input('note')` with a default — you must check `has('note')` / `array_key_exists('note', ...)` to tell absent from explicit `null`. Use `$request->exists('note')` (true even when the value is `null`) rather than `filled()`/`has()` semantics that treat `null` as missing.
- `recurring_id` is never touched by PATCH.
- Bump `updated_at` to now; `created_at` unchanged.

**Response:** `200`, `{ "data": <updated Expense> }`, full nested serialization (the re-resolved category if it changed).

**Re-sort on write:** because `date` can change, the row's position in the list can move. With a real DB + `ORDER BY date DESC, id DESC` this is automatic and no explicit re-sort step is needed (the mock's splice-and-reinsert exists only to keep its in-memory array ordered). Just ensure list ordering is always query-driven, not insertion-driven.

---

### `DELETE /api/expenses/{id}` → **204**

- Not found → **404**, `{ "message": "That entry is not on the roll." }`.
- Found → hard-delete the row, respond **204** with an empty body (no `{ data }`, no JSON — `null`).
- No 409 case here (409 belongs to categories that still have expenses; deleting an expense is always allowed, including one with a non-null `recurring_id` — the mock deletes it unconditionally).

---

### Cross-cutting requirements / flags

- **Centavos everywhere.** `amount` is an integer count of centavos on every read and write. Never divide/format on the backend; the frontend renders pesos. Seed/example values confirm the scale: `68000` = ₱680.00, `1500000` (rent) = ₱15,000.00.
- **Envelope discipline.** `{ "data": ... }` for single reads, POST, PATCH; `{ "data": [...], "meta": {...} }` for the list; bare `{ "message", "errors" }` for `422`; bare `{ "message" }` for `404`; empty body for `204`. Do not let Laravel's default exception handler reshape the `422`/`404` bodies.
- **Exact validation messages** are the mock's custom strings, not Laravel defaults — override `messages()` in the FormRequest for all three fields.
- **`month` and out-of-range `category_id`/`page` are not errors** — they filter to empty and return `200`. Only `amount`/`date`/`category_id` on write produce `422`.
- **Mock vs contract agreement:** shapes, sort order (`date` desc, `id` desc), pagination meta, and status codes all agree between `api-contract.md` and the mock. The one under-specification: the contract says `date` is a "calendar date" while the mock validates **format only** (a fake date like `2026-13-40` passes). Recommendation: keep the mock's format-only check for drop-in parity, or add a real-date check but keep the identical error message — either is contract-compatible, but note the behavioral difference for the frontend's test fixtures.

---

## Recurring endpoints + materialization

The `recurring_expenses` resource is a set of **monthly templates** the backend auto-materializes into real `Expense` rows. This section specs the five HTTP endpoints (reproducing the mock at `web/server/api/recurring*.ts` exactly) plus the materialization job, which has **no mock** and is derived from the contract.

### Table: `recurring_expenses`

| Column | Type | Constraints / notes |
|---|---|---|
| `id` | integer, PK, auto-increment | server-assigned |
| `amount` | integer | centavos, `> 0`. Never a float, never pesos. |
| `note` | string, nullable | free text; `null` when absent |
| `day_of_month` | unsigned tiny int | `1`–`31`. Stored as authored; **clamped to month length only at materialization**, never on write (a template can legitimately say "the 31st"). |
| `start_date` | date (`YYYY-MM-DD`) | required; calendar date, no time |
| `end_date` | date (`YYYY-MM-DD`), nullable | `null` = open-ended |
| `active` | boolean | default `true`; `false` = paused (kept, but not materialized) |
| `category_id` | integer, FK → `categories.id` | the write-side key; reads return a nested `category` object |
| `created_at` | timestamp | ISO 8601 UTC on read, e.g. `2026-06-17T10:03:00Z` |
| `updated_at` | timestamp | same format |

`Expense.recurring_id` is a nullable FK → `recurring_expenses.id`. **Do not cascade-delete** it (see DELETE below); use `nullOnDelete` or no DB-level FK action and leave the value intact — the contract requires materialized rows survive template deletion with their `recurring_id` still set.

### Response shape (single resource)

Every read/write of one template returns `{ "data": <RecurringExpense> }`:

```json
{
  "data": {
    "id": 1,
    "amount": 1500000,
    "note": "Apartment rent",
    "day_of_month": 1,
    "start_date": "2026-01-01",
    "end_date": null,
    "active": true,
    "category": { "id": 5, "name": "Housing", "color": "#534F8C", "emoji": "🏠" },
    "created_at": "2026-01-01T00:00:00Z",
    "updated_at": "2026-01-01T00:00:00Z"
  }
}
```

Field order is not significant, but the shape must match `web/shared/types/Recurring.ts` exactly: `note` is `string | null` (never omitted — emit explicit `null`); `end_date` is `string | null`; `active` is a real boolean; `category` is the **ref** shape (`id`, `name`, `color`, `emoji` — **no `created_at`** on the nested category, unlike a full Category resource). Timestamps serialize with a `Z` suffix. Use an API Resource/transformer so the write-only `category_id` never leaks into the read payload.

---

### `GET /api/recurring` — list all

- Returns **all** templates, **unpaginated**: `{ "data": [ <RecurringExpense>, … ] }`.
- **Ordering:** the mock returns them in **insertion / `id`-ascending** order (seed order 1…6) and does no sorting. Reproduce with `orderBy('id')`. The frontend groups them into "Every Nth" sections client-side, so backend sort order beyond a stable ascending `id` is not load-bearing — but keep it deterministic.
- **Contract vs mock discrepancy — flag:** the contract's Conventions say "Lists add `meta`." The mock's `recurring.get.ts` returns **no `meta`** (just `{ data }`), matching categories (also an unpaginated small set). **Mock wins:** omit `meta` for this endpoint. `meta` is for paginated lists (expenses) only; the recurring list is deliberately whole-set so the "monthly commitment total" on the screen is honest. Do not add a pagination envelope here.
- Empty set returns `{ "data": [] }`, `200`.

---

### `POST /api/recurring` — create → 201

**Accepted body fields:** `amount`, `category_id`, `day_of_month`, `start_date`, `note?`, `end_date?`, `active?`.

Note `active` **is** an accepted create field (the contract's endpoint table lists `active?`, and the mock reads `body.active ?? true`). Default to `true` when omitted or `null`.

**Validation** (run all rules, collect every failure — do not bail on first). Messages must match the mock **verbatim**:

| Field | Rule | Exact message |
|---|---|---|
| `amount` | present, integer, `> 0` (mock: `Number.isInteger(amount) && amount > 0`) | `The amount field must be a positive number of centavos.` |
| `category_id` | must reference an existing category | `The selected category is invalid.` |
| `day_of_month` | present, integer, `1`–`31` | `The day of month must be between 1 and 31.` |
| `start_date` | present, string matching `^\d{4}-\d{2}-\d{2}$` | `The start date must be a YYYY-MM-DD calendar date.` |
| `end_date` | if present and non-null, string matching `^\d{4}-\d{2}-\d{2}$` | `The end date must be a YYYY-MM-DD calendar date.` |
| `end_date` (cross-field) | if `end_date` present/valid **and** `start_date` valid, require `end_date >= start_date` | `The end date must be on or after the start date.` |

Cross-field details (mock behavior to reproduce precisely):
- `end_date` is treated as "present" only when it is **not `undefined` and not `null`** (`has_end`). A `null` or omitted `end_date` skips both the format and the cross-field check.
- The cross-field check is **string comparison** (`end_date < start_date`) — valid because both are zero-padded `YYYY-MM-DD`. Equal dates pass (`>=`).
- The cross-field rule is **suppressed if `start_date` already failed format** (`!errors.start_date`), so the user isn't shown a comparison error against garbage. In Laravel, `after_or_equal:start_date` naturally short-circuits similarly, but ensure you don't emit both a format error and a comparison error for the same bad `start_date`.
- The regex is strict on **shape only**, not calendar validity (`2026-13-40` passes the regex in the mock). Recommend the Laravel `date_format:Y-m-d` rule, which is stricter (rejects impossible dates) — this is a safe tightening; note it as an intentional improvement over the mock.

On any failure: **422**, body exactly `{ "message": "The given data was invalid.", "errors": { <field>: [<msg>] } }`.

On success: assign `id`, set `active` to `body.active ?? true`, set `note` to `note ?? null`, set `end_date` to the value or `null`, stamp `created_at` = `updated_at` = now, load the category ref, return `{ "data": … }` with status **201**.

---

### `GET /api/recurring/{id}` — single

- Found → `200`, `{ "data": <RecurringExpense> }`.
- Missing → **404**, body `{ "message": "That standing order is gone." }` (exact string, deadpan tone; no `errors` key). `getRouterParam` is coerced with `Number()` — a non-numeric id yields `NaN`, matches nothing, and 404s. Laravel route-model binding on an integer key gives the same 404; keep the message identical via a custom `ModelNotFound` handler for this resource.

---

### `PATCH /api/recurring/{id}` — partial update → 200

- Missing id → **404**, `{ "message": "That standing order is gone." }` (before any body validation).
- **Partial semantics:** validate **only fields present in the body**. Every rule is guarded by `field !== undefined`. An absent field keeps its existing value; a present field overwrites.
- Same per-field rules and **exact messages** as POST, applied only to present fields:
  - `amount` present → integer `> 0`.
  - `category_id` present → must exist; on success replace the nested category ref, else `The selected category is invalid.`.
  - `day_of_month` present → integer `1`–`31`.
  - `start_date` present → format regex.
  - `end_date` present and non-null → format regex. (Present-and-`null` clears the end date to open-ended — allowed, no format check.)
- **Cross-field (mock lines 46–53) — reproduce exactly:** compute the *effective* pair using whichever side is being written, falling back to the stored value:
  - `start = body.start_date ?? existing.start_date`
  - `end = body.end_date === undefined ? existing.end_date : body.end_date`
  - If neither `start_date` nor `end_date` failed format, and `end !== null`, and `end < start` → error on **`end_date`**: `The end date must be on or after the start date.`
  - This means: sending only a new `start_date` that is later than the **stored** `end_date` **fails** (the effective end is the old value). Likewise sending only a new `end_date` earlier than the stored `start_date` fails. Implement the comparison against the merged values, not just the incoming payload.
- `note`: present → `note ?? null` (can be cleared to `null`); absent → unchanged.
- `active`: mock uses `body.active ?? existing.active` — so present-and-`false` correctly turns it off, present-and-`null` leaves it unchanged, absent leaves it unchanged.

  > **Subtle mock trap to preserve exactly:** `amount`, `day_of_month`, `start_date` merge with `body.x ?? existing.x`, and `active` with `body.active ?? existing.active`. Because these use `??`, a payload value of `null` falls through to the existing value. Combined with validation (which only rejects when `!== undefined`), sending `"amount": null` would pass validation (guarded by `!== undefined`… wait: `null !== undefined` is true, so `Number.isInteger(null)` is false → it **does** 422). So `amount: null` → 422; `active: null` → validation has no `active` rule, and merge keeps existing. Match this: no validation rule on `active`'s type; treat `active: null` as "leave unchanged." Only accept `active` as a boolean toggle otherwise.

- On failure: **422**, same body shape.
- On success: bump `updated_at` to now, keep `created_at`, return `200` `{ "data": <updated> }`.

---

### `DELETE /api/recurring/{id}` → 204

- Missing id → **404**, `{ "message": "That standing order is gone." }`.
- Found → delete **only the template row**. Return **204** with an **empty body** (no envelope, `null`).
- **Critical invariant:** already-materialized `Expense` rows are **left untouched** — they keep their `recurring_id` value pointing at the now-deleted template id. Do **not** cascade, do **not** null out `recurring_id`. The FK must not have `ON DELETE CASCADE` or `ON DELETE SET NULL`. If a DB-level FK constraint would block the delete because expenses still reference it, drop the constraint's enforcement for this direction (or omit the FK) so the delete succeeds while the orphaned `recurring_id` stays. (Note: this is unlike categories, where a still-referenced category returns **409** — recurring templates delete freely regardless of referencing expenses.)

---

### MATERIALIZATION (scheduled job — no mock; derived from contract)

Not an HTTP endpoint. A backend job that turns active templates into real `Expense` rows so they flow into the normal expense list and all stats. The seed already demonstrates the end state: `mockDb.ts` hand-plants a "Rent share" expense with `recurring_id: 1` on the 1st of each month, matching template id 1 (`day_of_month: 1`). The job automates producing exactly those rows.

**Schedule:** run **daily**. Register in Laravel's scheduler (`routes/console.php` or the console kernel) as `->daily()` (once per day, e.g. `00:05` server time). Because it is idempotent (below), the exact time is not load-bearing and a missed day self-heals on the next run.

**Forward-only (contract Open-questions leaning):** the job **never backfills**. When a template's `start_date` is in the past, past months are **not** materialized retroactively. The job only ever creates the expense for a `day_of_month` occurrence **on or after "today"** as the schedule reaches it. A template created today with `start_date: 2026-01-01` produces its first expense on its next due day going forward, not one row per missed month. State this explicitly in the implementation: **do not loop over historical months.**

**Per-run algorithm** (run for "today", `$today`):

1. Determine the current month `Y-m` and its length `daysInMonth` (28/29/30/31, leap-aware — use Carbon `daysInMonth`).
2. Select templates where `active = true` **and** `start_date <= $today` **and** (`end_date IS NULL` **or** `end_date >= $today`). (Boundary rule: a template is live through its `end_date` inclusive, mirroring the `>=` used in the cross-field validation.)
3. For each, compute the **clamped due day** for the current month: `dueDay = min(day_of_month, daysInMonth)`. So `day_of_month = 31` fires on **Feb 28** (or 29 in a leap year), on **Apr 30**, on **31** in 31-day months. `day_of_month = 30` fires on **Feb 28/29**. This clamp is the whole reason `day_of_month` can exceed a given month's length.
4. Fire only when the occurrence has **arrived**: `$today->day >= dueDay` (contract: "whose `day_of_month` has arrived"). Because clamping caps `dueDay` at month end, a day-31 order still fires on the last day of a short month.
5. Build the target expense date = `Y-m-dueDay`.

**Idempotency (must-have — daily runs re-evaluate the same month all month long):** never create a second expense for the same `(recurring_id, month)`. Before inserting, check whether an `Expense` already exists with this `recurring_id` **and** a `date` within the current calendar month (`date LIKE 'Y-m-%'`, or `whereYear`/`whereMonth`). If one exists, skip. This makes the job safe to run every day, safe to re-run after a failure, and safe across a clock that crosses the due day repeatedly. (Match on month, not on exact date, so that an edited/clamped day still counts as "already done for this month.")

**On create**, insert an `Expense` with:
- `amount` = template.amount (centavos, copied)
- `date` = `Y-m-dueDay`
- `note` = template.note (copy, may be `null`)
- `category_id` = template.category_id (so the read side nests the same category)
- `recurring_id` = template.id
- `created_at` / `updated_at` = now

The materialized row is an ordinary expense from then on — editable, voidable, counted in `/api/stats/monthly` and the expense list, sorted by `date desc, id desc` like any other. Editing or deleting a materialized expense does **not** feed back to the template, and (per DELETE above) deleting the template does not remove its materialized rows.

**Edge cases to honor:**
- **`start_date` mid-month, after its due day:** if `day_of_month = 5`, `start_date = 2026-03-20`, then in March the occurrence's due day (5) precedes `start_date` — do not create for March; begin the following month. (Guard both `start_date <= $today` and, for the first month, `start_date <= Y-m-dueDay`; simplest correct rule: only materialize when `Y-m-dueDay >= start_date`.)
- **Paused templates** (`active = false`, e.g. seed "Gym"): skipped entirely; no rows, no catch-up when re-activated (forward-only).
- **`end_date` reached:** once `end_date < $today` the template stops materializing; the month containing `end_date` still materializes if its due day is on/before `end_date`.
- **Leap year:** `daysInMonth` for Feb resolves to 29 in leap years, so a day-29/30/31 order clamps to 29 that year, 28 otherwise — Carbon handles this; do not hardcode 28.

---

## Stats endpoints

Two read-only aggregation endpoints that power the dashboard. Both are pure derivations over the `expenses` table — no writes, no new columns. Every response wraps in `{ "data": ... }`. These endpoints return `data` as an **object** (monthly) or an **array** (trend); neither carries a `meta` block (they are not paginated lists). Money everywhere is integer **centavos**. No auth, single user, so no per-user scoping.

Source of truth: `docs/api-contract.md` §Stats. Mock reproduced: `web/server/api/stats/monthly.get.ts`, `web/server/api/stats/trend.get.ts`. Frontend shapes: `web/shared/types/Stats.ts`.

### Data these read

Both endpoints sum over the `expenses` table only. There is **no void / soft-delete field** in the contract, the `Expense` type, or the mock (`[id].delete.ts` is a hard delete), so stats sum **all** expense rows with no `whereNull(...)` filter. If a soft-delete/void column is ever added to `expenses`, both queries here must gain a `WHERE deleted_at IS NULL` (or `WHERE voided = 0`) clause — call that out at implementation time, but for v1 there is nothing to exclude.

The category object embedded in `by_category` is the **`CategoryRef`** shape — `{ id, name, color, emoji }` only, **no `created_at`** — identical to the nested `category` on an expense read. Load it by joining `expenses.category_id → categories`.

---

### `GET /api/stats/monthly?month=YYYY-MM`

Everything the single-month dashboard needs in one request.

**Query params**

- `month` — string `YYYY-MM`. Optional.
- **Default month:** the mock hardcodes `'2026-06'` (line 26) because the seed data is frozen in 2026-05/06/07 while the server clock reads 2026-08. That fixed default is a seed artifact, **not** intended behavior. For Laravel, default to the **current month** in the app timezone: `now()->format('Y-m')`. Flag this divergence; the current-month default is correct and the mock's `'2026-06'` should not be copied.
- **Validation:** the mock does no validation — a malformed `month` simply matches nothing via `startsWith`, yielding an all-zero result. Recommend matching that leniency (no 422) OR, if you prefer strictness, validate `month` against `/^\d{4}-(0[1-9]|1[0-2])$/` and return the standard 422 body `{ "message": ..., "errors": { "month": ["..."] } }`. The contract specifies neither; pick lenient to stay drop-in-compatible with the mock, and treat any unparseable `month` as "no rows" rather than an error.

**Response shape** (200), matching `MonthlyStats`:

```
{
  "data": {
    "month": "2026-06",              // echoes the resolved month string
    "total": 1234500,                // centavos, sum of all expenses in month
    "previous_total": 1100000,       // centavos, sum for the prior calendar month
    "change_pct": 12.2,              // float, 1 decimal, or null
    "by_category": [ { category, total, count }, ... ],
    "daily": [ { date, total }, ... ] // zero-filled, one per day of month
  }
}
```

**Field-by-field behavior**

- **`month`** — echo back the resolved month string exactly (the query value, or the current-month default).

- **`total`** — sum of `amount` (centavos) over all expenses whose `date` falls in `month`. Match against the calendar month: `WHERE date >= 'YYYY-MM-01' AND date < firstOfNextMonth`, or `WHERE strftime('%Y-%m', date) = :month` on SQLite. The mock uses `date.startsWith(month)` on the `YYYY-MM-DD` string — the `strftime`/prefix comparison is the faithful equivalent. Empty month ⇒ `0`.

- **`previous_total`** — same sum for the **prior calendar month**, computed by subtracting one month from `month` with year rollover (mock `previousMonth`: builds `new Date(year, index-2, 1)` so `2026-01` → `2025-12`). In Laravel: `Carbon::createFromFormat('Y-m', $month)->subMonthNoOverflow()->format('Y-m')`, then sum that month. Empty ⇒ `0`.

- **`change_pct`** — percent change vs the prior month, rounded to **1 decimal place**:
  - When `previous_total === 0`: **`null`** (no baseline; contract §218 and TS `number | null` both require this). Do not divide.
  - Otherwise: `round((total - previous_total) / previous_total * 100, 1)`. Mock computes `Math.round(((total - previous_total)/previous_total) * 1000) / 10`, which is exactly round-half-up to one decimal. PHP `round($x, 1)` matches. Result may be negative (spending dropped) or `0.0`. JSON-encode `12.0` as the number `12` — the frontend types it as `number`, so trailing-zero form is irrelevant.

- **`by_category`** — one row per category **that has at least one expense in the month** (categories with no spend are omitted, unlike `daily`). Each row:
  ```
  { "category": { "id", "name", "color", "emoji" }, "total": <centavos>, "count": <int> }
  ```
  `total` = sum of `amount` for that category in the month; `count` = number of expense rows (a **row count**, not a peso figure — same trap as `meta.total` elsewhere). **Sort by `total` descending.** SQL approach: `SELECT category_id, SUM(amount) AS total, COUNT(*) AS count FROM expenses WHERE <in month> GROUP BY category_id ORDER BY total DESC`, then hydrate the `CategoryRef` via a join or an `Category::find` map. Empty month ⇒ `[]`.
  - **Tie-break (flag):** neither the mock nor the contract defines ordering for equal totals. The mock's `Array.from(map).sort` is stable in V8, so ties fall in first-seen (date-desc) order — not reproducible in SQL. Recommend a deterministic secondary key: `ORDER BY total DESC, category_id ASC`. Note it; the frontend does not depend on tie order.

- **`daily`** — a **zero-filled** array with **exactly one entry per calendar day** of the month, in ascending date order, so the dashboard's bar chart renders contiguously with no gaps:
  ```
  [ { "date": "2026-06-01", "total": 0 }, { "date": "2026-06-02", "total": 32000 }, ... ]
  ```
  `date` is `YYYY-MM-DD`; `total` is the centavos sum for that exact day (0 if no expenses). Day count comes from the month itself (mock `daysInMonth` = `new Date(year, index, 0).getDate()`; Laravel `Carbon::createFromFormat('Y-m', $month)->daysInMonth`). **Implementation:** run one grouped query `SELECT date, SUM(amount) ... GROUP BY date` into a keyed collection, then loop `day = 1..daysInMonth`, formatting `sprintf('%s-%02d', $month, $day)` and reading the sum or `0`. Do **not** derive the day list from the returned rows — days with no spend must still appear. Length equals 28/29/30/31 accordingly.

**Aggregation approach (recommended)**

Three queries against `expenses`, all filtered to the month, plus one for the prior month's total:
1. `total` — `SUM(amount)` (or reuse the daily/category rows to derive it in-app; the mock sums the filtered set directly).
2. `previous_total` — `SUM(amount)` over the prior month.
3. `by_category` — `GROUP BY category_id` with `SUM` + `COUNT`, join categories, order by total desc.
4. `daily` — `GROUP BY date` with `SUM`, then zero-fill in PHP.

Query-builder `groupBy` is preferred over pulling all rows into a Collection, but at this data scale either is fine; the mock does it in-memory over the filtered array.

---

### `GET /api/stats/trend?months=N`

Multi-month totals for the trend line, **oldest → newest**, **ending with the current month**.

**Query params**

- `months` — integer, how many months of window including the current one. Optional, **default 6**.
- **Clamp to 1..24.** Mock: `const requested = Number(query.months) || 6; Math.min(Math.max(requested, 1), 24)`. Reproduce exactly, including these edge cases:
  - Missing, `0`, empty, or non-numeric ⇒ `6` (because `Number(...) || 6`; note `0` is falsy so `months=0` becomes 6, **not** clamped to 1).
  - Negative or `< 1` (e.g. `-3`) ⇒ **`1`**.
  - `> 24` ⇒ **`24`** (`MAX_MONTHS`).
  - Fractional (`6.7`) ⇒ mock passes it through `Number` unrounded into the loop bound; recommend flooring to int in Laravel (`(int) $months`) before clamping for clean behavior. Flag as a minor tidy-up, not a contract requirement.

**Response shape** (200), array of `MonthTotal`:

```
{
  "data": [
    { "month": "2026-01", "total": 980000 },
    { "month": "2026-02", "total": 1120000 }
  ]
}
```

`data` is a **bare array**, no `meta`. Each element: `month` (`YYYY-MM`) and `total` (centavos sum for that month, all categories).

**Behavior**

- **Window:** the last `N` calendar months ending with the **current month**. Anchor on the server clock (mock: `new Date()`). Generate by stepping `step = N-1 … 0` and taking `now minus step months`, so the array runs oldest → newest and the **last element is the current month**. Laravel: `Carbon::now()->startOfMonth()->subMonthsNoOverflow($step)->format('Y-m')`. Use `subMonthsNoOverflow` / first-of-month anchoring so a 31-day "now" doesn't skip a short month.
- **Zero-fill:** every month in the window appears even with no expenses (`total: 0`). The line must stay contiguous — a spend-free month is a legitimate zero, not a skipped point (mock's leading comment states this). Do not derive the month list from the data.
- **Current month is partial (flag):** the last point covers a month still in progress, so its `total` is a running partial and will read lower than a completed month. This is **intended** — the endpoint returns raw month+total and does not annotate partialness. The frontend derives **average, peak, and month-over-month deltas client-side**, so the endpoint returns nothing but `{ month, total }` per point — no averages, no peak flag, no deltas, no `change_pct`. Keep the payload minimal.

**Aggregation approach (recommended)**

One grouped query bounded to the window, then zero-fill:
- `SELECT strftime('%Y-%m', date) AS ym, SUM(amount) AS total FROM expenses WHERE date >= <first-of-window-month> GROUP BY ym` into a keyed map.
- Loop the generated month list (oldest → newest) and emit `{ month, total: $map[$ym] ?? 0 }`.

Generate the month window from the clock, never from the query result rows — that is what guarantees the contiguous, zero-filled line the chart requires.

---

### Cross-cutting notes

- **Envelope:** both wrap in `{ "data": ... }`. Monthly `data` is an object; trend `data` is an array. No `meta` on either.
- **Errors:** these are GETs with no body; the only failure mode is a bad `month`/`months` query. If you choose to validate (see above), use the exact 422 shape `{ "message": ..., "errors": { field: [msg] } }`; any other error surfaces the standard `{ "message": ... }`. Given the mock validates neither, the drop-in-faithful choice is to **not** 422 and instead resolve bad input to defaults / empty results.
- **Currency:** PHP only; all money is centavos integers end to end. No formatting, no float pesos in the payload.
- **Timezone:** "current month" (monthly default) and "current month" (trend anchor) must use the same app timezone consistently; pick the app's configured tz and use it for both `now()` calls.

## Build order

1. **Foundation** — enable API routing, add `config/cors.php`, the API Resource convention, migrations, models. Nothing else works until this lands.
2. **Seeder** — reproduce `mockDb.ts` exactly (8 categories with colours/emoji, the generated expenses across the seeded months, the 6 recurring templates). `php artisan migrate --seed`.
3. **Resources** — Categories → Expenses → Recurring → Stats. Each is independent once Foundation exists; they can be built in parallel.
4. **Materialization** — the scheduled job that turns active recurring templates into real expenses. Lowest priority; the app is fully usable without it (it only affects future auto-created rows).
5. **The swap** — below. Do it last, only once every endpoint is verified against the app.

## The swap (frontend → Laravel) & verification

Once the endpoints are live and seeded:

1. `docker-compose.yml` → set `NUXT_PUBLIC_API_BASE: "http://localhost:8000"` on the `web` service. Browser-side calls hit the host-published port, so `localhost`, not the compose service name. Needs `docker compose up -d web` to reload (a `restart` won't pick up the env change).
2. Delete `web/server/api/` and `web/server/utils/mockDb.ts` — the throwaway mocks. (`web/shared/types/` stays — the frontend still uses those.)
3. Laravel side: `php artisan migrate --seed`, then `docker compose up -d api` to pick up the new `routes/api.php` + `config/cors.php`.
4. **Verify before believing.** Curl each endpoint and diff the JSON against the contract shape (envelope, centavos, nested category, 422/409/404/204). Then load the app and click through every screen — the dashboard (one stats call), the expense roll (filters + tear-to-load pagination), add/edit/void, categories CRUD + the 409, recurring CRUD + the toggle, and the trend line. A month with no data must still render (zero-fill), and a note must be clearable (the frontend sends `note: null`).

## Verification checklist per resource

- **Envelope**: every response is `{ data }`; lists add `meta` with exactly `current_page, per_page, total, last_page` (total is a row count).
- **Money**: every amount is an integer number of centavos, in and out.
- **Category shape**: reads embed the 4-field ref (id, name, colour, emoji) — never the full category with `created_at`.
- **Errors**: 422 is `{ message, errors: { field: [msg] } }`; 404/409 carry `message` alone. Status codes: 201 create, 204 delete, 409 category-in-use, 404 missing.

---

## Architecture & best practices (post-build refactor)

The four resources are built and contract-verified. This section is the agreed refactor: tighten the code to **right-sized, idiomatic Laravel** and add the tooling and tests a real project wants — without over-building.

**Guiding principle.** The app is single-user, local, and the backend is deliberately swappable **at the HTTP boundary** (`docs/api-contract.md` is the seam). So the value is a clean, well-tested implementation of that contract — not speculative internal layering. Don't fight Eloquent, don't add indirection that buys nothing at this size. Every change below either removes duplication, makes a unit explicit, or locks the contract with a test.

### Keep (already correct)

Thin controllers · FormRequests for validation · API Resources for the `{ data }` shape · `Route::bind` 404s that fire before validation · attribute-style models with casts. None of this changes.

### Refactors (right-sized)

1. **Query scopes for the month filter.** The date-prefix filter (`where('date','like',"$month%")`) is duplicated in `ExpenseController@index` and four times in `StatsController`. Lift it to `Expense::scopeInMonth($q, $month)` (and a `scopeForCategory`), so the SQL trap lives in one place. Stats aggregates move from `DB::table('expenses')` to the Eloquent builder + scope for consistency (aggregates alias the raw `date`/`ym` columns to sidestep the date cast).
2. **A `StatsService`.** Lift the two aggregations out of the controller into `app/Services/StatsService.php` (`monthly(string $month): array`, `trend(int $months): array`). The controller just resolves query params and calls it. This is the one place the logic is dense enough to earn its own class — and it becomes unit-testable in isolation.
3. **Materialization as an Action + command (step 4, next).** The scheduled job lands as `app/Actions/MaterializeRecurring.php` invoked by an `artisan recurring:materialize` command registered `->daily()` — same right-sized pattern, with its own tests.
4. **Leave the note/active merge logic in the controllers.** It's request-shaped (absent-vs-null), reads clearly where it is, and does not earn an action class.

### Money value object

Make **centavos** a type, not a bare int that could silently become a float. `app/Support/Money.php` — a small immutable value object (`Money::fromCentavos(int)`, `->centavos(): int`) — bound to the `amount` attribute on `Expense` and `Recurring` via a custom `CastsAttributes`. Boundary rules: the amount validation closure still runs on the **raw request int** (pre-cast, so `is_int` strictness is unchanged); Resources serialize `->centavos()` so the wire stays a **bare integer**; the Stats aggregates read raw `SUM(amount)` and stay ints. This is the one add-on that leans "demonstration over necessity" — kept minimal so it clarifies intent without spreading through the codebase.

### Config

`config/pesonal.php` holds the tunables now hard-coded as controller constants: `pagination.default_per_page` (20), `pagination.max_per_page` (100), `trend.default_months` (6), `trend.max_months` (24). Controllers read `config('pesonal.…')`.

### Tooling

- **Pint** (already a dev dependency) — run `./vendor/bin/pint` to normalise to Laravel's PSR-12 style; commit the reformat as one reviewable step so it never muddies a feature diff.
- **Larastan** (add `larastan/larastan` dev) + a `phpstan.neon` starting at **level 6**, scanning `app/`. Fix what it flags (mostly missing generics/return types). Run `./vendor/bin/phpstan analyse`.

### Feature test suite (contract-locking)

The highest-value item, and CI-ready as-is: `phpunit.xml` already points the `testing` env at an **in-memory SQLite** DB, so tests are hermetic and need no services.

- **Framework:** the installed **PHPUnit** (no new dependency; `php artisan test` works today). Pest is a nicer syntax but an added dependency — skipped to stay right-sized; easy to swap later.
- **Factories:** add `CategoryFactory`, `ExpenseFactory`, `RecurringFactory` so each test builds exactly the rows it needs (deterministic, no reliance on the seed corpus).
- **Tests** (`tests/Feature/`), one file per resource, each mirroring the curl matrices already run by hand:
  - `CategoryTest` — envelope, 5-key shape, 422 messages + unique, 404-before-validation, 409-with-count, 204.
  - `ExpenseTest` — list `meta`/filters/pagination edges, strict centavos, nested ref, the note absent/null/string PATCH, 404-first.
  - `RecurringTest` — no-meta list, cross-field on merged values, `active` null-vs-false, and the **DELETE invariant** (materialized expenses keep `recurring_id`).
  - `StatsTest` — monthly totals + `change_pct` null baseline + zero-filled daily; trend window + clamps.
  - (`MaterializationTest` arrives with step 4.)
- **Run:** `docker compose exec api php artisan test`. Each test uses `RefreshDatabase`.
- **CI (optional final step):** a `.github/workflows/tests.yml` running `composer install`, `php artisan test`, `pint --test`, and `phpstan analyse` on push/PR — a concrete starting point for the CI/CD you're learning.

### Deliberately NOT doing (and why)

- **Repository pattern over Eloquent** — Eloquent *is* the data-access layer; a repo wrapper adds indirection and buys nothing when the swap seam is the HTTP contract, not the ORM.
- **DTOs for every payload** — `FormRequest::validated()` on the way in and API Resources on the way out already pin the shapes; a DTO layer between them is ceremony here.
- **A service/action class per CRUD action** — the controllers are already thin; only Stats and materialization are dense enough to extract.
- **DDD / hexagonal layering, API versioning (`/v1`)** — one user, one client, frozen contract. YAGNI; revisit only if a second consumer or a breaking change ever appears.

### Sequence (each a small, reviewable, committable step)

Tests **first** so every refactor after them is done under a green safety net — the whole point of having them.

1. **Feature test suite** (factories + the four test files) — green against the current code.
2. **Pint + Larastan** — reformat + static analysis baseline, tests stay green.
3. **Query scopes + `StatsService`** — tests stay green.
4. **Money value object + cast** — tests stay green.
5. **`config/pesonal.php`** — tests stay green.
6. **Materialization** (roadmap step 4) — Action + command + scheduler, with `MaterializationTest`.
7. **CI workflow** (optional) — wire the above into GitHub Actions.

Then the swap (roadmap step 5, already staged on `feat/point-at-laravel`).
- **CORS**: the browser at `:3000` can actually reach `:8000` (check the Network tab, not just curl — curl skips the preflight).