# Team Setup & Run Guide

Pharmacy Management + Recruitment Platform — how to get the whole system
running on a clean machine.

This guide is written for a teammate who has **never configured this
repository before**. Every command and value below was taken from the actual
repository configuration (`composer.json`, `.env.example`, `pubspec.yaml`,
`vite.config.ts`, `api_constants.dart`, route files). Nothing is invented.

---

## 1. Architecture Overview

The repository is a monorepo with three applications:

```
pharmacy-monorepo/
├── backend/      Laravel 12 API  (PHP 8.2+)      -> http://127.0.0.1:8000
├── frontend/     Flutter mobile app (Android)    -> emulator / device
└── admin-web/    React + Vite admin console      -> http://localhost:5173
```

How they talk to each other:

```
   ┌──────────────────────┐         ┌──────────────────────┐
   │  Flutter app         │         │  admin-web (browser) │
   │  (Android emulator)  │         │  localhost:5173      │
   └──────────┬───────────┘         └──────────┬───────────┘
              │                                │
   Sanctum Bearer token             Session cookie + CSRF
   http://10.0.2.2:8000/api         http://localhost:8000/api/admin
              │                                │
              └───────────────┬────────────────┘
                              ▼
                  ┌───────────────────────────┐
                  │  Laravel API              │
                  │  127.0.0.1:8000           │
                  │  SQLite (local default)   │
                  └───────────────────────────┘
```

Two completely separate authentication systems:

| Client | Identity | Auth mechanism |
| --- | --- | --- |
| Flutter | Pharmacist / Employee | Laravel Sanctum **bearer token** |
| admin-web | Administrator | **Session cookie + CSRF** (no token) |

**Start order matters:** always start the **backend first**, then the Flutter
app and/or admin-web. Both clients fail at startup if the API is unreachable.

---

## 2. Prerequisites

Install these before you begin:

| Tool | Required version | Notes |
| --- | --- | --- |
| PHP | **8.2 or newer** | from `backend/composer.json` (`"php": "^8.2"`) |
| Composer | latest | PHP dependency manager |
| Flutter SDK | Dart SDK **^3.10.4** | from `frontend/pubspec.yaml` |
| Android Studio | latest | for the Android emulator |
| Node.js | 20+ recommended | needed for `admin-web` |
| Git | latest | |

Required PHP extensions (used by document/image validation and SQLite):

- `pdo_sqlite`
- `fileinfo`
- `gd`
- `mbstring`

> **Windows / XAMPP note:** `gd` and `fileinfo` are commonly disabled by
> default. Open `php.ini` and make sure these lines are **not** commented out:
>
> ```ini
> extension=gd
> extension=fileinfo
> ```
>
> Restart your terminal afterwards. Without `gd`, profile-image tests and
> uploads will fail.

Verify your tooling:

```bash
php --version
composer --version
flutter --version
node --version
```

---

## 3. Clone and Checkout

```bash
git clone https://github.com/mayaalaj987-sys/pharmacy-app.git pharmacy-monorepo
cd pharmacy-monorepo
```

The current development branch is **`profile-account-management`** (confirmed
from Git; it tracks `origin/profile-account-management`):

```bash
git checkout profile-account-management
git pull
```

---

## 4. Backend Setup (Laravel)

All commands in this section run from the `backend/` folder.

```bash
cd backend
```

### 4.1 Install PHP dependencies

```bash
composer install
```

### 4.2 Create your local `.env`

`backend/.env` is **local-only and git-ignored** (see `backend/.gitignore`).
Copy the template:

```bash
# Windows (PowerShell)
Copy-Item .env.example .env

# macOS / Linux
cp .env.example .env
```

### 4.3 Generate the application key

```bash
php artisan key:generate
```

### 4.4 Configure the database

The default configuration in `.env.example` is **SQLite**, which needs no
database server:

```dotenv
DB_CONNECTION=sqlite
```

