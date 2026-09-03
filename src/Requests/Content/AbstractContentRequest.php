<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Lacodix\MembergySdk\Requests\AbstractTenantRequest;

abstract class AbstractContentRequest extends AbstractTenantRequest
{
    protected function contentEndpoint(string $path): string
    {
        return $this->tenantEndpoint('content/'.ltrim($path, '/'));
    }
}
