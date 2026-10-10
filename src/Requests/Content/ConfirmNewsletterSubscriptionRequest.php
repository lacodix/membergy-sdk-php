<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class ConfirmNewsletterSubscriptionRequest extends AbstractContentRequest implements HasBody
{
    use HasJsonBody;
    use RedactsSensitivePayload;

    protected Method $method = Method::POST;

    public function __construct(string $tenant, #[SensitiveParameter] private string $token)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('newsletter/confirm');
    }

    /** @return array{token: string} */
    public function defaultBody(): array
    {
        return ['token' => $this->token];
    }
}
