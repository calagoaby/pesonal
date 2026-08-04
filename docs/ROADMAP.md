# Pesonal roadmap

Status: `[x]` done · `[~]` in progress · `[ ]` todo. See `CLAUDE.md` for conventions and the build loop.

## Frontend setup

1. `[x]` **Install libs** — `primevue`, `@primevue/nuxt-module`, `@primeuix/themes`, `chart.js`, `@primeicons/vue`.
2. `[x]` **Wire PrimeVue** — `web/nuxt.config.ts` loads the `PesonalPreset` (stamp red baked into `primary`) with `darkModeSelector: false`, plus `~/assets/css/main.css` for the design tokens and the Google Fonts links.
3. `[x]` **Smoke test** — renders in stamp red.

Icons come from `@primeicons/vue` as SVG components (`import Home from '@primeicons/vue/home'`), **not** the `primeicons` CSS font — that dependency was removed. Node runs in WSL via nvm; the whole stack runs on Docker (`docker compose up`), web on `:3000`.

Shared state defaults to provide/inject context; Pinia is the escape hatch once state gets large, complex, or genuinely global (see `CLAUDE.md`).

## Build loop

Claude posts a **design spec** in `design/` → you implement it in `web/` → you open a PR → **Claude reviews it in chat** (what to change and why; no paste-ready code, no reference implementations) → you revise and merge. You run all git/PR mechanics yourself.

## Design specs — complete

Every screen and state is specified. The mockups cross-link, so `design/dashboard.html` opens in a browser as a clickable prototype.

| File | Covers |
|---|---|
| `dashboard.html` | Monthly overview · `GET /api/stats/monthly` |
| `expenses.html` | Expense roll, tear-to-load, category sheet · `GET /api/expenses` |
| `add-expense.html` | Create · `POST /api/expenses` |
| `edit-expense.html` | Update + void · `PATCH`/`DELETE /api/expenses/{id}` |
| `categories.html` | Full CRUD + color/emoji pickers · `/api/categories` |
| `recurring.html` | Standing orders list · `GET /api/recurring` |
| `recurring-form.html` | Create/update/delete · `/api/recurring` |
| `trend.html` | Six-month line · `GET /api/stats/trend` |
| `more.html` | Hub — Categories, Trend, About |
| `states.html` | Loading · empty · filtered-empty · error |
| `components.html` | Void slip · 409 refusal · toast · month/date pickers · amount keypad |

Navigation model: primary screens (dashboard / expenses / recurring / more) carry the bottom nav · drill-downs (categories / trend) get a back bar and no nav · forms are modals with a close X and a commit bar. **Tapping a row always opens its edit form, and delete lives inside that form** — no swipe-to-reveal, no per-row overflow menu.

## Screens (build order)

Backend is scaffold-only, so **mock the responses first** (shaped like `docs/api-contract.md`) and swap in the real endpoints later.

1. `[x]` **Dashboard** — header, month switcher, total receipt, category donut, daily bars, recent items.
2. `[x]` **App shell** — `layouts/default.vue`: full-height column, scrollable content region, bottom nav with route-aware active state, FAB.
3. `[~]` **Expense list** — day-grouped roll, category sheet, tear-to-load. *PR open, in review.*
4. `[ ]` **Add expense** — `POST /api/expenses`. The FAB currently goes nowhere; this is next.
5. `[ ]` **Edit / void expense** — `PATCH` + `DELETE /api/expenses/{id}`, reached by tapping a row.
6. `[ ]` **Categories** — CRUD with color + emoji pickers. Delete blocked → 409 surfaced in the void slip.
7. `[ ]` **Recurring list** — grouped by `day_of_month`, paused rows struck, monthly commitment total.
8. `[ ]` **Recurring form** — CRUD incl. `active` toggle and the day-of-month clamp warning.
9. `[ ]` **More** — hub linking Categories and Trend.
10. `[ ]` **States** — loading skeletons, empties, error + retry across every list.
11. `[ ]` **Trend** — 6-month line, on the dashboard as a card and as its own drill-down.
12. `[ ]` **Swap mocks → real API** — one `app/utils/api.ts` owns the base URL, the `{ data }` envelope, and the error shape.

## Shared components

Built: `SharedNavBar` · `SharedReceipt` (torn top/bottom + header) · `SharedPerf` · `SharedBarcode` · `DashboardCard`.

To build, all specified in `design/components.html` and `design/states.html`: `SharedVoidSlip` (confirm + 409 variant) · `SharedToast` (with undo) · `SharedEmpty` · `SharedSkeleton` · `SharedMonthPicker` · `SharedDatePicker` · `SharedKeypad`.

## Backend track (separate sessions)

- `[ ]` Implement contract endpoints: categories, expenses (+ filters/pagination), stats (monthly + trend), recurring.
- `[ ]` Recurring materialization (scheduled; stamp `recurring_id`; forward-only per the contract's open question).
- `[ ]` Seed default categories (each with `color` + `emoji`).
- `[ ]` Formalize the contract into OpenAPI once endpoints stabilize.

## Parking lot (post-v1)

Budgets · CSV import/export (surfaced in More as `SOON`) · search · weekly/yearly recurring frequencies · an About screen.