<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Lacodix\MembergySdk\Requests\AbstractTenantRequest;

abstract class AbstractSelfServiceRequest extends AbstractTenantRequest
{
    public const MEDIA_TYPE = 'application/vnd.membergy.self-service-v1+json';

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => self::MEDIA_TYPE];
    }

    protected function selfServiceEndpoint(string $path): string
    {
        return $this->tenantEndpoint('me/'.ltrim($path, '/'));
    }
}
