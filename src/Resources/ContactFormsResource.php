<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\ContactFormAcknowledgement;
use Lacodix\MembergySdk\DataObjects\ContactFormDefinition;
use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Requests\Content\ShowContactFormRequest;
use Lacodix\MembergySdk\Requests\Content\SubmitContactFormRequest;
use Lacodix\MembergySdk\Support\Payload;
use SensitiveParameter;

final class ContactFormsResource extends AbstractResource
{
    public function submit(string $uuid, #[SensitiveParameter] ContactFormSubmission $submission): ContactFormAcknowledgement
    {
        $this->assertUuid($uuid);

        $response = $this->send(new SubmitContactFormRequest($this->connector->tenant(), $uuid, $submission));
        if ($response->status() !== 202) {
            throw new HydrationException('Invalid contact form acknowledgement status.');
        }

        return ContactFormAcknowledgement::fromArray(Payload::data(Payload::fromResponse($response)));
    }

    public function find(string $uuid): ContactFormDefinition
    {
        $this->assertUuid($uuid);

        return ContactFormDefinition::fromArray(Payload::data(Payload::fromResponse($this->send(
            new ShowContactFormRequest($this->connector->tenant(), $uuid),
        ))));
    }

    private function assertUuid(string $uuid): void
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $uuid) !== 1) {
            throw new InvalidArgumentException('Invalid contact form UUID.');
        }
    }
}
