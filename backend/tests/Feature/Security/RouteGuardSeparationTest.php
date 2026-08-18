<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class RouteGuardSeparationTest extends SecurityTestCase
{
    public function test_shared_routes_are_declared_once_with_both_sanctum_guards(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach ([
            ['GET', 'api/medicines'],
            ['POST', 'api/sale/create'],
            ['GET', 'api/notifications'],
        ] as [$method, $uri]) {
            $matches = $routes->filter(fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true));

            $this->assertCount(1, $matches, $method.' '.$uri.' must be declared once.');
            $this->assertContains('auth:pharmacist,employee', $matches->first()->gatherMiddleware());
        }
    }

    public function test_routes_that_would_act_on_an_employee_without_consent_do_not_exist(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach (['api/employees/reject/{id}', 'api/employees/approve/{id}'] as $uri) {
            $this->assertCount(
                0,
                $routes->filter(fn (Route $route) => $route->uri() === $uri),
                $uri.' must not exist: hiring and rejecting are the applicant\'s call.',
            );
        }
    }

    public function test_normal_sanctum_routes_require_the_app_ability(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $exempt = ['api/logout', 'api/registration/status'];

        $normalAuthenticatedRoutes = $routes->filter(function (Route $route) use ($exempt) {
            $middleware = $route->gatherMiddleware();

            return str_starts_with($route->uri(), 'api/')
                && ! str_starts_with($route->uri(), 'api/admin/')
                && ! in_array($route->uri(), $exempt, true)
                && collect($middleware)->contains(
                    fn (string $item) => str_starts_with($item, 'auth:'),
                );
        });

        $this->assertNotEmpty($normalAuthenticatedRoutes);

        foreach ($normalAuthenticatedRoutes as $route) {
            $this->assertContains(
                'abilities:app',
                $route->gatherMiddleware(),
                implode('|', $route->methods()).' '.$route->uri().' must require the app ability.',
            );
        }

        $statusRoute = $routes->first(fn (Route $route) => $route->uri() === 'api/registration/status');
        $this->assertNotNull($statusRoute);
        $this->assertContains('abilities:registration-status', $statusRoute->gatherMiddleware());

        $logoutRoute = $routes->first(fn (Route $route) => $route->uri() === 'api/logout');
        $this->assertNotNull($logoutRoute);
        $this->assertNotContains('abilities:app', $logoutRoute->gatherMiddleware());
        $this->assertNotContains('abilities:registration-status', $logoutRoute->gatherMiddleware());
    }

    public function test_role_middleware_allows_matching_roles_and_rejects_non_matching_roles(): void
    {
        $pharmacist = $this->pharmacist('role');
        $request = Request::create('/role-test');
        $request->setUserResolver(fn () => $pharmacist);
        $middleware = new RoleMiddleware;

        $allowed = $middleware->handle($request, fn () => new Response('ok'), 'pharmacist');
        $denied = $middleware->handle($request, fn () => new Response('ok'), 'employee');

        $this->assertSame(200, $allowed->getStatusCode());
        $this->assertSame('ok', $allowed->getContent());
        $this->assertSame(403, $denied->getStatusCode());
    }
}
