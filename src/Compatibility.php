<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk;

use Lacodix\MembergySdk\Exceptions\UnsupportedApiVersionException;

final class Compatibility
{
    public const API_VERSION = 'v1';

    public const CMS_CONTRACT = 'cms-v1';

    public const FORMS_CONTRACT = 'forms-v1';

    public const AUTH_CONTRACT = 'auth-v1';

    public const SELF_SERVICE_CONTRACT = 'self-service-v1';

    public const BLOCK_DOCUMENT_SCHEMA = 1;

    public static function assertApiVersion(string $apiVersion): void
    {
        if ($apiVersion !== self::API_VERSION) {
            throw new UnsupportedApiVersionException(
                "Membergy SDK supports API version '".self::API_VERSION."', '{$apiVersion}' given.",
            );
        }
    }

    /** @return array<string, string|int> */
    public static function matrix(): array
    {
        return [
            'api' => self::API_VERSION,
            'cms' => self::CMS_CONTRACT,
            'forms' => self::FORMS_CONTRACT,
            'auth' => self::AUTH_CONTRACT,
            'self_service' => self::SELF_SERVICE_CONTRACT,
            'block_document_schema' => self::BLOCK_DOCUMENT_SCHEMA,
        ];
    }
}
