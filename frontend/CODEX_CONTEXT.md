# Codex Project Context

## Purpose

This document is the durable technical context for future Codex sessions working on the Pharmacy Management System. Read it before proposing or implementing changes.

The system consists of two repositories:

- Flutter frontend: `C:\Users\ASUSD\Desktop\Pharmacy-App-Front\pharmacy-app`
- Laravel backend: `C:\Users\ASUSD\Desktop\Pharmacy-App`

The current Flutter application is a prototype. The Laravel backend is more complete than the client, but it requires security, contract, and data-model hardening before the operational APIs are connected to Flutter.

## Working Agreement

For every requested feature:

1. Analyze the idea critically.
2. Explain its impact on the existing architecture.
3. Recommend improvements where necessary.
4. Design database changes.
5. Design API endpoints and contracts.
6. Design Flutter changes.
7. Wait for explicit approval.
8. Implement only after approval.

Additional constraints:

- Do not rewrite either project.
- Preserve existing Flutter pages and widgets where practical.
- Use incremental migrations and minimize breaking changes.
- Do not trust client-side authorization.
- Laravel must become the source of truth for operational data.
- Complete one milestone at a time.
- Add tests around critical behavior before connecting it to Flutter.

## Executive Architecture Understanding

### Current System Shape

The Flutter client and Laravel backend currently behave like two partially independent systems:

- Flutter calls Laravel only for pharmacist login and two-stage pharmacist/pharmacy registration.
- Flutter medicines, sales, purchases, and suppliers use mutable global in-memory lists.
- Laravel already provides APIs and database models for medicines, sales, orders, suppliers, reports, employees, notifications, ratings, and tasks.
- Most of those backend APIs are not called by Flutter.
- Flutter operational data disappears when the process restarts.
- Laravel operational data persists, but many endpoints have authorization and ownership vulnerabilities.

### Target Direction

Use a gradual strangler migration:

1. Stabilize and secure existing API contracts.
2. Introduce a unified authenticated session and active-pharmacy context.
3. Migrate one Flutter feature at a time from global lists to repository/Cubit/API state.
4. Preserve current visual widgets while replacing their data sources.
5. Extend the current database rather than replacing it.
6. Add batch inventory and an inventory ledger before declaring inventory or POS production-ready.

## Flutter Architecture

### Application Bootstrap

`lib/main.dart`:

- Creates `TokenStorage`.
- Initializes the static Dio client.
- Provides `ThemeProvider` through Provider.
- Provides `AuthCubit` through Bloc.
- Starts at `AccountTypePage`.
- Does not restore or validate an existing authenticated session.

### Flutter Structure

`lib/core` contains:

- `network`: Dio configuration, API constants, auth interceptor, error handler.
- `storage`: token storage implemented with `SharedPreferences` despite the secure-storage name.
- `theme`: application colors and a simple theme provider.
- `data`: mutable global lists for medicines, sales, purchases, suppliers, and duplicated current-user declarations.
- `widgets`: reusable and feature-specific widgets.
- `biometric`: currently only a placeholder.

`lib/features/auth` currently contains much more than authentication:

- Authentication datasource, repository, models, Cubit, and states.
- All application pages: inventory, POS, suppliers, purchases, analytics, settings, dashboard, and employee flows.

Do not perform a large folder move immediately. New feature repositories, DTOs, and Cubits should be created in properly named feature modules as those features are migrated. Existing page paths can move later when doing so is low risk.

### Flutter Authentication Flow

Pharmacist login:

`LoginPage -> AuthCubit -> AuthRepository -> AuthApi -> Dio -> Laravel`

On success:

- Flutter saves only the returned token.
- It navigates to `MainNavigationPage`.
- It does not load the authenticated pharmacist, pharmacies, permissions, or active pharmacy.

Pharmacist registration:

1. `SignupPage1` sends pharmacist identity and optional profile image.
2. Laravel is expected to return `pharmacist_id`.
3. `SignupPage2` sends pharmacy details, certificate, and license.
4. Flutter opens `PendingPage`.

Employee registration/login screens exist, but both contain API TODOs and do not match the current Laravel request contract.

### Flutter Main Navigation

`MainNavigationPage` has five tabs:

1. Home
2. Medicines
3. POS
4. Analytics
5. More

