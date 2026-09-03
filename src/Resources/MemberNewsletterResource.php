<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\MemberNewsletterSubscription;
use Lacodix\MembergySdk\Requests\Me\ShowNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Requests\Me\UpdateNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Support\Payload;

final class MemberNewsletterResource extends AbstractResource
{
    public function get(): MemberNewsletterSubscription
    {
        $payload = Payload::fromResponse($this->send(
            new ShowNewsletterSubscriptionsRequest($this->connector->tenant()),
        ));

        return MemberNewsletterSubscription::fromArray(Payload::data($payload));
    }

    /** @param list<string> $categoryUuids */
    public function replace(array $categoryUuids): MemberNewsletterSubscription
    {
        foreach ($categoryUuids as $uuid) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1) {
                throw new InvalidArgumentException("Invalid newsletter category UUID '{$uuid}'.");
            }
        }

        if (count($categoryUuids) !== count(array_unique($categoryUuids))) {
            throw new InvalidArgumentException('Newsletter category UUIDs must be distinct.');
        }

        $payload = Payload::fromResponse($this->send(
            new UpdateNewsletterSubscriptionsRequest(
                $this->connector->tenant(),
                array_values($categoryUuids),
            ),
        ));

        return MemberNewsletterSubscription::fromArray(Payload::data($payload));
    }
}
