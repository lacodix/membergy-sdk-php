<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class Link
{
    /**
     * @param  list<string>  $rel
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $url,
        public ?string $label,
        public bool $openInNewTab,
        public array $rel,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: Data::string($data, 'url'),
            label: Data::nullableString($data, 'label'),
            openInNewTab: Data::bool($data, 'open_in_new_tab'),
            rel: Data::stringList($data, 'rel'),
            extra: Data::extra($data, ['url', 'label', 'open_in_new_tab', 'rel']),
        );
    }
}
