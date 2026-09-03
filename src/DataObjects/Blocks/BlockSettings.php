<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class BlockSettings
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $anchor,
        public ?string $color,
        public ?BlockBackground $background,
        public ?string $container,
        public ?string $spacing,
        public ?string $overlap,
        public ?string $margin,
        public ?string $titlePosition,
        public ?string $animation,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $background = Data::nullableObject($data, 'background');

        return new self(
            anchor: Data::nullableString($data, 'anchor'),
            color: Data::nullableString($data, 'color'),
            background: $background === null ? null : BlockBackground::fromArray($background),
            container: Data::nullableString($data, 'container'),
            spacing: Data::nullableString($data, 'spacing'),
            overlap: Data::nullableString($data, 'overlap'),
            margin: Data::nullableString($data, 'margin'),
            titlePosition: Data::nullableString($data, 'title_position'),
            animation: Data::nullableString($data, 'animation'),
            extra: Data::extra($data, [
                'anchor', 'color', 'background', 'container', 'spacing', 'overlap',
                'margin', 'title_position', 'animation',
            ]),
        );
    }
}