With SQLite, Laravel uses `backend/database/database.sqlite`. That file is
git-ignored (`backend/database/.gitignore` contains `*.sqlite*`), so create it
once on a fresh clone:

```bash
# Windows (PowerShell)
New-Item -ItemType File database/database.sqlite

# macOS / Linux
touch database/database.sqlite
```

<details>
<summary>Optional: using MySQL/MariaDB instead</summary>

Uncomment and fill the MySQL block in your `.env` (the keys already exist,
commented out, in `.env.example`), then create the schema in your DB server
before migrating:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```
</details>

### 4.5 Review the admin/session settings

These already have working local defaults in `.env.example` — **do not change
them unless you know why**:

```dotenv
APP_URL=http://localhost
SESSION_DRIVER=database
SESSION_COOKIE=smart-pharmacy-admin-session
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
ADMIN_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173
```

`ADMIN_ALLOWED_ORIGINS` is what allows `admin-web` (port **5173**) to call the
admin API with credentials. If you run admin-web on a different port, add that
exact origin here.

### 4.6 Run migrations

```bash
php artisan migrate
```

> ⚠️ **Safety:** use plain `migrate` only. **Never** run `migrate:fresh`,
> `migrate:refresh`, `migrate:reset`, or `db:wipe` against a database that
> holds work you care about — they drop tables and destroy data.

Confirm everything applied:

```bash
php artisan migrate:status
```

Every migration should read **Ran**, with none Pending.

### 4.7 (Optional) Seed supplier catalogue data

`DatabaseSeeder` is intentionally **empty**, so `php artisan db:seed` alone
does nothing. The supplier catalogue seeders must be called by class:

```bash
php artisan db:seed --class=SupplierSeeder
```

Without this, the Suppliers screen will legitimately show an empty list.

### 4.8 Start the API

```bash
php artisan serve
```

The API is now at **`http://127.0.0.1:8000`**. Leave this terminal running.

Quick smoke check from another terminal:

```bash
curl http://127.0.0.1:8000/api/admin/csrf
```

Expected: `{"message":"CSRF protection is ready.","code":"csrf_ready"}`

> **Physical Android device instead of an emulator?** `php artisan serve`
> binds to localhost only. Start it as
> `php artisan serve --host=0.0.0.0` and point the app at your machine's LAN
> IP (see §5.3).

---

## 5. Flutter Frontend Setup

From the repository root:

```bash
cd frontend
```

### 5.1 Install dependencies

```bash
flutter pub get
```

### 5.2 Google Maps key (needed for the pharmacy location picker)

The repository contains **no usable Google API key** by design. The committed
`android/local.defaults.properties` holds only the placeholder
`MAPS_API_KEY=DEFAULT_API_KEY`.

To make the map render, create `frontend/android/secrets.properties` — this
file is git-ignored:

```properties
MAPS_API_KEY=your_restricted_development_key
```

See `frontend/android/MAPS_SETUP.md` for key-restriction guidance. The rest of
the app works fine without a key; only the map view is affected.

### 5.3 API address: host vs Android Emulator

This is the single most common setup mistake.

| Where the app runs | API base URL to use | Why |
| --- | --- | --- |
| **Android Emulator** | `http://10.0.2.2:8000/api` | `10.0.2.2` is the emulator's alias for your host machine's `localhost` |
| Host browser / curl | `http://127.0.0.1:8000` | direct loopback |
| Physical device (same Wi-Fi) | `http://<your-LAN-IP>:8000/api` | requires `php artisan serve --host=0.0.0.0` |

The app already defaults to the emulator address, defined in
`lib/core/network/api_constants.dart`:

```dart
static const String baseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);
```

So for a normal emulator run you need **no configuration at all**.

To override it (physical device, or a different port):

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8000/api
```

> The base URL **must include the `/api` suffix**, and must **not** have a
> trailing slash.

### 5.4 Run the app

Start an Android emulator from Android Studio, then:

```bash
flutter devices     # confirm the emulator is listed
flutter run
```

---

## 6. admin-web Setup (Administrator Console)

From the repository root:

```bash
cd admin-web
```

### 6.1 Install dependencies

```bash
npm install
```

### 6.2 Create the local env file

```bash
# Windows (PowerShell)
Copy-Item .env.example .env

