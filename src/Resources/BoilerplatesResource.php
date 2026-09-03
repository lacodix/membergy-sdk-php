<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\Boilerplate;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListBoilerplatesRequest;
use Lacodix\MembergySdk\Requests\Content\ShowBoilerplateRequest;
use Lacodix\MembergySdk\Support\Payload;

final class BoilerplatesResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<Boilerplate> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListBoilerplatesRequest($this->connector->tenant(), $this->query),
            Boilerplate::fromArray(...),
        );
    }

    public function find(string $slug): Boilerplate
    {
        try {
            $response = $this->send(new ShowBoilerplateRequest($this->connector->tenant(), $slug));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Boilerplate with slug '{$slug}' not found.", previous: $exception);
        }

        return Boilerplate::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
