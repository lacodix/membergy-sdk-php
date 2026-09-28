<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Enums\SearchResultType;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;

final readonly class SearchGroup
{
    /**
     * @param  list<SearchHit>  $items
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public SearchResultType $type,
        public array $items,
        public SearchMeta $meta,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $typeValue = Data::string($data, 'type');
        $type = SearchResultType::tryFrom($typeValue);

        if ($type === null) {
            throw new HydrationException(
                "Invalid API payload: unsupported search result type '{$typeValue}'.",
            );
        }

        $items = array_map(
            static fn (array $item): SearchHit => match ($type) {
                SearchResultType::PAGE => PageSearchHit::fromArray($item),
                SearchResultType::POST => PostSearchHit::fromArray($item),
                SearchResultType::EVENT => EventSearchHit::fromArray($item),
            },
            Data::objectList($data, 'items'),
        );

        return new self(
            type: $type,
            items: $items,
            meta: SearchMeta::fromArray(Data::object($data, 'meta')),
            extra: Data::extra($data, ['type', 'items', 'meta']),
        );
    }
}