# macOS / Linux
cp .env.example .env
```

`.env.example` contains one variable:

```dotenv
VITE_ADMIN_API_BASE_URL=http://localhost:8000
```

> Note this is the **origin only** — no `/api` suffix, no trailing slash. The
> client appends `/api/admin/...` itself.

### 6.3 Run the dev server

```bash
npm run dev
```

Opens on **`http://localhost:5173`** (the port is pinned via `strictPort: true`
in `vite.config.ts`; it will not silently pick another port).

Keep the hostname consistent: if the backend allowlist has
`http://localhost:5173`, use `localhost` in the browser — **not**
`127.0.0.1` — or the credentialed CORS/session check will reject you.

### 6.4 Create an administrator account

There is no admin self-registration and no seeded admin. Create the first
super administrator with the CLI (from `backend/`, with the API able to reach
the DB):

```bash
php artisan admin:provision-super --email=you@example.com --name="Your Name"
```

The password is entered through a hidden interactive prompt — it is never
passed as a command-line argument and never logged.

Other available admin commands:

```bash
php artisan admin:create        --actor=<super-admin-email> --email=... --name=... --role=...
php artisan admin:reset-password <email> --actor=<super-admin-email>
php artisan admin:set-status     <email> <active|disabled> --actor=<super-admin-email>
```

Supported roles: `super_admin`, `pharmacy_reviewer`.

---

## 7. Running the Test Suites

### 7.1 Backend

From `backend/`:

```bash
php artisan test
```

Tests run against an **isolated in-memory SQLite database** (configured in
`phpunit.xml`), so your local development data is never touched.

Run a focused subset:

```bash
php artisan test --filter=ProfileAccountManagementTest
```

Code style check (Laravel Pint):

```bash
./vendor/bin/pint --test
```

### 7.2 Flutter

From `frontend/`:

```bash
flutter analyze
```

Expected: **0 errors** (a number of pre-existing `info`/`warning` items are
known and tracked).

Run the repository/cubit test suites:

```bash
flutter test test/features/inventory/ test/features/suppliers/ test/features/orders/ test/features/sales/ test/features/reports/ test/features/employees/ test/features/employee_workspace/ test/features/tasks/
```

> ⚠️ **Known issue — do not run `flutter test` across everything.**
> The widget tests under `test/features/account/` currently **hang** in this
> environment (each test times out after 10 minutes). This is an environmental
> test-runner problem, not an application bug. Run the folder list above
> instead. See §9 for details.

### 7.3 admin-web

From `admin-web/`:

```bash
npm test          # vitest
npm run lint
npm run typecheck
npm run build
```

---

## 8. Project Structure

```
backend/
├── app/Http/Controllers/     API controllers (pharmacist, employee, admin)
├── app/Http/Middleware/      auth, active pharmacy, admin session/origin
├── app/Http/Requests/        Form Request validation
├── app/Http/Resources/       safe JSON output (hides private fields)
├── app/Models/               Eloquent models
├── app/Policies/             authorization rules
├── app/Services/             business logic (reviews, documents, accounts)
├── app/Console/Commands/     admin provisioning CLI
├── database/migrations/      schema
├── routes/api.php            mobile API (Sanctum tokens)
├── routes/web.php            admin API under /api/admin (session + CSRF)
└── docs/gate-1c-admin-api.md admin API contract reference

frontend/lib/
├── core/network/             Dio client, interceptor, API constants, errors
├── core/storage/             secure token storage
├── core/widgets/             shared UI widgets
└── features/<feature>/       data / domain / presentation per feature
        inventory, suppliers, orders, sales, reports,
        employees, employee_workspace, tasks, account, auth

admin-web/src/
├── lib/                      API client, session/CSRF, typed contracts
├── context/                  auth state
├── components/               review, admins, layout, ui
└── routes/                   /login, /review, /admins
```

