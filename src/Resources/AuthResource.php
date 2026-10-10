<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\AccessToken;
use Lacodix\MembergySdk\DataObjects\AuthResult;
use Lacodix\MembergySdk\Requests\Auth\CompleteTwoFactorRequest;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Auth\RequestPasswordResetLinkRequest;
use Lacodix\MembergySdk\Requests\Auth\ResetPasswordRequest;
use Lacodix\MembergySdk\Requests\Auth\RevokeCurrentTokenRequest;
use Lacodix\MembergySdk\Support\Payload;
use SensitiveParameter;

final class AuthResource extends AbstractResource
{
    public function tokenFromCredentials(
        string $email,
        #[SensitiveParameter] string $password,
        string $deviceName,
        bool $remember = false,
    ): AccessToken {
        $resource = $this->withoutUserToken();
        $response = $resource->send(new CreateTokenRequest($email, $password, $deviceName, $remember));

        return AccessToken::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    public function completeTwoFactor(
        #[SensitiveParameter] string $challenge,
        #[SensitiveParameter] ?string $code = null,
        #[SensitiveParameter] ?string $recoveryCode = null,
    ): AccessToken {
        $response = $this->withoutUserToken()->send(new CompleteTwoFactorRequest($challenge, $code, $recoveryCode));

        return AccessToken::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    public function revokeCurrentToken(): AuthResult
    {
        $response = $this->send(new RevokeCurrentTokenRequest);

        return AuthResult::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    public function requestPasswordResetLink(string $email): AuthResult
    {
        $resource = $this->withoutUserToken();
        $response = $resource->send(new RequestPasswordResetLinkRequest($email));

        return AuthResult::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    public function resetPassword(
        #[SensitiveParameter] string $token,
        string $email,
        #[SensitiveParameter] string $password,
        #[SensitiveParameter] string $passwordConfirmation,
    ): AuthResult {
        $resource = $this->withoutUserToken();
        $response = $resource->send(new ResetPasswordRequest(
            $token,
            $email,
            $password,
            $passwordConfirmation,
        ));

        return AuthResult::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    private function withoutUserToken(): self
    {
        return $this->connector->hasUserToken()
            ? new self($this->connector->withoutUserToken())
            : $this;
    }
}
