<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Feature;

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\AdminApi\ApiResponse;
use Marko\AdminApi\Controller\MeController;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminApi\Tests\Fixtures\FixedAdminGuardResolver;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use ReflectionClass;

/**
 * A custom admin API endpoint gated on a permission, standing in for an app's
 * own controller: none of admin-api's routes carry #[RequiresPermission].
 */
class ErrorShapeOrderApiController
{
    /** @noinspection PhpUnused - Invoked via the router */
    #[RequiresPermission('orders.view')]
    public function index(): Response
    {
        return ApiResponse::success(data: []);
    }
}

readonly class ErrorShapeAdminConfig implements AdminConfigInterface
{
    public function getRoutePrefix(): string
    {
        return '/admin';
    }

    public function getName(): string
    {
        return 'Admin';
    }
}

function adminApiAdminUser(): AdminUser
{
    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor';
    $role->slug = 'editor';

    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: [$role], permissionKeys: ['posts.view']);

    return $user;
}

/**
 * Build a real Router serving the admin API routes behind AdminAuthMiddleware,
 * as #[Middleware(AdminAuthMiddleware::class)] attaches it in production.
 */
function adminApiRouter(
    ?AuthenticatableInterface $user,
): Router {
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    if ($user !== null) {
        $guard->setUser($user);
    }

    $container = new Container();
    $container->instance(AdminGuardResolver::class, new FixedAdminGuardResolver($guard));
    $container->instance(AdminConfigInterface::class, new ErrorShapeAdminConfig());
    $container->instance(AdminSectionRegistryInterface::class, new AdminSectionRegistry($container));

    // The permission registry comes from admin-auth's own module.php, as in production.
    $module = require dirname((string) new ReflectionClass(PermissionRegistry::class)->getFileName(), 2)
        . '/module.php';
    new BindingRegistry($container)->registerModule(new ModuleManifest(
        name: 'marko/admin-auth',
        version: '1.0.0',
        bindings: $module['bindings'],
        singletons: $module['singletons'],
    ));

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin/api/v1/sections/{id}',
        controller: SectionController::class,
        action: 'show',
        middleware: [AdminAuthMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin/api/v1/me',
        controller: MeController::class,
        action: 'me',
        middleware: [AdminAuthMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin/api/v1/orders',
        controller: ErrorShapeOrderApiController::class,
        action: 'index',
        middleware: [AdminAuthMiddleware::class],
    ));

    return new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );
}

function adminApiJsonRequest(
    string $path,
): Request {
    return new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        'HTTP_ACCEPT' => 'application/json',
    ]);
}

it('renders an unknown section as a JSON 404 with only a message', function (): void {
    $response = adminApiRouter(adminApiAdminUser())->handle(adminApiJsonRequest('/admin/api/v1/sections/nope'));

    expect($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toContain('application/json')
        ->and(json_decode($response->body(), true))->toBe(['message' => "Section 'nope' not found"]);
});

it('renders a guest JSON request as a 401 with only a message', function (): void {
    $response = adminApiRouter(null)->handle(adminApiJsonRequest('/admin/api/v1/sections/nope'));

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toContain('application/json')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Unauthorized.']);
});

it('renders a missing permission as a JSON 403 with only a message', function (): void {
    $response = adminApiRouter(adminApiAdminUser())->handle(adminApiJsonRequest('/admin/api/v1/orders'));

    expect($response->statusCode())->toBe(403)
        ->and($response->headers()['Content-Type'])->toContain('application/json')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden.']);
});

it('renders a non-admin user on the me endpoint as a JSON 403 with only a message', function (): void {
    $response = adminApiRouter(new FakeAuthenticatable(id: 7))->handle(adminApiJsonRequest('/admin/api/v1/me'));

    expect($response->statusCode())->toBe(403)
        ->and($response->headers()['Content-Type'])->toContain('application/json')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden.']);
});
