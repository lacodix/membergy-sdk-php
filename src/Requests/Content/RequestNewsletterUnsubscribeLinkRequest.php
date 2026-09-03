<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class RequestNewsletterUnsubscribeLinkRequest extends AbstractContentRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(string $tenant, private string $email)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('newsletter/unsubscribe');
    }

    /** @return array{email: string} */
    public function defaultBody(): array
    {
        return ['email' => $this->email];
    }
}
