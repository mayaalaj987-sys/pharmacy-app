# admin-web provenance

## Source

- Imported from a local downloaded snapshot at `C:\Users\ASUSD\Desktop\admin-pharmacy-review\admin-pharmacy-main` on 2026-08-16.
- No GitHub/remote repository URL was available in the local snapshot (no `.git` directory, no remote metadata file). The exact upstream source repository could not be determined from the provided copy; it should be requested from the teammate if a canonical URL is needed for the record.
- Authorization: the teammate authorized reuse of this admin frontend for Gate 1D integration (per the Gate 1D task brief provided to this session). No license file was present in the snapshot; none is asserted here.
- Import date: 2026-08-16.
- The external source directory was read-only throughout this work and was not modified, formatted, or had dependencies installed into it. Verified unchanged before and after import: 92 files, 1,166,073 bytes.

## Snapshot limitations

The imported snapshot was a mock-only prototype (TanStack Start SSR app scaffolded via the "Lovable" AI app-builder platform) with:

- In-memory, unauthenticated login (any syntactically valid email + 6+ character password succeeded).
- No backend calls anywhere — all data came from `src/data/mock.ts` and all mutations were local `useState` updates.
- No localStorage/sessionStorage usage (confirmed by full-source grep).
- A build dependency on `@lovable.dev/vite-tanstack-config`, a proprietary SaaS-platform Vite plugin wrapper (dev-only "component tagger", build-time "error logger" plugins, sandbox host detection) — not installed or reused here (see "Major Gate 1D modifications" below).

## Major Gate 1D modifications

- Converted from a TanStack Start SSR app to a plain Vite + React SPA. Removed: `@lovable.dev/vite-tanstack-config`, `@tanstack/react-start`, `nitro`, `src/server.ts`, `src/start.ts`, `src/lib/error-capture.ts`, `src/lib/error-page.ts`, `src/lib/lovable-error-reporting.ts`. Kept `@tanstack/react-router` (client-side only) for real, guarded routes.
- Replaced the entirely mock/unauthenticated login with the Gate 1C browser-session contract: `GET /api/admin/csrf` → `POST /api/admin/login` (credentialed) → `GET /api/admin/session` (startup restore) → `POST /api/admin/logout` (CSRF-protected). See `src/lib/adminApi.ts`, `src/lib/sessionApi.ts`, `src/context/AuthContext.tsx`.
- Removed the fake "reset link sent" forgot-password flow entirely (no canonical backend contract exists; per the Gate 1C report this was to be removed or honestly deferred, not simulated).
- Removed screens/data with no canonical Gate 1C backend contract: Dashboard (stats/charts/activity feed), Reports & Analytics, Support Tickets, the standalone Pharmacies/branches/ratings view, the notification bell, and the command palette (all were tied to `src/data/mock.ts`, which was deleted).
- Renamed/rebuilt "Verification" into a real pharmacy-review workflow (`src/components/review/`) backed by `/api/admin/review/applications*`, including `review_version` submission, 409 conflict/idempotent handling, and authenticated document preview (Blob + revoked object URL) and download.
- Added a super-admin-only Administrator Management screen (`src/components/admins/`) backed by `/api/admin/admins*`, absent from the prototype entirely.
- Added real, guarded routes (`/login`, `/review`, `/admins`) replacing the prototype's single-view, client-state-only navigation, so unauthorized direct navigation fails safely.
- Added a test setup (Vitest + Testing Library + MSW) — the prototype had no test tooling at all.
- One stray pasted-chat artifact (`[06/08/26 09:03 م] Aya Abd:`) found inline in the prototype's `AuthView.tsx` was dropped during the rewrite; it was inert, not an instruction, and is noted here only for transparency.

## Preserved as-is (visual design / components)

`src/components/ui/*` (shadcn/Radix primitives), `src/components/ui-custom/Toast.tsx` and `StatusBadge.tsx`, `src/hooks/use-mobile.tsx`, `src/lib/utils.ts`, `src/styles.css` (Tailwind v4 theme tokens), and the four background/logo image assets were copied verbatim. The auth screen's dark-emerald visual treatment, the sidebar/topbar layout, and the review list + slide-over drawer pattern were preserved from the prototype's design.

## Dependency/lockfile note

The prototype shipped two lockfiles (`bun.lock` and `package-lock.json`) and no `packageManager` field. Neither was carried forward as-is: the dependency set changed materially (SSR/Lovable tooling removed; Vitest/Testing Library/MSW added for Phase 8 test coverage), so a fresh, deterministic `package-lock.json` is generated for `admin-web` via `npm install` rather than attempting to reconcile either prototype lockfile against a changed dependency tree.
