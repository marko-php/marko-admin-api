<?php

declare(strict_types=1);

namespace Marko\AdminApi\Controller;

use JsonException;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\Exceptions\AdminException;
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
readonly class SectionController
{
    public function __construct(
        private AdminSectionRegistryInterface $sectionRegistry,
        private AdminGuardResolver $adminGuard,
        private PermissionRegistryInterface $permissionRegistry,
    ) {}

    /**
     * @throws JsonException
     */
    #[Get('/admin/api/v1/sections')]
    public function index(): Response
    {
        $sections = $this->sectionRegistry->all();
        $user = $this->adminGuard->guard()->user();

        if ($user instanceof AdminUserInterface) {
            $sections = array_filter(
                $sections,
                fn (AdminSectionInterface $section): bool => $this->userCanAccessSection($user, $section),
            );
            $sections = array_values($sections);
        }

        $data = array_map(
            static fn (AdminSectionInterface $section): array => [
                'id' => $section->getId(),
                'label' => $section->getLabel(),
                'icon' => $section->getIcon(),
                'sort_order' => $section->getSortOrder(),
            ],
            $sections,
        );

        return ApiResponse::success(data: $data);
    }

    /**
     * @throws HttpException|JsonException
     */
    #[Get('/admin/api/v1/sections/{id}')]
    public function show(
        string $id,
    ): Response {
        try {
            $section = $this->sectionRegistry->get($id);
        } catch (AdminException $e) {
            throw $this->sectionNotFound($id, $e);
        }

        $user = $this->adminGuard->guard()->user();

        if ($user instanceof AdminUserInterface && !$this->userCanAccessSection($user, $section)) {
            throw $this->sectionNotFound($id);
        }

        $menuItems = array_map(
            static fn (MenuItemInterface $menuItem): array => [
                'id' => $menuItem->getId(),
                'label' => $menuItem->getLabel(),
                'url' => $menuItem->getUrl(),
                'icon' => $menuItem->getIcon(),
                'sort_order' => $menuItem->getSortOrder(),
                'permission' => $menuItem->getPermission(),
            ],
            $section->getMenuItems(),
        );

        return ApiResponse::success(data: [
            'id' => $section->getId(),
            'label' => $section->getLabel(),
            'icon' => $section->getIcon(),
            'sort_order' => $section->getSortOrder(),
            'menu_items' => $menuItems,
        ]);
    }

    /**
     * An inaccessible section answers like a missing one, so the API never
     * reveals which section IDs exist to a user who cannot see them.
     */
    private function sectionNotFound(
        string $id,
        ?AdminException $previous = null,
    ): HttpException {
        return new HttpException(
            statusCode: 404,
            message: "Section '$id' not found",
            previous: $previous,
        );
    }

    private function userCanAccessSection(
        AdminUserInterface $user,
        AdminSectionInterface $section,
    ): bool {
        $menuItems = $section->getMenuItems();

        if (empty($menuItems)) {
            return true;
        }

        return array_any(
            $menuItems,
            fn (MenuItemInterface $menuItem): bool => $this->userCanAccessMenuItem($user, $menuItem),
        );
    }

    private function userCanAccessMenuItem(
        AdminUserInterface $user,
        MenuItemInterface $menuItem,
    ): bool {
        $permission = $menuItem->getPermission();

        if ($permission === '' || $user->hasPermission($permission)) {
            return true;
        }

        return array_any(
            $user->getPermissionKeys(),
            fn (string $permissionKey): bool => $this->permissionRegistry->matches($permissionKey, $permission),
        );
    }
}
