# Ledger

A personal finance and budgeting app: record income and expenses, split a transaction across categories, set monthly budgets, and see where the money goes.

Built as a learning project to practise professional React, coming from Angular.

| Folder | What it is | Stack |
|---|---|---|
| [`frontend/`](frontend) | The web app | React 19, TypeScript, Vite, React Router, Sass (CSS Modules) |
| [`backend/`](backend) | The REST API | Laravel 12, PHP 8.2+, MySQL, JWT auth |

## Run it locally

**Backend** (http://localhost:8010/api):

```bash
cd backend
composer install
cp .env.example .env          # then set DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed    # creates the demo account and ~6 months of sample data
php artisan serve --port=8010
```

**Frontend** (http://localhost:5173):

```bash
cd frontend
npm install --legacy-peer-deps
npm run dev
```

Demo login: `demo@ledger.test` / `password123`

The API's endpoints and response format are documented in [`backend/README.md`](backend/README.md).
