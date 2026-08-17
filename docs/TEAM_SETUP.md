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
   API_BASE_URL (see §5.3)          http://localhost:8000/api/admin
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

### 4.7 Seed the supplier catalogue (needed for a working demo)

`DatabaseSeeder` is intentionally **empty**, so `php artisan db:seed` alone
does nothing. Run the four seeders by class, **in this order** — the catalogue
rows reference supplier ids, so `SupplierSeeder` must go first:

```bash
php artisan db:seed --class=SupplierSeeder
php artisan db:seed --class=MedicalPharma
php artisan db:seed --class=DrPharma
php artisan db:seed --class=MedCorePharma
```

This creates **3 demo suppliers** and **25 catalogue medicines** covering all
8 categories.

- The suppliers are fictional demo companies marked `(Demo)`, based in
  Damascus, Rif Dimashq and Aleppo, with Syrian-format phone numbers.
- Prices are demo values in **Syrian Pounds (SYP)** — not real market prices.
- Expiry dates are generated **relative to the day you seed**
  (`CatalogueSeeding` adds N months to `now()`), so seeded stock is never
  already expired, no matter how old your checkout is.

`CatalogueSeeding` is a shared helper, **not** a seeder — don't call it
directly.

Without these, Suppliers and Purchases will legitimately show empty lists.

> **Re-running is safe.** All four use `updateOrCreate`, so they refresh the
> demo rows instead of duplicating them.

#### What the seeders do *not* create

Catalogue rows are the **global supplier catalogue** (`pharmacy_id = null`) —
they are what you order *from*. Your pharmacy starts with **zero stock of its
own**, which is correct. To get sellable inventory, walk the real flow:

**Suppliers → Purchases (create order) → Receive → Medicines → POS → Reports**

Receiving an order is what copies catalogue items into your pharmacy's own
inventory.

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

> ⚠️ **Changed — read this even if you set the project up before.**
> The committed default is **no longer the emulator address**. It is now a
> LAN IP, because the app is being developed against a physical device:
>
> ```dart
> static const String baseUrl = String.fromEnvironment(
>   'API_BASE_URL',
>   defaultValue: 'http://192.168.1.8:8000/api',
> );
> ```
>
> `192.168.1.8` is **one specific machine on one specific network** — it will
> not work for you. Every developer must override it.

Override it with `--dart-define` (no file edit, nothing to accidentally
commit):

```bash
# Android Emulator
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api

# Physical device on the same Wi-Fi — use YOUR machine's LAN IP
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8000/api
```

Find your LAN IP with `ipconfig` (Windows) or `ifconfig | grep inet`
(macOS/Linux), and remember the API must be started with
`php artisan serve --host=0.0.0.0` for a physical device to reach it.

> Please **don't** commit a change to the `defaultValue` just to match your
> own machine — it breaks everyone else. Use `--dart-define`.

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

Current expected result: **172 passed, 1 skipped** (1073 assertions).

The single skip is environmental, not a failure —
`LegacyDocumentMigrationCommandTest` needs file symlinks, which this Windows
runtime does not allow.

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

Expected: **0 errors, 0 warnings**, and 18 pre-existing `info` items (mostly
`withOpacity` deprecations).

Run the test suite. **Use this command, not a bare `flutter test`:**

```bash
flutter test --timeout=30s test/core test/features/auth test/features/employee_workspace test/features/employees test/features/inventory test/features/notifications test/features/orders test/features/reports test/features/sales test/features/suppliers test/features/tasks test/features/account/account_cubit_test.dart test/features/account/account_repository_test.dart test/features/account/pharmacy_location_controller_test.dart test/features/account/pharmacy_location_picker_page_test.dart test/features/account/settings_widgets_test.dart
```

Expected: **125 passed**, in roughly 15–20 seconds.

> ⚠️ **Known issue — a bare `flutter test` will hang forever.**
> Exactly **three** files hang and never finish:
>
> - `test/features/account/settings_change_password_page_test.dart`
> - `test/features/account/settings_edit_pharmacy_page_test.dart`
> - `test/features/account/settings_edit_profile_page_test.dart`
>
> Everything else in `test/features/account/` passes normally, so exclude the
> three files — **not** the whole folder. `--timeout` does **not** rescue you
> here; the stall happens outside the per-test clock. See §12.

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

**Owning more than one pharmacy.** A pharmacist can register additional
pharmacies from **Settings → PHARMACY → Add Pharmacy** (`POST /pharmacy/add`:
name, address, certificate file, license file). The new pharmacy is created
**pending** and goes into the same admin review queue as a first-time
applicant — it does *not* become active, and your current pharmacy and its
data are untouched.

Once an admin approves it, tap **Settings → Check for Approvals** (this calls
`GET /api/me` on demand — nothing polls) and the pharmacy becomes selectable
in the **Active Pharmacy** tile. That tile only becomes tappable once you have
**2 or more approved** pharmacies.

> Switching the active pharmacy re-scopes every screen server-side. A request
> for a record belonging to a different pharmacy is rejected with
> `403 active_pharmacy_mismatch`, even when you own both.