The More page links to inventory, sales history, suppliers, purchases, and settings.

### Flutter Operational Data

The following globals currently act as the frontend database:

- `lib/core/data/medicine_data.dart`: empty medicine list.
- `lib/core/data/sales_data.dart`: empty sales list.
- `lib/core/data/purchase_data.dart`: empty purchase list.
- `lib/core/data/supplier_data.dart`: one seeded supplier with two products.

There are three separate current-user globals:

- `lib/core/globals.dart`
- `lib/core/data/current_user_data.dart`
- `lib/core/data/user_data.dart`

They are not reliably populated and must eventually be replaced by one session state.

### Flutter Business Workflows

Medicines:

- `AddMedicinePage` directly adds or replaces entries in the global medicine list.
- Medicine cards directly delete entries.
- Category filtering works.
- Medicine search is displayed but is not applied to `MedicineList`.

Inventory:

- Reads the same medicine list.
- Calculates low-stock, out-of-stock, and expiry statistics locally.
- Inventory search does not rebuild while typing.
- The Expiring list filter currently returns all matching medicines.

POS:

- Searches local medicines by name or barcode.
- Maintains a local page cart.
- Limits cart quantity to local stock.
- On completion, directly reduces local medicine quantities and appends a local sale.
- Uses unsafe numeric parsing on user-entered string fields.
- Does not call Laravel's existing sale endpoint.

Suppliers and purchases:

- Supplier data is local and seeded.
- The supplier add/edit form is fully commented out, so the page is blank.
- Buying locally creates a purchase with a fixed quantity of 50.
- Receiving locally updates an existing medicine by matching its name.
- Flutter's purchase model represents one medicine, while Laravel orders support multiple line items.

Dashboard and history:

- Dashboard statistics scan local lists.
- Revenue chart values are hard-coded.
- Recent sales is a placeholder.
- Sales history reads only local POS sales.
- Analytics page contains only an app bar.

Settings:

- Profile data is hard-coded as Maya Pharmacy.
- Profile edits are not persisted.
- Logout only resets navigation and does not revoke or clear the token.
- Delete account is a UI no-op.
- Rating is local.
- Dark mode is partial because many widgets hard-code light colors.

## Laravel Architecture

### Framework and Authentication

- Laravel 12, PHP 8.2+, Sanctum 4.
- Separate authenticatable models and guards for `Pharmacist` and `Employee`.
- Personal access tokens are used.
- Sanctum token expiration is currently `null`.
- The default auth guard is `pharmacist`.
- Laravel 12 middleware aliases are configured through `bootstrap/app.php`.
- `app/Http/Kernel.php` appears to be legacy configuration and should not be treated as authoritative.

### Existing Backend Domains

Models and migrations currently exist for:

- Pharmacists
- Pharmacies
- Employees
- Suppliers
- Medicines
- Sales and sale items
- Orders and order items
- Notifications
- Ratings
- Tasks
- Personal access tokens

Laravel also contains controllers for:

- Pharmacist registration, login, profile, pharmacy management, and approval
- Employee registration, login, approval, dismissal, and listing
- Medicine CRUD-like operations and stock filters
- Sales and sales history
- Orders and receiving
- Suppliers
- Reports/dashboard
- Notifications
- Ratings
- Tasks
- An incomplete admin dashboard

### Missing Backend Layers

The reviewed Laravel application has no application-specific:

- `app/Http/Requests`
- `app/Services`
- `app/Policies`
- `app/Http/Resources`
- `app/Exceptions`

Business logic, validation, authorization, transactions, and response construction currently live directly in controllers.

### Existing API Groups

Public routes include:

- Pharmacist registration
- Pharmacy registration
- Pharmacist login
- Employee registration
- Employee login

Employee-authenticated routes include:

- Medicine listing/search/filters
- Sale creation and employee sales
- Notifications
- Tasks

Pharmacist-authenticated routes include:

- Logout and account deletion
- Profile and pharmacy updates
- Employee approval and management
- Sales
- Medicines
- Suppliers
- Orders
- Reports
- Notifications
- Ratings
- Tasks

Administrative routes currently have no effective authentication middleware.

### Existing Backend Strengths to Preserve

