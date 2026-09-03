<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\FileReference;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Factories\MediaFactory;
use Lacodix\MembergySdk\Requests\Content\ListFilesRequest;
use Lacodix\MembergySdk\Requests\Content\ShowFileRequest;
use Lacodix\MembergySdk\Support\Payload;

final class FilesResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<FileReference> */
    public function get(): PaginatedResult
    {
        $factory = new MediaFactory;

        return $this->paginate(
            new ListFilesRequest($this->connector->tenant(), $this->query),
            $factory->fileFromArray(...),
        );
    }

    public function find(string $uuid): FileReference
    {
        try {
            $response = $this->send(new ShowFileRequest($this->connector->tenant(), $uuid));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("File with UUID '{$uuid}' not found.", previous: $exception);
        }

        return (new MediaFactory)->fileFromArray(Payload::data(Payload::fromResponse($response)));
    }
}
