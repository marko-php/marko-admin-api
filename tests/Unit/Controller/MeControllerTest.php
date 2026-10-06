<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Unit\Controller;

use Marko\AdminApi\Controller\MeController;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

function createMeTestAdminUser(
    array $roles = [],
    array $permissionKeys = [],
): AdminUser {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: $roles, permissionKeys: $permissionKeys);

    return $user;
}

it('returns current user info with roles and permissions on GET /admin/api/v1/me', function (): void {
    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $user = createMeTestAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.create', 'posts.edit', 'posts.view'],
    );
    $guard->setUser($user);

    $controller = new MeController(
        guard: $guard,
    );

    $response = $controller->me();
    $body = json_decode($response->body(), true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('data')
        ->and($body['data']['id'])->toBe(1)
        ->and($body['data']['email'])->toBe('admin@example.com')
        ->and($body['data']['name'])->toBe('Admin User')
        ->and($body['data']['roles'])->toHaveCount(1)
        ->and($body['data']['roles'][0]['id'])->toBe(1)
        ->and($body['data']['roles'][0]['name'])->toBe('Editor')
        ->and($body['data']['roles'][0]['slug'])->toBe('editor')
        ->and($body['data']['permissions'])->toBe(['posts.create', 'posts.edit', 'posts.view']);
});

function catchMeHttpException(
    callable $action,
): HttpException {
    try {
        $action();
    } catch (HttpException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected the controller to throw an HttpException.');
}

it('throws a 401 UnauthenticatedException when no user is authenticated', function (): void {
    $controller = new MeController(
        guard: new FakeGuard(name: 'admin-api', attemptResult: false),
    );

    $exception = catchMeHttpException(fn () => $controller->me());

    expect($exception)->toBeInstanceOf(UnauthenticatedException::class)
        ->and($exception->getStatusCode())->toBe(401)
        ->and($exception->getResponseData())->toBe(['message' => 'Unauthorized.']);
});

it('throws a 403 HttpException when the authenticated user is not an admin user', function (): void {
    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $guard->setUser(new FakeAuthenticatable(id: 7));

    $controller = new MeController(
        guard: $guard,
    );

    $exception = catchMeHttpException(fn () => $controller->me());

    expect($exception->getStatusCode())->toBe(403)
        ->and($exception->getResponseData())->toBe(['message' => 'Forbidden.']);
});

it('uses the ApiResponse envelope for the successful response', function (): void {
    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $guard->setUser(createMeTestAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.view'],
    ));

    $controller = new MeController(
        guard: $guard,
    );

    $body = json_decode($controller->me()->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta');
});

it('applies AdminAuthMiddleware to all routes', function (): void {
    $reflection = new ReflectionClass(MeController::class);

    // Check class-level Middleware attribute
    $middlewareAttributes = $reflection->getAttributes(Middleware::class);

    // Verify route attribute exists on the "me" method
    $meRouteAttributes = (new ReflectionMethod(MeController::class, 'me'))->getAttributes(Get::class);

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(AdminAuthMiddleware::class)
        ->and($meRouteAttributes)->toHaveCount(1)
        ->and($meRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/me');
});
