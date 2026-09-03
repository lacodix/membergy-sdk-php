<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\DataObjects\DynamicIncludes\DynamicInclude;
use Lacodix\MembergySdk\Factories\DynamicIncludeFactory;
use Lacodix\MembergySdk\Support\Data;

final readonly class DynamicIncludes
{
    /**
     * @param  list<DynamicInclude>  $dynamic
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public array $dynamic,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?DynamicIncludeFactory $factory = null): self
    {
        $factory ??= new DynamicIncludeFactory;

        return new self(
            dynamic: array_map($factory->fromArray(...), Data::objectList($data, 'dynamic')),
            extra: Data::extra($data, ['dynamic']),
        );
    }
}
