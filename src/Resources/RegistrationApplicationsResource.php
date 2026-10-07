<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\RegistrationApplication;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationMessage;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationTokenState;
use Lacodix\MembergySdk\Requests\Registrations\RequestRegistrationMagicLinkRequest;
use Lacodix\MembergySdk\Requests\Registrations\ResendRegistrationVerificationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ResumeRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationResumeRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationVerificationRequest;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Requests\Registrations\VerifyRegistrationApplicationRequest;
use SensitiveParameter;

final class RegistrationApplicationsResource extends AbstractRegistrationResource
{
    public function submit(string $offerSlug, #[SensitiveParameter] RegistrationSubmission $submission, #[SensitiveParameter] ?RegistrationResume $resume = null): RegistrationApplication
    {
        return $this->hydrate($this->send(new SubmitRegistrationApplicationRequest(
            $this->connector->tenant(), $offerSlug, $submission, $resume,
        )), RegistrationApplication::fromArray(...));
    }

    public function find(#[SensitiveParameter] string $publicId): RegistrationApplication
    {
        return $this->hydrate($this->send(new ShowRegistrationApplicationRequest($publicId)), RegistrationApplication::fromArray(...));
    }

    public function requestMagicLink(#[SensitiveParameter] string $email): RegistrationMessage
    {
        return $this->hydrate($this->send(new RequestRegistrationMagicLinkRequest(
            $this->connector->tenant(), $email,
        )), RegistrationMessage::fromArray(...), wrapped: false);
    }

    /** Read-only landing. A GET never verifies an application or exchanges a link. */
    public function verificationLanding(#[SensitiveParameter] string $token): RegistrationTokenState
    {
        return $this->hydrate($this->send(new ShowRegistrationVerificationRequest($token)), RegistrationTokenState::fromArray(...));
    }

    /** Explicit consuming POST; call only from the website's confirmed POST handler. */
    public function verify(#[SensitiveParameter] string $token): RegistrationApplication
    {
        return $this->hydrate($this->send(new VerifyRegistrationApplicationRequest($token)), RegistrationApplication::fromArray(...));
    }

    public function resendVerification(#[SensitiveParameter] string $publicId, #[SensitiveParameter] string $submissionKey, #[SensitiveParameter] string $email): RegistrationMessage
    {
        return $this->hydrate($this->send(new ResendRegistrationVerificationRequest(
            $publicId, $submissionKey, $email,
        )), RegistrationMessage::fromArray(...), wrapped: false);
    }

    /** Read-only landing. Repeated GETs leave the magic link unconsumed. */
    public function resumeLanding(#[SensitiveParameter] string $token): RegistrationTokenState
    {
        return $this->hydrate($this->send(new ShowRegistrationResumeRequest($token)), RegistrationTokenState::fromArray(...));
    }

    /** Headless consuming POST. Store the returned context only in encrypted, tenant-scoped server session state. */
    public function resume(#[SensitiveParameter] string $token): RegistrationResume
    {
        $resume = $this->hydrate($this->send(new ResumeRegistrationApplicationRequest($token)), RegistrationResume::fromArray(...));
        $resume->assertBinding($this->connector->tenant(), $resume->offerSlug);

        return $resume;
    }
}
