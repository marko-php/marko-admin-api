<?php

declare(strict_types=1);

namespace Marko\AdminApi;

use JsonException;
use Marko\Routing\Http\Response;

/**
 * Success envelopes for admin API controllers: `{data, meta}`.
 *
 * Errors are not built here. Throw an HttpException (e.g.
 * HttpException::notFound()) and the routing pipeline renders it through
 * ExceptionRenderer as `{"message": ...}`, the same shape the admin auth
 * middleware, the router and validation produce.
 */
class ApiResponse
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException
     */
    public static function success(
        array $data = [],
        array $meta = [],
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => $meta,
            ],
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException
     */
    public static function created(
        array $data = [],
        array $meta = [],
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => $meta,
            ],
            statusCode: 201,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @throws JsonException
     */
    public static function paginated(
        array $data,
        int $page,
        int $perPage,
        int $total,
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
        );
    }
}
