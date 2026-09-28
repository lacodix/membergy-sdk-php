<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Enums\SearchResultType;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;

final readonly class SearchResult
{
    /**
     * @param  list<SearchGroup>  $groups
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public array $groups,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?SearchResultType $expectedType = null): self
    {
        $groups = array_map(SearchGroup::fromArray(...), Data::objectList($data, 'data'));

        if ($expectedType === null && count($groups) !== 3) {
            throw new HydrationException(
                'Invalid API payload: untyped search results must contain exactly three groups.',
            );
        }

        if ($expectedType !== null && count($groups) !== 1) {
            throw new HydrationException(
                'Invalid API payload: typed search results must contain exactly one group.',
            );
        }

        if ($expectedType !== null && $groups[0]->type !== $expectedType) {
            throw new HydrationException(
                "Invalid API payload: typed search result group must match requested type '{$expectedType->value}'.",
            );
        }

        if (
            $expectedType === null
            && array_map(
                static fn (SearchGroup $group): SearchResultType => $group->type,
                $groups,
            ) !== [SearchResultType::PAGE, SearchResultType::POST, SearchResultType::EVENT]
        ) {
            throw new HydrationException(
                'Invalid API payload: complete search results must be ordered page, post, event.',
            );
        }

        return new self(
            groups: $groups,
            extra: Data::extra($data, ['data']),
        );
    }

    public function group(SearchResultType $type): ?SearchGroup
    {
        foreach ($this->groups as $group) {
            if ($group->type === $type) {
                return $group;
            }
        }

        return null;
    }
}
