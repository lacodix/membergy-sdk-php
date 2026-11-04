<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;

final class ShowContactFormRequest extends AbstractContactFormRequest
{
    protected Method $method = Method::GET;

    public function __construct(string $tenant, private readonly string $uuid)
    {
        parent::__construct($tenant);
    }

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof RequestException && $exception->getResponse()->status() === 429) {
            return false;
        }

        return parent::handleRetry($exception, $request);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('forms/'.rawurlencode($this->uuid));
    }
}
