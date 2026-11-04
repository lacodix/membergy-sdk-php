<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

/** Editorial boundary; public resources normally remove it server-side. */
final readonly class MemberContinuationBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $dataExtra
     * @param  array<string, mixed>  $extra
     */
    public function __construct(string $id, int $version, BlockSettings $settings, public array $dataExtra = [], array $extra = [])
    {
        parent::__construct($id, 'member_continuation', $version, $settings, $extra);
    }
}
