<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\AuthResult;
use Lacodix\MembergySdk\DataObjects\PasswordUpdateResult;
use Lacodix\MembergySdk\Requests\Me\ConfirmPasswordRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePasswordRequest;
use Lacodix\MembergySdk\Support\Payload;
use SensitiveParameter;

final class PasswordResource extends AbstractResource
{
    public function update(
        #[SensitiveParameter] string $currentPassword,
        #[SensitiveParameter] string $password,
        #[SensitiveParameter] string $passwordConfirmation,
    ): PasswordUpdateResult {
        $payload = Payload::fromResponse($this->send(new UpdatePasswordRequest(
            $this->connector->tenant(), $currentPassword, $password, $passwordConfirmation,
        )));

        return PasswordUpdateResult::fromArray(Payload::data($payload));
    }

    public function confirm(#[SensitiveParameter] string $password): AuthResult
    {
        $response = $this->send(new ConfirmPasswordRequest($this->connector->tenant(), $password));

        return AuthResult::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
