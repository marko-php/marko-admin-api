<?php

declare(strict_types=1);

namespace Marko\AdminApi\Controller;

use JsonException;
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
readonly class MeController
{
    public function __construct(
        private AdminGuardResolver $adminGuard,
    ) {}

    /**
     * AdminAuthMiddleware has already turned guests away; a user the guard
     * authenticated that is not an admin user gets the same 403 the
     * middleware sends for one on a permission-gated route.
     *
     * @throws HttpException|JsonException|UnauthenticatedException
     */
    #[Get('/admin/api/v1/me')]
    public function me(): Response
    {
        $guard = $this->adminGuard->guard();
        $user = $guard->user();

        if ($user === null) {
            throw UnauthenticatedException::forGuard($guard);
        }

        if (!$user instanceof AdminUserInterface) {
            throw new HttpException(
                statusCode: 403,
                message: 'Forbidden.',
                context: "The authenticated user on guard '{$guard->getName()}' is not an admin user.",
            );
        }

        $roles = array_map(
            static fn ($role): array => [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
            ],
            $user->getRoles(),
        );

        return ApiResponse::success(data: [
            'id' => $user->getAuthIdentifier(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $roles,
            'permissions' => $user->getPermissionKeys(),
        ]);
    }
}
