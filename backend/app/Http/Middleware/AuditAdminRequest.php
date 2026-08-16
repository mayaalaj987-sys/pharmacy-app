<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\AdminAuditLogger;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditAdminRequest
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        $action = $request->route()?->getName() ?: 'admin.request';

        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $this->audit->record(
                $request,
                $admin instanceof Admin ? $admin : null,
                $action,
                $status < 400 ? 'success' : ($status < 500 ? 'denied' : 'failure'),
                reason: $status < 400 ? null : 'http_'.$status,
            );

            return $response;
        } catch (AuthorizationException $exception) {
            $this->audit->record($request, $admin instanceof Admin ? $admin : null, $action, 'denied', reason: 'forbidden');
            throw $exception;
        } catch (Throwable $exception) {
            $this->audit->record($request, $admin instanceof Admin ? $admin : null, $action, 'failure', reason: 'unhandled_failure');
            throw $exception;
        }
    }
}
