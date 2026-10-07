<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use InvalidArgumentException;
use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;

final class DownloadRegistrationDocumentRequest extends AbstractRegistrationRequest
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $tenant, private readonly string $slug, private readonly int $requirementId)
    {
        RegistrationInput::tenant($tenant);
        RegistrationInput::slug($slug);
        if ($requirementId < 1) {
            throw new InvalidArgumentException('Invalid registration document requirement.');
        }
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/registration-offers/'.rawurlencode($this->slug).'/documents/'.$this->requirementId;
    }
}
