<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class AgendaItem
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $time,
        public string $title,
        public ?RichText $text,
        public bool $highlight,
        public ?Link $link,
        public ?UuidReference $file,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $text = Data::nullableObject($data, 'text');
        $link = Data::nullableObject($data, 'link');
        $file = Data::nullableObject($data, 'file');

        return new self(
            time: Data::nullableString($data, 'time'),
            title: Data::string($data, 'title'),
            text: $text === null ? null : RichText::fromArray($text),
            highlight: Data::bool($data, 'highlight'),
            link: $link === null ? null : Link::fromArray($link),
            file: $file === null ? null : UuidReference::fromArray($file),
            extra: Data::extra($data, ['time', 'title', 'text', 'highlight', 'link', 'file']),
        );
    }
}
