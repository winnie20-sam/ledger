# Ledger API

Laravel 12 backend for the Ledger React app. JWT auth, MySQL (`ledger` database).

## Run it

```bash
php artisan serve --port=8010
```

Base URL: `http://localhost:8010/api`
Demo login: `demo@ledger.test` / `password123` (about 6 months of KES transactions and budgets)

Reset the demo data at any time: `php artisan db:seed`

To practise loading states, set `API_DELAY_MS=1500` in `.env` (then `php artisan config:clear`).

## Response format

Every response looks like this:

```json
{ "status": { "code": 1, "message": "Success" }, "data": ... }
```

| `status.code` | Meaning | HTTP |
|---|---|---|
| 1 | Success | 200 |
| 2 | Failed (`message` says why) | 200 |
| 3 | Validation failed. `data` holds errors by field: `{ "amount": ["..."] }` | 200 |
| 4 | Record not found | 200 (404 for an unknown URL) |
| 5 | Record already exists | 200 |
| 7 | Not logged in, or the token expired | **401** |

> ⚠️ Most failures still come back as **HTTP 200**, so `fetch` won't treat them as errors. Your API client has to check `status.code`.

Send `Authorization: Bearer <access_token>` on every request except register, login and refresh. Tokens last 60 minutes.

## Endpoints

### Users
| Method | URL | Body | Returns |
|---|---|---|---|
| POST | `/users/register` | `name, email, password, password_confirmation` | same as login |
| POST | `/users/login` | `email, password` | `{ access_token, token_type, expires_in, user: {id, name, email} }` |
| POST | `/users/refresh` | — (send the current token) | same as login, with a new token. The old token stops working. |
| GET | `/users/me` | — | `{ id, name, email }` |
| POST | `/users/logout` | — | — |

### Categories
| Method | URL | Body | Notes |
|---|---|---|---|
| GET | `/categories/fetch?type=expense` | — | `type` is optional. Returns all categories, for dropdowns. |
| POST | `/categories/store` | `name, type (income\|expense), color? (#rrggbb)` | |
| PUT | `/categories/edit` | `id, name, type, color?` | `type` can't change once a transaction uses the category |
| PUT | `/categories/destroy` | `id` | Fails (code 2) if any transaction or budget uses it |

### Transactions
| Method | URL | Body |
|---|---|---|
| POST | `/transactions/search` | All optional: `page, page_size (max 100), search, type, category_id, date_from, date_to (YYYY-MM-DD), amount_min, amount_max, sort_by (date\|amount\|description\|created_at), sort_dir (asc\|desc)` |
| POST | `/transactions/get-details` | `id` |
| POST | `/transactions/store` | `description, amount, type, date, notes?` plus **either** `category_id` **or** `splits` |
| PUT | `/transactions/edit` | `id` plus the same fields as store (send the whole record) |
| PUT | `/transactions/destroy` | `id`, or `ids: [1, 2, 3]` to delete several |
| POST | `/transactions/import` | `transactions: [{ description, amount, type, date, category_id }]`, up to 500 rows. Saves all rows or none. |

`search` returns a page:

```json
{ "current_page": 1, "page_size": 10, "last_page": 25, "total": 247, "items": [ ...transactions ] }
```

A transaction:

```json
{
  "id": 12, "description": "Naivas", "amount": 1840, "type": "expense", "date": "2026-10-02",
  "notes": null, "category_id": 5,
  "category": { "id": 5, "name": "Food & Groceries", "type": "expense", "color": "#ea580c" },
  "splits": []
}
```

**Split transaction:** `category_id` and `category` are `null`, and `splits` lists the parts:

```json
"splits": [
  { "id": 1, "category_id": 5, "amount": 7200, "category": { "name": "Food & Groceries", ... } },
  { "id": 2, "category_id": 11, "amount": 2300, "category": { "name": "Household", ... } }
]
```

Split rules: 2–10 parts, the parts must add up to `amount`, and every category must match the transaction's `type`.
Errors come back keyed like `splits.1.category_id`. Import errors come back as `transactions.3.amount`, so you can show each error next to its row.

### Budgets
| Method | URL | Body | Returns |
|---|---|---|---|
| POST | `/budgets/search` | `month? (YYYY-MM, default this month)` | `{ month, items: [{ id, category_id, month, amount, category, spent, remaining, percent_used }] }` |
| POST | `/budgets/store` | `category_id (expense only), month, amount` | code 5 if that category already has a budget for that month |
| PUT | `/budgets/edit` | `id, amount` | |
| PUT | `/budgets/destroy` | `id` | |

### Dashboard
| Method | URL | Body |
|---|---|---|
| POST | `/dashboard/summary` | `date_from?, date_to?` (default: this month), `months?` (trend length, default 6) |

Returns:

```json
{
  "totals": { "income": 164660, "expense": 122709, "balance": 41951, "transaction_count": 47 },
  "by_category": [{ "category_id", "name", "type", "color", "total", "percent" }],
  "monthly_trend": [{ "month": "2026-05", "income", "expense", "balance" }],
  "budgets": [ ...same shape as /budgets/search items, for date_to's month ]
}
```

## Sample CSV

`docs/sample-bank-statement.csv` is a bank-style export (`Date` as DD/MM/YYYY, separate `Money In` / `Money Out` columns), for practising the CSV import wizard.
