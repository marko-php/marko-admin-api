<?php

declare(strict_types=1);

use Marko\AdminApi\ApiResponse;
use Marko\Routing\Http\Response;

it('creates ApiResponse helper with success method returning data and meta', function (): void {
    $data = ['id' => 1, 'name' => 'Test'];
    $meta = ['version' => '1.0'];

    $response = ApiResponse::success(
        data: $data,
        meta: $meta,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toBe($data)
        ->and($body['meta'])->toBe($meta);
});

it('creates ApiResponse helper with created method returning 201 with data and meta', function (): void {
    $response = ApiResponse::created(
        data: ['id' => 5],
        meta: ['version' => '1.0'],
    );

    expect($response->statusCode())->toBe(201)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and(json_decode($response->body(), true))->toBe([
            'data' => ['id' => 5],
            'meta' => ['version' => '1.0'],
        ]);
});

it('creates ApiResponse helper with paginated method including pagination meta', function (): void {
    $items = [
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second'],
    ];

    $response = ApiResponse::paginated(
        data: $items,
        page: 2,
        perPage: 10,
        total: 25,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toBe($items)
        ->and($body['meta']['page'])->toBe(2)
        ->and($body['meta']['per_page'])->toBe(10)
        ->and($body['meta']['total'])->toBe(25)
        ->and($body['meta']['total_pages'])->toBe(3);
});

it('exposes only the success envelope helpers, leaving errors to HttpException', function (): void {
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        new ReflectionClass(ApiResponse::class)->getMethods(ReflectionMethod::IS_PUBLIC),
    );
    sort($methods);

    expect($methods)->toBe(['created', 'paginated', 'success']);
});
