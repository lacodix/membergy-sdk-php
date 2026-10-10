<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

use SensitiveParameter;
use Throwable;

final class RequestValidationException extends MembergyException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        #[SensitiveParameter] public readonly array $errors,
        ?Throwable $previous = null,
        #[SensitiveParameter] public readonly ?string $errorCode = null,
    ) {
        parent::__construct('The Membergy API rejected the request parameters.', previous: $previous);
    }

    public function __debugInfo(): array
    {
        return ['message' => $this->getMessage(), 'errors' => '[redacted]', 'error_code' => '[redacted]'];
    }
}
