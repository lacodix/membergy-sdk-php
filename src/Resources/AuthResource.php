<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\AccessToken;
use Lacodix\MembergySdk\DataObjects\AuthResult;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Auth\RequestPasswordResetLinkRequest;
use Lacodix\MembergySdk\Requests\Auth\ResetPasswordRequest;
use Lacodix\MembergySdk\Requests\Auth\RevokeCurrentTokenRequest;
use Lacodix\MembergySdk\Support\Payload;

final class AuthResource extends AbstractResource
{
    public function tokenFromCredentials(
        string $email,
        string $password,
        string $deviceName,
    ): AccessToken {
        $resource = $this->withoutUserToken();
        $response = $resource->send(new CreateTokenRequest($email, $password, $deviceName));

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
        string $token,
        string $email,
        string $password,
        string $passwordConfirmation,
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