- Sanctum authentication foundation.
- Pharmacy relationships on core records.
- Decimal columns for prices.
- Separate sale and order line-item tables.
- Database transactions around sale creation and order creation/receipt.
- Medicine filtering endpoints.
- Existing dashboard/report endpoints.
- Existing notifications, employee, rating, and task concepts.
- Foreign keys in migrations.

These should be hardened and extended, not discarded.

## Flutter and Laravel Contract Mismatches

### Pharmacist Registration

Flutter:

- Calls `POST /register/pharmacist`.
- Sends profile image as `profile`.

Laravel:

- Exposes `POST /register`.
- Expects profile image as `profile_image`.

Result: registration currently has a route mismatch, and the image field contract also differs.

### Pharmacy Registration

Both sides use `/register/pharmacy` and agree on:

- `pharmacist_id`
- `pharmacy_name`
- `pharmacy_address`
- `certificate`
- `license`

The Laravel response is not represented by a typed Flutter DTO.

### Pharmacist Login

Both sides use `/login` and agree on email/password and token output.

Problems:

- Laravel returns only a token, not a complete session resource.
- Flutter stores the token but no actor/pharmacy information.
- Laravel checks only the pharmacist's first pharmacy when deciding approval status.
- Flutter identifies pending/rejected states by searching backend message text.

### Employee Registration

Flutter currently collects:

- Name
- Phone
- Location
- Employee/trainee selection
- CV
- Optional/required certificate based on type

Laravel expects:

- Name
- Phone
- Email
- Password
- CV
- `experience_proof`
- Role

The employee form and endpoint cannot be connected safely until one contract is agreed.

### Medicines

Flutter and Laravel field names differ:

- Flutter `category`; Laravel `category_medicine`
- Flutter `expiryDate`; Laravel `expire_date`
- Flutter `barcode`; Laravel `qr_code`
- Flutter includes `notes`; Laravel has no medicine notes column
- Laravel includes `Antidiabetics`; Flutter's category list does not

Use explicit DTO mappings. Do not make widgets depend directly on raw Laravel field names.

### Sales

- Laravel supports multi-line sales and persists them transactionally.
- Flutter generates its own timestamp invoice number and stores sales locally.
- Laravel accepts lowercase payment values; Flutter uses capitalized display strings.
- Laravel currently trusts client-supplied pharmacy and seller IDs.
- Laravel does not prevent concurrent overselling or duplicate retries.

### Orders

- Laravel supports multi-line orders.
- Flutter models one medicine per purchase.
- Laravel can create inventory on receipt but matches existing products by medicine name.
- Laravel does not support partial receiving.
- Laravel order ID operations are not ownership-scoped.

## Current Critical Problems

### Security

1. Admin routes are public, including pharmacy approve/reject routes.
2. Admin middleware is not used by those routes and is not registered in active Laravel 12 bootstrap configuration.
3. Most endpoints trust submitted `pharmacy_id` without verifying ownership.
4. Record-ID actions are frequently unscoped:
   - Medicine edit
   - Order detail/receive/cancel
   - Notification read/delete
   - Task delete
5. Sale creation trusts submitted pharmacist/employee IDs.
6. Sale items can reference medicines from another pharmacy.
7. Rating trusts submitted pharmacist ID.
8. Employee rejection changes global applicant status without pharmacy-specific review state.
9. Certificates, licenses, CVs, and experience files are stored on the public disk.
10. Uploads have no maximum file-size rules.
11. Sanctum tokens never expire.
12. Flutter stores tokens in `SharedPreferences`.
13. Flutter logout does not revoke or clear tokens.
14. Password updates do not require the current password.
15. Laravel returns raw exception messages to API clients.
16. Backend account deletion can cascade-delete operational records.
17. Flutter uses a hard-coded HTTP development URL.

### Backend Correctness

