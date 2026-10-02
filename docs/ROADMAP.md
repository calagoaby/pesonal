# Pesonal roadmap

Status: `[x]` done · `[~]` in progress · `[ ]` todo. See `CLAUDE.md` for conventions, the build loop, and the environment gotchas.

## Where things stand

**The app runs end to end on Laravel.** Every screen fetches over HTTP through the data layer, and since step 17 every request goes to the API in `api/`. The mock routes that stood in for it in `web/server/` are deleted; they're in git history, last present at `2d7871c`.

**The swap took two base URLs, not one.** This section used to say one config value would do. That was wrong: a server-side render runs inside the `web` container, where `localhost` is the container itself, so it reaches Laravel at `http://api:8000` (`NUXT_API_BASE`, the compose service name). The browser can't resolve service names and uses `http://localhost:8000` (`public.apiBase`). `utils/api.ts` picks one with `import.meta.server`.

## Frontend setup — done

`primevue` · `@primevue/nuxt-module` · `@primeuix/themes` · `chart.js` · `@primeicons/vue`, wired in `web/nuxt.config.ts` with the `PesonalPreset` and `~/assets/css/main.css`. Icons are **SVG components** (`import Home from '@primeicons/vue/home'`), not the `primeicons` CSS font — that dependency was removed. `typescript` + `vue-tsc` are devDependencies for `npm run typecheck`.

## Build steps

Steps 1–3 stood up the data layer; 4–7 finished Add expense; 8–10 are cross-cutting; 11–16 are the remaining screens.

1. `[x]` **API transport + mock server + expenses list** — `utils/api.ts`, `repositories/expenses.ts`, `server/api/expenses.get.ts`, deterministic seed in `server/utils/mockDb.ts`.
2. `[x]` **Dashboard on one stats call** — `server/api/stats/monthly.get.ts`; four dashboard composables collapsed into `useMonthlyStats` behind a context.
3. `[x]` **Categories endpoint** — `server/api/categories.get.ts`, `useCategories`.
4. `[x]` **Category bottom sheet** — `SharedCategorySheet`, shared by the add form (required pick) and the expenses filter (`all-label` adds the escape row).
5. `[x]` **Amount keypad** — `SharedKeypad` on a new `SharedSheet` shell; cents-first entry, plus keyboard input.
6. `[x]` **Date picker** — `SharedDatePicker`; date defaults to today in the configured zone.
7. `[x]` **Create expense** — `POST`, validation, `SharedToast`, cache invalidation. Add expense is complete.
8. `[x]` **Screen states** — `SharedNotice` (empty + error) and `SharedSkeleton`, applied to the roll and the dashboard.
9. `[x]` **Tear-to-load** — append pages onto the roll, reset on filter/month change.
10. `[x]` **Month picker** — `SharedMonthPicker`; the month label becomes tappable. Prev/next chevrons already work on both screens.
11. `[x]` **Edit / void expense** — `PATCH` + `DELETE`, reached by tapping a row. Extract the shared expense form from `AddExpense.vue` here. Adds `SharedVoidSlip`, and the toast gains Undo (which needs `pointer-events: auto` on `.shown`).
12. `[x]` **Categories screen** — CRUD, colour + emoji pickers, 409 surfaced through the void slip.
13. `[x]` **Recurring list** — grouped by `day_of_month`, paused rows struck, monthly commitment total.
14. `[x]` **Recurring form** — CRUD, `active` toggle, day-of-month clamp warning.
15. `[x]` **More hub** — links Categories and Trend.
16. `[x]` **Trend** — `GET /api/stats/trend`; dashboard sparkline card plus the drill-down screen.
17. `[x]` **Point at Laravel** — a base URL per side (server render vs browser), `web/server/` deleted.

## Decisions that aren't obvious from the code

Worth knowing before changing any of it:

