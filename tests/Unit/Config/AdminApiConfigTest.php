<?php

declare(strict_types=1);

use Marko\AdminApi\Config\AdminApiConfig;
use Marko\AdminApi\Config\AdminApiConfigInterface;
use Marko\AdminApi\Controller\MeController;
use Marko\AdminApi\Controller\SectionController;
use Marko\Routing\Attributes\Get;
use Marko\Testing\Fake\FakeConfigRepository;

it('creates AdminApiConfig with version and rate limit settings', function (): void {
    $config = new AdminApiConfig(new FakeConfigRepository([
        'admin-api.version' => 'v1',
        'admin-api.rate_limit' => 60,
        'admin-api.guard' => 'admin-api',
    ]));

    expect($config)->toBeInstanceOf(AdminApiConfigInterface::class)
        ->and($config->getVersion())->toBe('v1')
        ->and($config->getRateLimit())->toBe(60)
        ->and($config->getGuardName())->toBe('admin-api');
});

it('registers API routes under /admin/api/v1 prefix', function (): void {
    // Verify MeController routes
    $meRouteAttributes = (new ReflectionMethod(MeController::class, 'me'))->getAttributes(Get::class);

    // Verify SectionController routes
    $indexRouteAttributes = (new ReflectionMethod(SectionController::class, 'index'))->getAttributes(Get::class);
    $showRouteAttributes = (new ReflectionMethod(SectionController::class, 'show'))->getAttributes(Get::class);

    expect($meRouteAttributes)->toHaveCount(1)
        ->and($meRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($meRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/me')
        ->and($indexRouteAttributes)->toHaveCount(1)
        ->and($indexRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($indexRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections')
        ->and($showRouteAttributes)->toHaveCount(1)
        ->and($showRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($showRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections/{id}');
});

it('does not conflict with admin-panel routes', function (): void {
    // Admin API routes use /admin/api/v1/ prefix
    $apiRoutes = [];

    $meMethod = new ReflectionMethod(MeController::class, 'me');
    $meRouteAttrs = $meMethod->getAttributes(Get::class);
    $apiRoutes[] = $meRouteAttrs[0]->newInstance()->path;

    $indexMethod = new ReflectionMethod(SectionController::class, 'index');
    $indexRouteAttrs = $indexMethod->getAttributes(Get::class);
    $apiRoutes[] = $indexRouteAttrs[0]->newInstance()->path;

    $showMethod = new ReflectionMethod(SectionController::class, 'show');
    $showRouteAttrs = $showMethod->getAttributes(Get::class);
    $apiRoutes[] = $showRouteAttrs[0]->newInstance()->path;

    // All API routes must contain /api/v1/ which distinguishes them from panel routes
    foreach ($apiRoutes as $route) {
        expect($route)->toContain('/api/v1/');
    }

    // Admin panel routes are at /admin, /admin/login, /admin/logout
    // These should never match API route patterns
    $panelRoutes = ['/admin', '/admin/login', '/admin/logout'];

    foreach ($apiRoutes as $apiRoute) {
        foreach ($panelRoutes as $panelRoute) {
            expect($apiRoute)->not->toBe($panelRoute);
        }
    }
});

it('has valid config/admin-api.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin-api.php';
    $configData = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($configData)->toBeArray()
        ->and($configData)->toHaveKey('version')
        ->and($configData)->toHaveKey('rate_limit')
        ->and($configData)->toHaveKey('guard')
        ->and($configData['version'])->toBe('v1')
        ->and($configData['rate_limit'])->toBe(60)
        ->and($configData['guard'])->toBe('admin-api');
});

it('has module.php with AdminApiConfig binding', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminApiConfigInterface::class)
        ->and($module['bindings'][AdminApiConfigInterface::class])
            ->toBe(AdminApiConfig::class);
});
