<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

/**
 * Lightweight reference to a media item returned inline with other
 * resources (e.g. a post's cover image). Full media details can be
 * fetched via the Images / Files endpoints.
 */
final class MediaReference
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $url,
        public readonly ?string $name,
        public readonly ?string $mimeType,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $known = ['uuid', 'url', 'name', 'mime_type'];
        $extra = array_diff_key($data, array_flip($known));

        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            url: isset($data['url']) ? (string) $data['url'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            mimeType: isset($data['mime_type']) ? (string) $data['mime_type'] : null,
            extra: $extra,
        );
    }
}