### 9.2 Employee

1. `POST /api/employee/register` — uploads CV / experience proof, status
   `pending`, not yet attached to any pharmacy.
2. A pharmacist approves the application from **More → Employees**
   (max **2** approved employees per pharmacy — enforced by the backend).
3. `POST /api/employee/login` — returns a token once approved.
4. Employee recruitment documents are **self-access only**; pharmacists have
   no route to them.

The employee app is a **3-tab shell** exposing only what the backend
authorises for the `employee` guard:

| Tab | Contents |
| --- | --- |
| **Home** | own sales, assigned tasks, notifications, My Account |
| **Medicines** | read-only catalogue (no add/edit) |
| **POS** | record a sale (`POST /sale/create`) |

From **Home → My Account** an employee can edit their **name and phone** and
change their **password** (`/employee/profile`, `/employee/profile/update`,
`/employee/password/change`). Everything else — email, role, salary, status,
pharmacy — is rejected server-side as a prohibited field.

Pharmacist-only areas are deliberately absent from the employee shell:
employees management, pharmacy profile, suppliers, purchases, reports, and
task creation. The backend is the source of truth; hiding them in the UI is
convenience, not the security boundary.

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
- [ ] All 4 seeders run in order (§4.7) → 3 suppliers, 25 catalogue medicines
- [ ] `php artisan serve` running → `/api/admin/csrf` responds
- [ ] `cd frontend && flutter pub get`
- [ ] *(optional)* `android/secrets.properties` created for Maps
- [ ] `flutter run --dart-define=API_BASE_URL=...` with **your** address (§5.3)
- [ ] `cd admin-web && npm install`
- [ ] admin-web `.env` created
- [ ] `npm run dev` → `http://localhost:5173` loads
- [ ] `php artisan admin:provision-super ...` → can sign in to admin-web
- [ ] `php artisan test` → 172 passed, 1 skipped
- [ ] `flutter analyze` → 0 errors, 0 warnings
- [ ] Flutter tests via the §7.2 command → 125 passed

---

## 11. First Demo Run

Fresh database + seeders gives you suppliers and a catalogue, but **no
sellable stock and no sales history** — that is correct, not a bug. Walk the
chain once and every screen fills with real data:

1. **Register** a pharmacist in the app → pharmacy is created `pending`.
2. **Approve it** in admin-web (Review → Applications).
3. **Log in** to the app.
4. **More → Suppliers** → pick a supplier → see its catalogue.
5. **More → Purchases → create an order** against that supplier.
6. **Receive** the order → catalogue items are copied into *your* inventory.
7. **Medicines** now lists your stock; **POS** can sell it.
8. **Make a sale** → Home dashboard and **Analytics** show revenue and profit.
9. Sell an item below its reorder level → it appears under **low stock**.

Amounts display in **SYP** throughout.

To demo **multi-pharmacy switching** you need **two approved** pharmacies on
the *same* pharmacist: add a second via Settings → Add Pharmacy, approve it in
admin-web, then Settings → Check for Approvals. With only one approved
pharmacy the switcher stays inert by design.

---

## 12. Troubleshooting

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

Three widget-test files hang and never complete (listed in §7.2). This is a
**test-runner problem, not an application bug** — the screens themselves work,
and their logic is covered by cubit and repository tests.

What is actually known, so nobody re-derives it:

- It is **file-specific**, not folder-wide. `settings_widgets_test.dart` and
  `pharmacy_location_picker_page_test.dart` live in the same folder and pass.
- The rest of the suite completes in ~16 s; the run then sits with no output
  and no CPU. A bare `flutter test` reaches 125 passing tests and stops there.
- `--timeout=30s` does **not** bound it — the stall is outside the per-test
  clock.
- Two earlier theories were **tested and disproved**: it is not
  `pumpAndSettle` against the loading spinner, and it is not a pending
  `SnackBar` timer. The true cause is still open.

**Related, and worth knowing before you write new widget tests:** any page
that imports `file_picker` (`add_pharmacy_page.dart`, `signup_page2.dart`,
`employee_signup_page.dart`) hangs the runner **just by being mounted** — no
interaction needed. The plugin's platform singleton does not initialise under
`flutter_tester`. No widget test currently covers those pages; test them at
the cubit level instead until someone stubs the plugin.

If a run appears stuck, stop it and clean up orphaned processes — but **do not
kill the VS Code Dart daemons** (`language-server`, `tooling-daemon`,
`devtools`), or you will break your editor's analysis until you restart it:

```powershell
Get-CimInstance Win32_Process -Filter "Name='dart.exe' OR Name='flutter_tester.exe'" |
  Select-Object ProcessId, ParentProcessId, CommandLine
```

Kill only the `flutter_tester.exe` processes and the `dart.exe` ones whose
command line contains `flutter_tools`.

---

## 13. Do Not Commit

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

## 14. Daily Workflow

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
git status          # verify nothing from §13 is staged
```
