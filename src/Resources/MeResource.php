<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\ManagementLogin;
use Lacodix\MembergySdk\Requests\Me\IssueManagementLoginRequest;
use Lacodix\MembergySdk\Support\Payload;

final class MeResource extends AbstractResource
{
    public function managementLogin(): ManagementLogin
    {
        return ManagementLogin::fromArray(Payload::data(Payload::fromResponse(
            $this->send(new IssueManagementLoginRequest($this->connector->tenant())),
        )));
    }

    public function access(): TenantAccessResource
    {
        return new TenantAccessResource($this->connector);
    }

    public function password(): PasswordResource
    {
        return new PasswordResource($this->connector);
    }

    public function person(): PersonResource
    {
        return new PersonResource($this->connector);
    }

    public function newsletter(): MemberNewsletterResource
    {
        return new MemberNewsletterResource($this->connector);
    }
}
