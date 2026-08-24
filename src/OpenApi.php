<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api;

/** The shipped description of this surface. `Unit\OpenApiParityTest` is what keeps it true. */
final class OpenApi
{
    public static function path(): string
    {
        return __DIR__.'/../resources/openapi/openapi.json';
    }

    /** @return array<string, mixed> */
    public static function document(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = (array) json_decode((string) file_get_contents(self::path()), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
