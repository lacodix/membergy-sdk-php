<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationRequirement
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $title,
        public ?string $body,
        public ?string $statement,
        public bool $required,
        public int $position,
        public ?string $contentHash,
        public ?string $documentUrl,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            id: Data::int($data, 'id'),
            kind: Data::string($data, 'kind'),
            title: Data::string($data, 'title'),
            body: Data::nullableString($data, 'body'),
            statement: Data::nullableString($data, 'statement'),
            required: Data::bool($data, 'required'),
            position: Data::int($data, 'position'),
            contentHash: Data::nullableString($data, 'content_hash'),
            documentUrl: Data::nullableString($data, 'document_url'),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
