<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class LogoItem
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public BlockMediaReference $image,
        public ?Link $link,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $link = Data::nullableObject($data, 'link');

        return new self(
            image: BlockMediaReference::fromArray(Data::object($data, 'image')),
            link: $link === null ? null : Link::fromArray($link),
            extra: Data::extra($data, ['image', 'link']),
        );
    }
}
