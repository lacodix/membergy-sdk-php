<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class RequestPasswordResetLinkRequest extends AbstractAuthRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private string $email) {}

    public function resolveEndpoint(): string
    {
        return '/auth/password/reset-link';
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return ['email' => $this->email];
    }
}
