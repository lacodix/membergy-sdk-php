<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\DataObjects\MenuTargets\MenuTarget;
use Lacodix\MembergySdk\Factories\MenuTargetFactory;
use Lacodix\MembergySdk\Support\Data;

final readonly class MenuItem
{
    /**
     * @param  list<string>  $rel
     * @param  array<string, mixed>  $meta
     * @param  list<MenuItem>  $children
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?MenuTarget $target,
        public bool $openInNewTab,
        public array $rel,
        public array $meta,
        public array $children,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?MenuTargetFactory $factory = null): self
    {
        $factory ??= new MenuTargetFactory;
        $target = Data::nullableObject($data, 'target');

        return new self(
            id: Data::string($data, 'id'),
            label: Data::string($data, 'label'),
            target: $factory->fromArray($target),
            openInNewTab: Data::bool($data, 'open_in_new_tab'),
            rel: Data::stringList($data, 'rel'),
            meta: Data::object($data, 'meta'),
            children: array_map(
                static fn (array $child): self => self::fromArray($child, $factory),
                Data::objectList($data, 'children'),
            ),
            extra: Data::extra($data, [
                'id', 'label', 'target', 'open_in_new_tab', 'rel', 'meta', 'children',
            ]),
        );
    }
}
