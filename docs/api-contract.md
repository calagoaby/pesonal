# Pesonal API Contract — v1 (draft)

The backend-agnostic REST contract between the Nuxt frontend and the API.
The frontend codes against **this document**, never against Laravel specifics, so
the backend stays a drop-in swap. Any backend that implements this contract is valid.

Status: **draft** — design surface for v1. Will be formalized into OpenAPI once endpoints stabilize.

---

## Conventions

| Topic | Rule |
|---|---|
| Base path | All endpoints under `/api`. |
| Money | Integer **centavos**. `15000` = ₱150.00. Frontend formats for display. |
| Currency | Fixed **PHP**. No per-row currency field in v1. |
| JSON keys | `snake_case`. |
| IDs | Integer, server-assigned. |
| Expense date | Calendar date, `YYYY-MM-DD` (no time component). |
| Timestamps | Full ISO 8601 UTC, e.g. `2026-06-17T10:03:00Z` (`created_at`, `updated_at`). |
| Envelope | Single resource and lists both wrap in `{ "data": ... }`. Lists add `meta`. |
| Content type | `application/json` for requests and responses. |
| Methods | `GET` read, `POST` create, `PATCH` partial update, `DELETE` remove. |

### Error shape

All 4xx/5xx responses use this shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount field is required."]
  }
}
```

`errors` is present only for validation failures (422). Other errors carry `message` alone.

### Status codes

| Code | Meaning |
|---|---|
| 200 | OK (read / update) |
| 201 | Created |
| 204 | No content (delete) |
| 404 | Resource not found |
| 409 | Conflict (e.g. deleting a category that still has expenses) |
| 422 | Validation failed |

---

## Resources

### Category

```json
{
  "id": 3,
  "name": "Food",
  "color": "#2D8CFF",
  "emoji": "🍔",
  "created_at": "2026-06-17T10:03:00Z"
}
```

| Field | Type | Notes |
|---|---|---|
| `id` | int | server-assigned |
| `name` | string | required, unique |
| `color` | string | required, hex `#RRGGBB` |
| `emoji` | string | required, single emoji |
| `created_at` | timestamp | |

### Expense

Asymmetric: **writes** send `category_id`; **reads** return the nested `category` object
so list / donut / tap-through views have color + emoji without a second lookup.

```json
{
  "id": 1,
  "amount": 15000,
  "date": "2026-06-17",
  "note": "lunch with team",
  "category": {
    "id": 3, "name": "Food", "color": "#2D8CFF", "emoji": "🍔"
  },
  "recurring_id": null,
  "created_at": "2026-06-17T10:03:00Z",
  "updated_at": "2026-06-17T10:03:00Z"
}
```

| Field | Type | Notes |
|---|---|---|
| `id` | int | server-assigned |
| `amount` | int | required, centavos, > 0 |
| `date` | date | required, `YYYY-MM-DD` |
| `note` | string \| null | optional |
| `category` | object | read-only; on write send `category_id` |
| `recurring_id` | int \| null | set when this row was materialized from a recurring template |
| `created_at` / `updated_at` | timestamp | |

### RecurringExpense

A template the backend auto-materializes into real `Expense` rows. v1 recurs **monthly**.

```json
{
  "id": 1,
  "amount": 1500000,
  "note": "Apartment rent",
  "day_of_month": 1,
  "start_date": "2026-01-01",
  "end_date": null,
  "active": true,
  "category": {
    "id": 5, "name": "Housing", "color": "#7C3AED", "emoji": "🏠"
  },
  "created_at": "2026-06-17T10:03:00Z",
  "updated_at": "2026-06-17T10:03:00Z"
}
```

| Field | Type | Notes |
|---|---|---|
| `id` | int | server-assigned |
| `amount` | int | required, centavos, > 0 |
| `note` | string \| null | optional |
| `day_of_month` | int | required, 1–31 (clamped to month length) |
| `start_date` | date | required |
| `end_date` | date \| null | null = open-ended |
| `active` | bool | pause without deleting |
| `category` | object | read-only; on write send `category_id` |

**Materialization** is a backend behavior, not an HTTP call: on a schedule the backend
creates `Expense` rows from active templates whose `day_of_month` has arrived, stamping
`recurring_id`. Those expenses then appear in the normal expense list and all stats.

---

## Endpoints

### Categories

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/categories` | List all (unpaginated — small set). |
| POST | `/api/categories` | Create. Body: `name`, `color`, `emoji`. → 201 |
| GET | `/api/categories/{id}` | Single. |
| PATCH | `/api/categories/{id}` | Update any of `name`, `color`, `emoji`. |
| DELETE | `/api/categories/{id}` | Delete. **409** if any expense references it. |

### Expenses

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/expenses` | List, paginated, filterable (see below). |
| POST | `/api/expenses` | Create. Body: `amount`, `date`, `category_id`, `note?`. → 201 |
| GET | `/api/expenses/{id}` | Single. |
| PATCH | `/api/expenses/{id}` | Update any of `amount`, `date`, `category_id`, `note`. |
| DELETE | `/api/expenses/{id}` | Delete. → 204 |

**`GET /api/expenses` query params** (all optional):

| Param | Example | Effect |
|---|---|---|
| `month` | `2026-06` | Limit to that calendar month. |
| `category_id` | `3` | Limit to one category. |
| `page` | `2` | Page number (default 1). |
| `per_page` | `20` | Page size (default 20, max 100). |

Sorted by `date` desc, then `id` desc. Paginated response:

```json
{
  "data": [ /* Expense objects */ ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 137,
    "last_page": 7
  }
}
```

### Stats (dashboard)

#### `GET /api/stats/monthly?month=YYYY-MM`

Everything the dashboard's single-month view needs in one request. `daily` is zero-filled
for every day of the month so bars render contiguously.

```json
{
  "data": {
    "month": "2026-06",
    "total": 1234500,
    "previous_total": 1100000,
    "change_pct": 12.2,
    "by_category": [
      {
        "category": { "id": 3, "name": "Food", "color": "#2D8CFF", "emoji": "🍔" },
        "total": 450000,
        "count": 12
      }
    ],
    "daily": [
      { "date": "2026-06-01", "total": 0 },
      { "date": "2026-06-02", "total": 32000 }
    ]
  }
}
```

`change_pct` is `null` when `previous_total` is 0 (no baseline to compare).

#### `GET /api/stats/trend?months=N`

Multi-month totals for the trend chart, oldest → newest, ending with the current month.
`months` defaults to 6.

```json
{
  "data": [
    { "month": "2026-01", "total": 980000 },
    { "month": "2026-02", "total": 1120000 }
  ]
}
```

### Recurring

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/recurring` | List all templates. |
| POST | `/api/recurring` | Create. Body: `amount`, `category_id`, `day_of_month`, `start_date`, `note?`, `end_date?`, `active?`. → 201 |
| GET | `/api/recurring/{id}` | Single. |
| PATCH | `/api/recurring/{id}` | Update any field incl. `active`. |
| DELETE | `/api/recurring/{id}` | Delete template. Already-materialized expenses are untouched. |

---

## Open questions / parking lot

- **Backfill on create**: when a recurring template's `start_date` is in the past, does the
  backend materialize the missed months immediately, or only go forward? (Leaning: forward-only in v1.)
- **Frequency**: v1 is monthly-only. Weekly/yearly would add a `frequency` field later.
- **Budgets, CSV import, search, export**: out of scope for v1 (parking lot).