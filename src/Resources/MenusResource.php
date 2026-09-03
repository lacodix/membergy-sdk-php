<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\Menu;
use Lacodix\MembergySdk\DataObjects\MenuSummary;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListMenusRequest;
use Lacodix\MembergySdk\Requests\Content\ShowMenuRequest;
use Lacodix\MembergySdk\Support\Payload;

final class MenusResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<MenuSummary> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListMenusRequest($this->connector->tenant(), $this->query),
            MenuSummary::fromArray(...),
        );
    }

    public function find(string $handle): Menu
    {
        try {
            $response = $this->send(new ShowMenuRequest($this->connector->tenant(), $handle));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Menu with handle '{$handle}' not found.", previous: $exception);
        }

        return Menu::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
