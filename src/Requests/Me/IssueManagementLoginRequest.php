<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Enums\Method;

final class IssueManagementLoginRequest extends AbstractSelfServiceRequest
{
    use RedactsSensitivePayload;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('management-login');
    }
}
