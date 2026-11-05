<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Exceptions\HydrationException;

final readonly class ContactFormBlock extends AbstractBlock
{
    /**
     * @param  list<ContactTopic>  $topics
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public string $formHandle,
        public array $topics,
        public array $dataExtra = [],
        array $extra = [],
        public ?string $formUuid = null,
        public ?int $definitionVersion = null,
        public ?string $submitLabel = null,
        public ?string $title = null,
    ) {
        if ($formUuid !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $formUuid) !== 1) {
            throw new HydrationException("Invalid API payload: 'form_uuid' must be UUID|null.");
        }

        parent::__construct($id, 'contact_form', $version, $settings, $extra);
    }

    /** @return array<string, mixed> */
    public function dataToArray(): array
    {
        $data = $this->formHandle !== '' || $this->topics !== []
            ? ['form_handle' => $this->formHandle, 'topics' => array_map(static fn (ContactTopic $topic): array => ['key' => $topic->key, 'label' => $topic->label] + $topic->extra, $this->topics)]
            : ['form_uuid' => $this->formUuid];
        foreach (['definition_version' => $this->definitionVersion, 'submit_label' => $this->submitLabel, 'title' => $this->title] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data + $this->dataExtra;
    }
}
