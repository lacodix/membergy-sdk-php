<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Support;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Saloon\Http\Response;

final class Payload
{
    /** @return array<string, mixed> */
    public static function fromResponse(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload) || array_is_list($payload)) {
            throw new HydrationException('Invalid API payload: response must be an object.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function data(array $payload): array
    {
        return Data::object($payload, 'data');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function collection(array $payload): array
    {
        return Data::objectList($payload, 'data');
    }
}
