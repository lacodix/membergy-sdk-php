<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

abstract class AbstractListContentRequest extends AbstractContentRequest
{
    /** @param array<string, mixed> $queryParameters */
    public function __construct(string $tenant, protected array $queryParameters = [])
    {
        parent::__construct($tenant);
    }

    /** @return array<string, mixed> */
    public function defaultQuery(): array
    {
        return $this->queryParameters;
    }
}
