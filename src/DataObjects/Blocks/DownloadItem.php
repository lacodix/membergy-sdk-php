<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class DownloadItem
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $label,
        public UuidReference $file,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            label: Data::string($data, 'label'),
            file: UuidReference::fromArray(Data::object($data, 'file')),
            extra: Data::extra($data, ['label', 'file']),
        );
    }
}
