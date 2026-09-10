<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\Event;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Enums\EventVisibilityType;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListEventsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowEventRequest;
use Lacodix\MembergySdk\Support\Payload;

final class EventsResource extends AbstractResource
{
    private const TYPES = [
        'rehearsal',
        'performance',
        'request_for_rehearsal',
        'request_for_performance',
        'meeting',
        'info',
        'others',
    ];

    public function cursor(string $cursor): self
    {
        if (trim($cursor) === '') {
            throw new InvalidArgumentException('Cursor must not be empty.');
        }

        return $this->withQuery(['cursor' => $cursor]);
    }

    public function perPage(int $perPage): self
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Per-page must be between 1 and 100.');
        }

        return $this->withQuery(['per_page' => $perPage]);
    }

    public function eventType(string $type): self
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown event type '{$type}'.");
        }

        return $this->withQuery(['event_type' => $type]);
    }

    public function visibilities(EventVisibilityType ...$visibilities): self
    {
        $values = array_map(
            static fn (EventVisibilityType $visibility): string => $visibility->value,
            $visibilities,
        );

        if ($values === [] || count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException('Event visibilities must contain unique supported values.');
        }

        return $this->withQuery(['visibility' => $values]);
    }

    public function tagIds(int ...$tagIds): self
    {
        if ($tagIds === []) {
            throw new InvalidArgumentException('Tag IDs must contain positive integers.');
        }

        foreach ($tagIds as $tagId) {
            if ($tagId < 1) {
                throw new InvalidArgumentException('Tag IDs must contain positive integers.');
            }
        }

        return $this->withQuery(['tags' => array_values(array_unique($tagIds))]);
    }

    public function participation(
        bool $noDecision = false,
        bool $accepted = false,
        bool $rejected = false,
    ): self {
        return $this->withQuery(['pp' => [
            'no_decision' => $noDecision,
            'accepted' => $accepted,
            'rejected' => $rejected,
        ]]);
    }

    /** @return PaginatedResult<Event> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListEventsRequest($this->connector->tenant(), $this->query),
            Event::fromArray(...),
        );
    }

    public function find(string $uuid): Event
    {
        try {
            $response = $this->send(new ShowEventRequest($this->connector->tenant(), $uuid));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Event with UUID '{$uuid}' not found.", previous: $exception);
        }

        return Event::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
