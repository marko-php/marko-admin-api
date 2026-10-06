<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Unit\Controller;

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Module\ModuleManifest;
use Marko\Testing\Fake\FakeGuard;
use ReflectionClass;
use ReflectionProperty;

readonly class SectionWiringAdminConfig implements AdminConfigInterface
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

/**
 * Build a container with the services other packages provide in a real
 * application, optionally wired with marko/admin-auth's module.php.
 */
function sectionControllerContainer(
    bool $withAdminAuthModule,
): Container {
    $container = new Container();
    $container->instance(GuardInterface::class, new FakeGuard(name: 'admin'));
    $container->instance(AdminConfigInterface::class, new SectionWiringAdminConfig());
    $container->instance(AdminSectionRegistryInterface::class, new AdminSectionRegistry());

    if ($withAdminAuthModule) {
        $module = require dirname((string) new ReflectionClass(PermissionRegistry::class)->getFileName(), 2)
            . '/module.php';

        new BindingRegistry($container)->registerModule(new ModuleManifest(
            name: 'marko/admin-auth',
            version: '1.0.0',
            bindings: $module['bindings'],
            singletons: $module['singletons'],
        ));
    }

    return $container;
}

it(
    'builds SectionController from the admin-auth module bindings without an app-level registry binding',
    function (): void {
        expect(sectionControllerContainer(withAdminAuthModule: true)->get(SectionController::class))
            ->toBeInstanceOf(SectionController::class);
    },
);

it('injects the same PermissionRegistry instance into SectionController and AdminAuthMiddleware', function (): void {
    $container = sectionControllerContainer(withAdminAuthModule: true);
    $registry = $container->get(PermissionRegistryInterface::class);

    $controllerRegistry = new ReflectionProperty(SectionController::class, 'permissionRegistry')
        ->getValue($container->get(SectionController::class));
    $middlewareRegistry = new ReflectionProperty(AdminAuthMiddleware::class, 'permissionRegistry')
        ->getValue($container->get(AdminAuthMiddleware::class));

    expect($controllerRegistry)->toBe($registry)
        ->and($middlewareRegistry)->toBe($registry);
});

it('fails to build SectionController when no module binds PermissionRegistryInterface', function (): void {
    expect(fn () => sectionControllerContainer(withAdminAuthModule: false)->get(SectionController::class))
        ->toThrow(BindingException::class);
});
