<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Exceptions\HydrationException;

final readonly class ContactFormBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $formUuid,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        if ($formUuid !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $formUuid) !== 1) {
            throw new HydrationException("Invalid API payload: 'form_uuid' must be UUID|null.");
        }

        parent::__construct($id, 'contact_form', $version, $settings, $extra);
    }

    /** @return array<string, mixed> */
    public function dataToArray(): array
    {
        return ['form_uuid' => $this->formUuid] + $this->dataExtra;
    }
}
