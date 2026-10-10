<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use InvalidArgumentException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class CompleteTwoFactorRequest extends AbstractAuthRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        #[SensitiveParameter] private string $challenge,
        #[SensitiveParameter] private ?string $code = null,
        #[SensitiveParameter] private ?string $recoveryCode = null,
    ) {
        if (($code === null) === ($recoveryCode === null)) {
            throw new InvalidArgumentException('Supply exactly one second factor.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/token/two-factor';
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return ['challenge' => $this->challenge, ...($this->code !== null
            ? ['code' => $this->code] : ['recovery_code' => (string) $this->recoveryCode])];
    }
}