1. `AdminController.php` defines `AdminDashboardController`, violating PSR-4 naming.
2. Admin controller references missing `Branch`, `Ticket`, and `JobPost` models/tables.
3. `RoleMiddleware` returns debug JSON before performing any checks.
4. Employee logout method exists without an API route.
5. Pharmacist login checks only the first pharmacy instead of selecting an approved pharmacy.
6. Sales check then decrement stock without row locks, allowing an overselling race.
7. Sales have no idempotency key, so retries can duplicate transactions.
8. Order receiving merges products by name, which can combine different formulations.
9. Profit reports use current medicine cost, so historical profit changes after cost edits.
10. Average-sales calculation computes `daysPassed` but always divides by seven.
11. Low-stock and expiry GET endpoints create notifications as side effects.
12. Notification deduplication searches message text and can suppress future legitimate alerts.
13. QR validation requires numeric input even though real barcodes may be strings.
14. `DatabaseSeeder` does not invoke any project seeders.
15. Many seeded expiry dates are already expired as of July 2026.
16. There are no meaningful backend tests beyond Laravel examples.

### Database Design

1. One medicine row has one quantity and expiry date; there are no batches.
2. There is no immutable inventory movement ledger.
3. Supplier catalog products are represented as medicines with `pharmacy_id = null`.
4. Sale items do not snapshot unit cost.
5. There is no business invoice number or idempotency key.
6. Orders support only full receipt.
7. Financial records use destructive cascades.
8. There is no audit log.
9. There are no returns/refunds or stocktaking tables.
10. Database check and composite uniqueness constraints are insufficient.
11. Employee application status is global rather than pharmacy-specific.

### Flutter Correctness and Maintainability

1. Medicine search is not applied.
2. Inventory search does not rebuild.
3. Expiring inventory filter returns all medicines.
4. Dashboard expiry statistics count any nonempty expiry date.
5. Supplier add/edit page is blank.
6. POS and purchase receiving can crash on invalid numeric strings.
7. Purchase quantity is fixed at 50.
8. Local receipt only updates an already matching medicine.
9. Profile editing reports success without persistence.
10. Delete account is a UI no-op.
11. There are duplicate current-user globals.
12. Several controllers are not disposed.
13. Dark mode is incomplete.
14. Analytics, AI insights, splash, biometrics, and other areas are empty or placeholders.

## Architectural Decisions Made

1. Do not rewrite the Flutter or Laravel project.
2. Keep the existing Flutter UI wherever it remains suitable.
3. Laravel will be the authoritative data source for operational records.
4. Global Flutter lists will be removed feature by feature, not all at once.
5. Security and tenant authorization must be fixed before connecting operational Flutter screens.
6. Existing Laravel models, tables, controllers, and endpoints should be hardened incrementally.
7. Add Form Requests, Policies, Resources, and service/action classes around high-risk operations first.
8. Keep separate pharmacist and employee tables initially to minimize breaking changes.
9. Add a unified Flutter session model containing actor, role, pharmacies, permissions, status, and active pharmacy.
10. Introduce versioned API contracts and stable error codes.
11. Extend the current database additively with batches, movements, cost snapshots, receipts, audit events, and constraints.
12. Do not connect Flutter POS until Laravel sales are ownership-safe, concurrency-safe, and idempotent.
13. Do not expose admin functionality until admin authentication and missing admin domain models are complete.
14. Private documents must move off the public disk before production.
15. Complete one approved milestone at a time.

## Recommended Roadmap

### Milestone 0: Contract and Security Stabilization

Goal: make the existing backend safe enough for integration.

Tasks:

- Protect or disable all admin routes.
- Fix admin class/file mismatch and remove/disable routes using missing models.
- Remove the role middleware debug return.
- Add Laravel Policies and pharmacy-scoped route/model access.
- Stop trusting client-supplied actor IDs.
- Align pharmacist registration route and multipart fields.
- Add stable API errors.
- Move sensitive files to private storage.
- Add upload size limits.
- Add critical authorization tests.

This is the immediate next milestone.

### Milestone 1: Session and Active Pharmacy

- Secure Flutter token storage.
- Add a unified Flutter session Cubit.
- Add Laravel `/api/v1/auth/me` and normalized login/logout responses.
- Return actor, role, account status, owned/assigned pharmacies, permissions, and active-pharmacy options.
- Add token expiration/revocation behavior.
- Make Flutter logout call Laravel and clear local state.
- Correct pharmacist multi-pharmacy login behavior.

### Milestone 2: Medicines and Inventory Integration

- Harden medicine authorization and API Resources.
- Add pagination and search by name/barcode.
- Introduce Flutter medicine/inventory repositories and Cubits.
- Preserve existing medicine and inventory widgets.
- Remove direct global medicine mutations after migration.
- Add inventory batches and inventory movements before production sign-off.

