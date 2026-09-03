<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Factories\MenuTargetFactory;
use Lacodix\MembergySdk\Support\Data;

final readonly class Menu
{
    /**
     * @param  list<MenuItem>  $items
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $handle,
        public string $visibility,
        public array $items,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?MenuTargetFactory $factory = null): self
    {
        $factory ??= new MenuTargetFactory;

        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            handle: Data::string($data, 'handle'),
            visibility: Data::string($data, 'visibility'),
            items: array_map(
                static fn (array $item): MenuItem => MenuItem::fromArray($item, $factory),
                Data::objectList($data, 'items'),
            ),
            publishedAt: Data::nullableDate($data, 'published_at'),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, [
                'uuid', 'title', 'handle', 'visibility', 'items', 'published_at', 'updated_at',
            ]),
        );
    }
}
