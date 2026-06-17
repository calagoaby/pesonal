# Pesonal roadmap

Status: `[x]` done · `[~]` in progress · `[ ]` todo. See `CLAUDE.md` for conventions and the build loop.

## Frontend setup (once)

1. `[ ]` **Install libs** — in `web/`:
   ```sh
   npm install primevue @primevue/nuxt-module @primeuix/themes primeicons chart.js
   ```
2. `[ ]` **Wire PrimeVue** — set `web/nuxt.config.ts` to:
   ```ts
   // https://nuxt.com/docs/api/configuration/nuxt-config
   import { PesonalPreset } from './app/theme/pesonal-preset'

   export default defineNuxtConfig({
     compatibilityDate: '2025-07-15',
     devtools: { enabled: true },
     modules: ['@primevue/nuxt-module'],
     css: ['primeicons/primeicons.css'],
     primevue: {
       options: {
         theme: {
           preset: PesonalPreset,
           options: { darkModeSelector: false, cssLayer: false }
         }
       }
     }
   })
   ```
   The preset already exists at `web/app/theme/pesonal-preset.ts` (brand blue baked into `primary`).
3. `[ ]` **Smoke test** — drop `<Button label="Hi" />` in `app/app.vue`, `docker compose up`, confirm it renders in brand blue.

No Pinia — shared state uses provide/inject context (see `CLAUDE.md`).

## Screens (build order)

For each: Claude posts the spec → you implement in `web/` → Claude PRs a reference to compare. Backend is likely scaffold-only, so **mock the responses first** (shaped like `docs/api-contract.md`) and swap in the real endpoint later.

1. **Dashboard**
   - `[x]` Design spec — `design/dashboard.html`
   - `[ ]` Implement (you)
   - `[ ]` Reference PR (Claude)
   - Data: `GET /api/stats/monthly?month=YYYY-MM` → `total`, `previous_total`, `change_pct`, `by_category[]`, `daily[]`.
2. **Add expense** (the FAB target)
   - `[ ]` Design spec (next up) · `[ ]` Implement · `[ ]` PR
   - Form → `POST /api/expenses` `{ amount (centavos), date, category_id, note? }`.
3. **Expense list**
   - `[ ]` month + category filter, pagination. `GET /api/expenses`.
4. **Categories**
   - `[ ]` CRUD, color + emoji picker. Delete blocked → 409 surfaced as a friendly message.
5. **Recurring**
   - `[ ]` CRUD, monthly (`day_of_month`), `active` toggle. `GET/POST /api/recurring`.
6. **Trend chart**
   - `[ ]` 6-month line on the dashboard. `GET /api/stats/trend?months=6`.
7. `[ ]` **Swap mocks → real API** once backend endpoints land.

## Backend track (separate sessions)

- `[ ]` Implement contract endpoints: categories, expenses (+ filters/pagination), stats (monthly + trend), recurring.
- `[ ]` Recurring materialization (scheduled; stamp `recurring_id`; forward-only per the contract's open question).
- `[ ]` Seed default categories (each with `color` + `emoji`).
- `[ ]` Formalize the contract into OpenAPI once endpoints stabilize.

## Parking lot (post-v1)

Budgets · CSV import · search · export · weekly/yearly recurring frequencies.