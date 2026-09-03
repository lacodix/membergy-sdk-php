<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class UpdateNewsletterSubscriptionsRequest extends AbstractSelfServiceRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    /** @param list<string> $categories */
    public function __construct(string $tenant, private array $categories)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('newsletter');
    }

    /** @return array{categories: list<string>} */
    public function defaultBody(): array
    {
        return ['categories' => $this->categories];
    }
}
