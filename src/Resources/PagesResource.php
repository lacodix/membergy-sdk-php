<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\Page;
use Lacodix\MembergySdk\DataObjects\PageSummary;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListPagesRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Support\Payload;

final class PagesResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<PageSummary> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListPagesRequest($this->connector->tenant(), $this->query),
            PageSummary::fromArray(...),
        );
    }

    public function find(string $slug, bool $includeDynamic = false): Page
    {
        try {
            $response = $this->send(new ShowPageRequest(
                $this->connector->tenant(),
                $slug,
                $includeDynamic ? ['include' => 'dynamic'] : [],
            ));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Page with slug '{$slug}' not found.", previous: $exception);
        }

        $payload = Payload::fromResponse($response);

        return Page::fromArray(
            Payload::data($payload),
            Data::nullableObject($payload, 'included'),
        );
    }
}
