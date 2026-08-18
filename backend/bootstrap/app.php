<?php

use App\Http\Middleware\ActivePharmacyContext;
use App\Http\Middleware\AdminRequestCorrelation;
use App\Http\Middleware\AuditAdminRequest;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminOriginAllowed;
use App\Http\Middleware\EnsurePharmacistHasApprovedPharmacy;
use App\Http\Middleware\EnsurePharmacistIsActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // There is no login *page* anywhere in this application: the mobile app
        // and the admin console both authenticate over the API. Laravel's
        // default guest redirect calls route('login') from inside the auth
        // middleware, before any exception handler can intervene, so an
        // unauthenticated request that did not ask for JSON died with a 500 —
        // and with APP_DEBUG on, a 500 is a full stack trace served to someone
        // holding no credentials. Returning null lets the handler answer 401.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->prepend(EnsureAdminOriginAllowed::class);
        $middleware->prepend(AdminRequestCorrelation::class);
        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
            'admin.audit' => AuditAdminRequest::class,
            'admin.correlation' => AdminRequestCorrelation::class,
            'admin.origin' => EnsureAdminOriginAllowed::class,
            'admin.super' => EnsureSuperAdmin::class,
            'active.pharmacy' => ActivePharmacyContext::class,
            'approved.pharmacist' => EnsurePharmacistHasApprovedPharmacy::class,
            'active.account' => EnsurePharmacistIsActive::class,
            'abilities' => CheckAbilities::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every /api/* response is JSON, whatever the caller asked for.
        //
        // Without this, an api request that does not send Accept:
        // application/json takes the browser branch of the handler, which tries
        // to redirect an unauthenticated visitor to route('login') — a route
        // this app does not have. The result was a 500 instead of a 401, and
        // with APP_DEBUG on a 500 is a full stack trace, reachable with no
        // credentials at all. It matters most for the recruitment document
        // routes, whose preview URLs are meant to be opened in a viewer.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'unauthenticated',
                ], 401);
            }

            return null;
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You are not authorized to perform this action.',
                    'code' => 'forbidden',
                ], 403);
            }

            return null;
        });
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The requested resource was not found.',
                    'code' => 'not_found',
                ], 404);
            }

            return null;
        });
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->is('api/admin/*')) {
                return response()->json([
                    'message' => 'The CSRF token is missing or expired.',
                    'code' => 'csrf_token_mismatch',
                ], 419);
            }

            return null;
        });
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/admin/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please try again later.',
                    'code' => 'too_many_attempts',
                ], 429, $exception->getHeaders());
            }

            return null;
        });
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($request->is('api/admin/*') && $exception->getStatusCode() === 419) {
                return response()->json([
                    'message' => 'The CSRF token is missing or expired.',
                    'code' => 'csrf_token_mismatch',
                ], 419);
            }

            return null;
        });
    })->create();
