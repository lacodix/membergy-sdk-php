<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class TenantAccess
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $tenantSlug,
        public string $displayName,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantSlug: Data::string($data, 'tenant_slug'),
            displayName: Data::string($data, 'display_name'),
            extra: Data::extra($data, ['tenant_slug', 'display_name']),
        );
    }
}
