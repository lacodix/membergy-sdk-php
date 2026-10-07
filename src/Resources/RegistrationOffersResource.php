<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\RegistrationOffer;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationDocument;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\Exceptions\RegistrationPayloadException;
use Lacodix\MembergySdk\Requests\Registrations\DownloadRegistrationDocumentRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use SensitiveParameter;

final class RegistrationOffersResource extends AbstractRegistrationResource
{
    public function find(string $slug, #[SensitiveParameter] ?RegistrationResume $resume = null): RegistrationOffer
    {
        return $this->hydrate($this->send(new ShowRegistrationOfferRequest(
            $this->connector->tenant(), $slug, $resume,
        )), RegistrationOffer::fromArray(...));
    }

    public function document(string $slug, int $requirementId): RegistrationDocument
    {
        // Resolve on the configured tenant/base URL; never follow a server-supplied absolute document URL.
        $response = $this->send(new DownloadRegistrationDocumentRequest($this->connector->tenant(), $slug, $requirementId));
        $contentType = $response->getPsrResponse()->getHeaderLine('Content-Type');
        if (strtolower(trim(explode(';', $contentType)[0])) !== 'application/pdf') {
            throw new RegistrationPayloadException('The registration document response has an unexpected content type.');
        }

        return new RegistrationDocument($response->body(), $contentType);
    }
}
