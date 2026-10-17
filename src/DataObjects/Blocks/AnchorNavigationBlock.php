<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Exceptions\HydrationException;

final readonly class AnchorNavigationBlock extends AbstractBlock
{
    /**
     * @param  list<int>|null  $levels
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?array $levels = null,
        public array $dataExtra = [],
        array $extra = [],
        public string $scope = 'page',
    ) {
        if (! in_array($scope, ['page', 'following'], true)) {
            throw new HydrationException("Invalid API payload: 'scope' must be page or following.");
        }

        if ($levels !== null && (! array_is_list($levels) || count($levels) > 6
            || count(array_unique($levels, SORT_REGULAR)) !== count($levels))) {
            throw new HydrationException("Invalid API payload: 'levels' must be a unique list of heading integers.");
        }
        foreach ($levels ?? [] as $level) {
            if (! is_int($level) || $level < 1 || $level > 6) {
                throw new HydrationException("Invalid API payload: 'levels' must contain integers from 1 to 6.");
            }
        }

        parent::__construct($id, 'anchor_navigation', $version, $settings, $extra);
    }

    /** @return array<string, mixed> */
    public function dataToArray(): array
    {
        return ($this->levels === null ? [] : ['levels' => $this->levels])
            + ['scope' => $this->scope] + $this->dataExtra;
    }
}
