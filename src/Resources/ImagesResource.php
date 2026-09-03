<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\Image;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Factories\MediaFactory;
use Lacodix\MembergySdk\Requests\Content\ListImagesRequest;
use Lacodix\MembergySdk\Requests\Content\ShowImageRequest;
use Lacodix\MembergySdk\Support\Payload;

final class ImagesResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<Image> */
    public function get(): PaginatedResult
    {
        $factory = new MediaFactory;

        return $this->paginate(
            new ListImagesRequest($this->connector->tenant(), $this->query),
            $factory->imageFromArray(...),
        );
    }

    public function find(string $uuid): Image
    {
        try {
            $response = $this->send(new ShowImageRequest($this->connector->tenant(), $uuid));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Image with UUID '{$uuid}' not found.", previous: $exception);
        }

        return (new MediaFactory)->imageFromArray(Payload::data(Payload::fromResponse($response)));
    }
}
