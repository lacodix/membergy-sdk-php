<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests;

use Saloon\Http\Request;

abstract class AbstractTenantRequest extends Request
{
    public function __construct(protected string $tenant) {}

    protected function tenantEndpoint(string $path): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/'.ltrim($path, '/');
    }
}
