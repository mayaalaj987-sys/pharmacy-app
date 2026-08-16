# Gate 1C administrator backend contract

## Architecture and deployment topology

Administrator identities are stored separately from pharmacists and employees and authenticate only through the `admin` session guard. Mobile application identities continue to use their existing Sanctum bearer-token guards. Administrator responses never contain a bearer token or another credential intended for browser storage.

The administrator API is served under `/api/admin` through Laravel's stateful `web` middleware. The browser must send cookies and obtain CSRF protection before a state-changing request. The default cookie namespace is dedicated to the administrator browser session, is HttpOnly, uses SameSite `lax`, and becomes Secure by default when `APP_ENV=production` unless deployment configuration explicitly supplies the equivalent secure setting.

For local Gate 1D integration, set `ADMIN_ALLOWED_ORIGINS` to the exact local Vite origin, keep `SESSION_SECURE_COOKIE=false`, and use the same host label for frontend and backend where possible (do not mix `localhost` and `127.0.0.1`). Requests use `credentials: "include"`. Obtain CSRF state from `GET /api/admin/csrf`, then read the `XSRF-TOKEN` cookie and send its decoded value as `X-XSRF-TOKEN` for state-changing calls.

For production, `ADMIN_ALLOWED_ORIGINS` must contain only explicit HTTPS origins. Wildcards, non-HTTPS origins, and an empty list fail closed. Configure `SESSION_SECURE_COOKIE=true`, an appropriate same-site `SESSION_DOMAIN`, `SESSION_SAME_SITE=lax`, HTTPS end to end, trusted proxy handling, and a persistent server-side session store. Credentialed CORS is enabled only for the explicit allowlist; wildcard origins are never permitted.

## Common response and error behavior

Successful state changes return `message`, a stable machine-readable `code`, and, where applicable, `data`. Validation failures return HTTP 422 with `code=validation_failed` and an `errors` object. Other stable errors include `unauthenticated` (401), `invalid_credentials` (401), `session_expired` (401), `account_disabled` (403), `forbidden` (403), `origin_not_allowed` (403), `csrf_token_mismatch` (419), `review_version_conflict` (409), `review_already_finalized` (409), `legal_documents_required` (422), `document_unavailable` (404), and `too_many_attempts` (429). Production must run with `APP_DEBUG=false` so stack traces and framework internals are not returned.

Every administrator response includes an `X-Request-ID`. A valid UUID supplied as `X-Request-ID` is preserved; otherwise the backend creates one.

## Authentication and session contracts

| Method | Path | Request | Response data |
| --- | --- | --- | --- |
| GET | `/api/admin/csrf` | none | `code=csrf_ready`; establishes session/CSRF cookies |
| POST | `/api/admin/login` | `email`, `password` | `admin`, `navigation`; no token |
| GET | `/api/admin/session` | authenticated cookie | current `admin`, `navigation` |
| POST | `/api/admin/logout` | authenticated cookie + CSRF | invalidates the session and regenerates CSRF state |

`admin` contains the stable public administrator ID, name, normalized email, role, active state, and lifecycle timestamps. `navigation.review_pharmacies` and `navigation.manage_admins` are server-derived capabilities for UI navigation only; Laravel policies remain authoritative.

Login responses are deliberately generic for unknown emails, wrong passwords, and disabled identities. Login is throttled by normalized email and client IP. Login regenerates the session ID. Password reset, role change, disable, and reactivate operations increment an authentication version so existing sessions fail closed.

## Pharmacy review contracts

| Method | Path | Role | Request |
| --- | --- | --- | --- |
| GET | `/api/admin/review/applications` | reviewer or super admin | optional `per_page` (1-100) |
| GET | `/api/admin/review/applications/{pharmacy}` | reviewer or super admin | none |
| POST | `/api/admin/review/applications/{pharmacy}/approve` | reviewer or super admin | `review_version` |
| POST | `/api/admin/review/applications/{pharmacy}/reject` | reviewer or super admin | `review_version`, `reason` (5-500 normalized characters) |
| GET | `/api/admin/review/applications/{pharmacy}/documents/{document}/preview` | reviewer or super admin | none |
| GET | `/api/admin/review/applications/{pharmacy}/documents/{document}/download` | reviewer or super admin | none |

