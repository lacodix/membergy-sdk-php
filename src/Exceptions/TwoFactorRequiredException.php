<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

use DateTimeImmutable;
use SensitiveParameter;
use Throwable;

final class TwoFactorRequiredException extends MembergyException
{
    public function __construct(
        string $message,
        #[SensitiveParameter] public readonly ?string $challenge = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function __debugInfo(): array
    {
        return ['message' => $this->getMessage(), 'challenge' => '[redacted]', 'expires_at' => $this->expiresAt];
    }
}
