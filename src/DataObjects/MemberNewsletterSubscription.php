<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class MemberNewsletterSubscription
{
    /**
     * @param  list<string>  $categories
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $email,
        public array $categories,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: Data::string($data, 'email'),
            categories: Data::stringList($data, 'categories'),
            extra: Data::extra($data, ['email', 'categories']),
        );
    }
}