- **`meta.total` is a row count, not a sum.** The expenses list can't show a peso total — you only ever hold one page. Money totals come from `stats/monthly`.
- **Day grouping runs over the accumulated list, not per page.** A day straddling a page boundary must print one header, not two. This is why tear-to-load was cheap.
- **A filter defines the result set; pagination only walks it.** Changing month or category resets to page 1 and discards appended pages.
- **Calendar dates never go through a timezone.** `new Date('2026-06-17')` is UTC midnight and lands on the 16th anywhere behind UTC. `utils/date.ts` builds dates from parts; `todayIn(timezone)` pins "today" so SSR and the client agree.
- **The keypad is cents-first.** Digits shift in from the right, so the value is always a whole number of centavos and can never be a half-typed decimal. That's why there's no decimal key.
- **Context vs `v-model` vs Pinia.** A sheet talking to its own parent is `v-model` (steps 4–6). Two components in a tree needing the same state is a context (`useMonthlyStats`, the shared expense form via `ExpenseFormContext`). State that outlives a screen goes to Pinia — the toast store is the first: a void on the edit screen shows its undo back on the roll, so it can't live in either tree.
- **Writes are imperative, reads are declarative.** Reads go through `useAsyncData`; a save is a repository call from a handler, then `clearNuxtData([...])` to invalidate other screens' caches. Don't `await refreshNuxtData` for keys owned by screens that aren't mounted — it never settles.

## Design specs — complete

Every screen and state is specified. The mockups cross-link, so `design/dashboard.html` opens in a browser as a clickable prototype.

| File | Covers |
|---|---|
| `dashboard.html` | Monthly overview · `GET /api/stats/monthly` |
| `expenses.html` | Expense roll, tear-to-load, category sheet · `GET /api/expenses` |
| `add-expense.html` | Create · `POST /api/expenses` |
| `edit-expense.html` | Update + void · `PATCH`/`DELETE /api/expenses/{id}` |
| `categories.html` | Full CRUD + colour/emoji pickers · `/api/categories` |
| `recurring.html` | Standing orders list · `GET /api/recurring` |
| `recurring-form.html` | Create/update/delete · `/api/recurring` |
| `trend.html` | Six-month line · `GET /api/stats/trend` |
| `more.html` | Hub — Categories, Trend, About |
| `states.html` | Loading · empty · filtered-empty · error |
| `components.html` | Void slip · 409 refusal · toast · month/date pickers · amount keypad |

Navigation model: primary screens (dashboard / expenses / recurring / more) carry the bottom nav · drill-downs (categories / trend) get a back bar and no nav · forms are modals with a close X and a commit bar. **Tapping a row always opens its edit form, and delete lives inside that form** — no swipe-to-reveal, no per-row overflow menu.

The specs are a guide, not scripture — where one has been wrong, the code won and the spec was corrected (the weekday labels, the keypad's decimal key). Keep doing that rather than matching a mockup you can prove wrong.

## Shared components

Built: `SharedNavBar` · `SharedReceipt` (torn top/bottom + header) · `SharedPerf` · `SharedBarcode` · `SharedSheet` (scrim + panel + titled header, closes on Escape) · `SharedCategorySheet` · `SharedKeypad` · `SharedDatePicker` (optional clear chip, e.g. "No end") · `SharedMonthPicker` · `SharedToast` (presentational) + `SharedToaster` (store-driven, in both layouts) · `SharedVoidSlip` (confirm + blocked/409 variants, overridable warning line) · `SharedNotice` · `SharedSkeleton` · `DashboardCard`.

## Backend track (separate sessions)

- `[x]` Implement contract endpoints: categories, expenses (+ filters/pagination), stats (monthly + trend), recurring. A PHPUnit feature suite locks each one: `docker compose exec api php artisan test`.
- `[~]` Recurring materialization — `recurring:materialize` is built, tested and registered `->daily()`, but **nothing runs Laravel's scheduler yet** (no `schedule:work` process in `docker-compose.yml`), so it never fires on its own.
- `[x]` Seed default categories (each with `color` + `emoji`). The seeder reproduces the old mock corpus.
- `[ ]` Formalize the contract into OpenAPI once endpoints stabilize.

The Laravel API was built against the mock routes' behaviour; they're in git history at `2d7871c` if a shape is ever in doubt.

## Parking lot (post-v1)

Budgets · CSV import/export (surfaced in More as `SOON`) · search · weekly/yearly recurring frequencies · an About screen.