The list envelope is `{data: [...], meta: {current_page,last_page,per_page,total}}`. Each application returns its ID, name, address, status, submitted timestamp, `review_version`, safe owner contact fields, safe document metadata, and review timestamp. Document metadata contains only a stable public ID, document type, review status, MIME category, size, submitted/review timestamps, and authorized preview/download URLs. It never contains a disk name, raw path, original filename, content hash, legacy locator, or document contents.

The client must submit the returned `review_version`. A duplicate request for the already-applied decision returns `code=review_already_applied` without rewriting the original reviewer. An opposite or already-finalized decision returns HTTP 409. Approval requires both verified pending legal document types and validates private stored content inside the locked transaction. Rejection requires a bounded normalized reason. The backend derives the actor from the active administrator session; no actor field is accepted.

Preview/download responses stream verified private bytes through Laravel with a synthetic filename, verified MIME, `nosniff`, sandbox CSP, private/no-store caching, and no referrer. There is no administrator route for employee CV or experience documents.

## Administrator management contracts

Only an active `super_admin` may use these routes:

| Method | Path | Request |
| --- | --- | --- |
| GET | `/api/admin/admins` | none |
| POST | `/api/admin/admins` | `name`, `email`, `password`, `password_confirmation`, supported `role` |
| PATCH | `/api/admin/admins/{admin}/role` | supported `role` |
| POST | `/api/admin/admins/{admin}/disable` | none |
| POST | `/api/admin/admins/{admin}/reactivate` | none |

The service locks affected administrator rows and refuses to disable or demote the last active super administrator. Privileged state fields are force-assigned only by the service and cannot be escalated through request mass assignment.

## Controlled CLI provisioning and recovery

- `php artisan admin:provision-super --email=... --name=...` provisions only the first super administrator and is idempotent for the same active identity.
- `php artisan admin:create --actor=... --email=... --name=... --role=...` creates later individual accounts after interactive super-admin authorization.
- `php artisan admin:reset-password {email} --actor=...` resets a password and invalidates existing sessions.
- `php artisan admin:set-status {email} {active|disabled} --actor=...` disables or reactivates an account with last-super-admin protection.

Passwords and authorizing passwords are entered through hidden interactive prompts. There is no password command option, default password, seeder credential, or shared identity. Production commands require a separate confirmation unless the operator intentionally supplies `--force` in controlled automation. The initial bootstrap audit is attributed to the newly provisioned individual identity; later commands require an existing active super administrator identity.

## Audit design and operational limits

Privileged requests receive a generic route-level audit event, and high-value operations receive a specific domain event. Events include the individual admin ID when one can exist, role snapshot, normalized action, target, outcome, bounded reason, correlation ID, timestamp, encrypted IP address, bounded user agent, and allowlisted before/after state. Authentication failures never store attempted passwords or authorization material. A sanitizer removes password, secret, token, cookie, session, storage, path, filename, hash, and content-shaped fields.

The Eloquent model rejects updates/deletes, and supported SQLite, MySQL/MariaDB, and PostgreSQL migrations install database triggers rejecting updates/deletes. This is append-only protection, not an absolute guarantee against database owners or infrastructure administrators, who can disable triggers or alter data. Production must use a least-privilege application database account without DDL/trigger-bypass rights after deployment, protect database backups, monitor privileged database access, and define an approved archival/destruction procedure. IP and user-agent collection are configurable; the documented operational review/archival horizon is 90 days by default. Retention beyond that point requires a privileged database-controlled process because normal application deletion is intentionally unavailable.

## External prototype compatibility notes for Gate 1D

The read-only external UI is currently a mock prototype. Its in-memory login, preset credential text, timeout-based loading, forgot-password success, mock dashboard statistics, bulk approval control, `Under Review` state, document display names, branch/rating data, tickets, reports, notifications, and claims that rejection email was sent have no Gate 1C canonical backend equivalent. Gate 1D must remove preset credentials and mock mutations, must not place session credentials in localStorage, and must not invent production fields to preserve those screens.

Gate 1D prerequisites are: configure the exact local origin/cookie host topology; implement the CSRF bootstrap and credentialed request client; hydrate auth from `/api/admin/session`; map navigation from server capabilities; map only the documented review fields; submit `review_version` on decisions; handle all stable error codes; use authenticated preview/download URLs; hide super-admin navigation from reviewers while retaining server enforcement; remove or explicitly defer unsupported mock-only screens; and provision real individual accounts only through a separately approved operational step.
