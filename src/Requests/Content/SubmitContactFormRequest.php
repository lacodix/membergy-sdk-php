<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class SubmitContactFormRequest extends AbstractContactFormRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        string $tenant,
        private readonly string $uuid,
        #[SensitiveParameter] private readonly ContactFormSubmission $submission,
    ) {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('forms/'.rawurlencode($this->uuid).'/submissions');
    }

    /** @return array<string, string> */
    protected function defaultBody(): array
    {
        return $this->submission->toArray();
    }
}
