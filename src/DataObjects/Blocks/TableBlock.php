<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;

final readonly class TableBlock extends AbstractBlock
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, mixed>  $dataExtra
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public array $headers,
        public array $rows,
        public bool $hasHeader,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'table', $version, $settings, $extra);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    public static function fromData(string $id, int $version, BlockSettings $settings, array $data, array $extra = []): self
    {
        $headers = Data::stringList($data, 'headers');
        $rows = Data::value($data, 'rows');
        $hasHeader = $headers !== [];
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new HydrationException("Invalid API payload: 'rows' must be list<list<string>>.");
        }
        if (array_key_exists('has_header', $data) && Data::bool($data, 'has_header') !== $hasHeader) {
            throw new HydrationException("Invalid API payload: 'has_header' must match headers.");
        }
        $width = count($headers);
        foreach ($rows as $row) {
            $cells = Data::stringList(['cells' => $row], 'cells');
            $width = $width ?: count($cells);
            if (count($cells) !== $width || $cells === []) {
                throw new HydrationException('Invalid API payload: table rows must be rectangular.');
            }
        }
        if ($width < 1 || $width > 12 || count($rows) + ($hasHeader ? 1 : 0) > 101) {
            throw new HydrationException('Invalid API payload: table dimensions exceed the CMS contract.');
        }
        foreach ([$headers, ...$rows] as $cells) {
            foreach ($cells as $cell) {
                // Count Unicode characters without requiring ext-mbstring in the core SDK.
                if (preg_match('/^.{0,2000}$/usD', $cell) !== 1) {
                    throw new HydrationException('Invalid API payload: table cells must contain at most 2000 characters.');
                }
            }
        }

        return new self($id, $version, $settings, Data::nullableString($data, 'title'), $headers, $rows, $hasHeader,
            Data::extra($data, ['title', 'headers', 'rows', 'has_header']), $extra);
    }
}