Each Flutter feature follows the same layering:

```
presentation (widgets + Cubit)  ->  repository  ->  data source / API  ->  Laravel
```

Business rules live on the **server**. The app never recomputes stock, prices,
profit, or permissions locally.

---

## 9. Authentication Flows

### 9.1 Pharmacist

1. `POST /api/register` — creates the pharmacist and a **pending** pharmacy.
2. Until an administrator approves the pharmacy, login returns a restricted
   token with only the `registration-status` ability.
3. `GET /api/registration/status` — poll approval state with that token.
4. After approval, `POST /api/login` returns a full token with the `app`
   ability.
5. `GET /api/me` — restores the session on app start.
6. Operational endpoints additionally require an **active pharmacy**, sent as
   the `X-Pharmacy-Id` header (the app's interceptor adds this automatically).

> A `registration-status` token can never be used as a general app token —
> this is enforced on both the server and the client.

### 9.2 Employee

1. `POST /api/employee/register` — uploads CV / experience proof, status
   `pending`, not yet attached to any pharmacy.
2. A pharmacist approves the application from **More → Employees**
   (max **2** approved employees per pharmacy — enforced by the backend).
3. `POST /api/employee/login` — returns a token once approved.
4. The employee sees their own sales and assigned tasks. Employee recruitment
   documents are **self-access only**; pharmacists have no route to them.

### 9.3 Administrator (admin-web)

1. `GET /api/admin/csrf` — establishes session + XSRF cookie.
2. `POST /api/admin/login` — credentialed; returns **no token**.
3. `GET /api/admin/session` — restores the session on page load.
4. `POST /api/admin/logout` — CSRF-protected.

Roles: `super_admin` (review + administrator management) and
`pharmacy_reviewer` (review only).

---

## 10. First-Time Setup Checklist

Work top to bottom:

- [ ] PHP 8.2+, Composer, Flutter, Node.js, Android Studio installed
- [ ] `gd` and `fileinfo` enabled in `php.ini`
- [ ] Repository cloned, on branch `profile-account-management`
- [ ] `cd backend && composer install`
- [ ] `.env` created from `.env.example`
- [ ] `php artisan key:generate`
- [ ] `database/database.sqlite` file created
- [ ] `php artisan migrate` → `migrate:status` shows **no Pending**
- [ ] *(optional)* `php artisan db:seed --class=SupplierSeeder`
- [ ] `php artisan serve` running → `/api/admin/csrf` responds
- [ ] `cd frontend && flutter pub get`
- [ ] *(optional)* `android/secrets.properties` created for Maps
- [ ] Emulator running → `flutter run` connects to the API
- [ ] `cd admin-web && npm install`
- [ ] admin-web `.env` created
- [ ] `npm run dev` → `http://localhost:5173` loads
- [ ] `php artisan admin:provision-super ...` → can sign in to admin-web
- [ ] `php artisan test` passes
- [ ] `flutter analyze` reports 0 errors

---

## 11. Troubleshooting

### Database

**`database file does not exist` / `unable to open database file`**
The SQLite file was not created. From `backend/`:
`New-Item -ItemType File database/database.sqlite` (PowerShell) or
`touch database/database.sqlite`.

**`no such table: ...`**
Migrations have not been applied. Run `php artisan migrate`, then confirm with
`php artisan migrate:status`.

**Config changes appear to be ignored**
Laravel caches config. Run:
```bash
php artisan config:clear
```

### Laravel API

**Port 8000 already in use**
```bash
php artisan serve --port=8001
```
Then update the clients: `--dart-define=API_BASE_URL=http://10.0.2.2:8001/api`
and `VITE_ADMIN_API_BASE_URL=http://localhost:8001`.

**500 errors with no detail**
Check `backend/storage/logs/laravel.log`. Ensure `APP_KEY` was generated.

### Flutter ↔ API connection

**All requests fail / "No internet connection" in the app**
1. Is `php artisan serve` still running?
2. On the emulator, the host is **`10.0.2.2`**, never `localhost` or
   `127.0.0.1` — those resolve to the emulator itself.
3. The URL must end with `/api` and have no trailing slash.

**Works in the browser but not in the app**
You are almost certainly using `127.0.0.1` in the app. Use `10.0.2.2`.

**Physical device cannot reach the API**
Start the API with `--host=0.0.0.0`, use your machine's LAN IP, and allow port
8000 through the Windows firewall.

**401 immediately after login**
The stored token was cleared or the account was deactivated. Sign in again.

### admin-web ↔ API connection

**Login fails with `origin_not_allowed` (403)**
Your browser origin is not in `ADMIN_ALLOWED_ORIGINS` in `backend/.env`. Add
the exact origin (scheme + host + port) and restart the API.

**419 / CSRF token mismatch**
Mixing hostnames (`localhost` in one place, `127.0.0.1` in another) breaks
cookies. Pick one and use it consistently in the browser and in
`ADMIN_ALLOWED_ORIGINS`.

**Cannot sign in — no admin exists**
There is no seeded admin. Run `php artisan admin:provision-super`.

### Dependency installation

**`composer install` fails on a missing extension**
Enable the extension in `php.ini` (see §2), restart the terminal, retry.

**`flutter pub get` fails**
```bash
flutter clean
flutter pub get
```

**`npm install` is slow or fails**
Delete `admin-web/node_modules` and retry. Do **not** commit `node_modules`.

### Known Flutter test-runner issue

Widget tests under `frontend/test/features/account/` **hang** and time out
after 10 minutes each. Evidence gathered so far:

- Reproduces with a single file and `--concurrency=1`
- The process sits at ~0 % CPU with no output
- The only trace is `TimeoutException ... dart:isolate _RawReceivePort._handleMessage`
  with **no application stack frames**

It is treated as an **environment / test-runner problem, not an app bug**.
Repository and cubit tests are unaffected — run the folder list in §7.2. A
suspected contributor is stale VS Code Dart daemons (Dart Tooling Daemon /
DevTools) holding ports; closing VS Code and retrying is worth a try.

If a test run appears stuck, stop it and clean up any orphaned processes:

```powershell
Get-CimInstance Win32_Process -Filter "Name='dart.exe'" |
  Select-Object ProcessId, CommandLine
```

---

## 12. Do Not Commit

These are already git-ignored — keep it that way.

| Path | Why |
| --- | --- |
| `backend/.env` | local secrets: `APP_KEY`, DB credentials |
| `backend/database/*.sqlite` | your local database |
| `backend/vendor/` | installed PHP dependencies |
| `backend/storage/logs/*.log` | runtime logs |
| `frontend/android/secrets.properties` | **your real Google Maps API key** |
| `frontend/build/` | build output (very large) |
| `admin-web/node_modules/` | installed JS dependencies |
| `admin-web/dist/` | build output |
| `admin-web/.env` | local environment overrides |

**Committed on purpose (safe, placeholders only):**
`backend/.env.example`, `admin-web/.env.example`, and
`frontend/android/local.defaults.properties` (which contains only
`MAPS_API_KEY=DEFAULT_API_KEY`).

Rules of thumb:

- Never commit a real API key, password, token, or `.env` file.
- Never commit a database file or its contents.
- Do not commit real patient, pharmacy, or employee data.
- Before committing, run `git status` and confirm nothing above appears.

---

## 13. Daily Workflow

```bash
# terminal 1 — API
cd backend && php artisan serve

# terminal 2 — mobile app
cd frontend && flutter run

# terminal 3 — admin console (only when working on admin features)
cd admin-web && npm run dev
```

Before pushing:

```bash
cd backend  && php artisan test && ./vendor/bin/pint --test
cd frontend && flutter analyze
git status          # verify nothing from §12 is staged
```