### Milestone 3: Suppliers

- Decide whether suppliers are global platform data or pharmacy relationships.
- Add supplier mutation endpoints where required.
- Add `supplier_products` and migrate null-pharmacy catalog medicines.
- Restore Flutter supplier form and integrate supplier state.

### Milestone 4: Orders and Receiving

- Scope order operations to authorized pharmacies.
- Add order Resources and service/action logic.
- Align Flutter with multi-line orders.
- Add partial goods receipts.
- Create batches and inventory movements during receipt.
- Add idempotency and transaction tests.

### Milestone 5: POS and Sales

- Derive seller and pharmacy from authenticated context.
- Validate every medicine against the active pharmacy.
- Add row locking or atomic stock updates.
- Add idempotency keys and invoice numbers.
- Snapshot unit cost.
- Move Flutter cart behavior into a POS Cubit.
- Submit one authoritative Laravel transaction.
- Integrate sales history and invoice details.

### Milestone 6: Returns, Adjustments, and Stocktaking

- Add returns and refunds.
- Add damaged/expired stock disposal.
- Add stock adjustment reasons and permissions.
- Add stock-count sessions and variance review.
- Record all changes in the inventory ledger and audit log.

### Milestone 7: Employees and Tasks

- Finalize employee registration contract.
- Model pharmacy-specific applications/reviews.
- Integrate employee login and session behavior.
- Add pharmacist employee-review UI.
- Secure employee documents.
- Integrate existing tasks APIs.

### Milestone 8: Dashboard, Analytics, Notifications, and Settings

- Correct report calculations, especially cost/profit history.
- Integrate existing report endpoints.
- Move alert generation to jobs/events.
- Integrate notification inbox and badge.
- Integrate profile and pharmacy settings.
- Complete theme and localization behavior.

### Milestone 9: Production Hardening

- Add meaningful Flutter and Laravel automated tests.
- Add CI/CD, staging, and controlled migrations.
- Add monitoring, structured logs, metrics, and crash reporting.
- Configure HTTPS-only production environments.
- Configure backups and test restoration.
- Add privacy, retention, and audit procedures.
- Complete accessibility, responsive layout, and localization testing.
- Perform security and concurrency testing.

## Immediate Next Steps

Before implementing a new product feature:

1. Approve Milestone 0: Contract and Security Stabilization.
2. Decide the canonical pharmacist registration route:
   - Recommended: versioned `POST /api/v1/auth/pharmacists/register`, with a temporary compatibility alias if needed.
3. Decide the admin identity model:
   - Recommended: authenticated admin accounts with roles and audit logs, not a shared static header key.
4. Decide active-pharmacy behavior for pharmacists with multiple pharmacies.
5. Decide whether suppliers are platform-global or pharmacy-specific.
6. Decide whether employee applicants can apply to multiple pharmacies independently.
7. Add authorization tests before connecting any operational Flutter endpoint.

The safest first implementation slice is:

- Protect/disable admin routes.
- Add pharmacy ownership policies for medicine operations.
- Align pharmacist registration route and field names.
- Add a typed session endpoint.
- Add tests proving one pharmacy cannot read or mutate another pharmacy's data.

## Production Readiness Definition

The project is not production-ready until all of the following are true:

- No operational Flutter screen relies on global in-memory data.
- All backend records are tenant-scoped and authorization-tested.
- Admin routes require secure authenticated authorization.
- Tokens are secure, expiring/revocable, and cleared on logout.
- Sensitive documents are private.
- Sales are concurrency-safe and idempotent.
- Inventory is batch-aware and ledger-backed.
- Historical financial data cannot change after master-data edits.
- Returns, adjustments, and stocktaking are supported.
- Critical workflows have automated tests.
- Backups, monitoring, deployment, and rollback procedures are tested.

## Validation Notes

- Laravel files reviewed: 66 requested files, approximately 3,799 lines.
- Flutter files reviewed: all 106 files under `lib`, approximately 6,154 lines.
- All reviewed Laravel PHP files passed syntax linting.
- Laravel application behavior has not yet been validated through a full integration test suite.
- Existing Laravel tests are only default example tests.
- No project files were changed during the architecture reviews preceding this document.
