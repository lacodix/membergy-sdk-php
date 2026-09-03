<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class SubscribeNewsletterRequest extends AbstractContentRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param list<string> $categories */
    public function __construct(
        string $tenant,
        private array $categories,
        private string $email,
    ) {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('newsletter/subscribe');
    }

    /** @return array<string, mixed> */
    public function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'categories' => $this->categories,
        ];
    }
}
