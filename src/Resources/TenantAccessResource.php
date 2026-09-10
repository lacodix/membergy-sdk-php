<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\TenantAccess;
use Lacodix\MembergySdk\Requests\Me\ShowTenantAccessRequest;
use Lacodix\MembergySdk\Support\Payload;

final class TenantAccessResource extends AbstractResource
{
    public function get(): TenantAccess
    {
        $payload = Payload::fromResponse($this->send(
            new ShowTenantAccessRequest($this->connector->tenant()),
        ));

        return TenantAccess::fromArray(Payload::data($payload));
    }
}
