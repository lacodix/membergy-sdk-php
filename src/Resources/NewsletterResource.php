<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\NewsletterCategory;
use Lacodix\MembergySdk\DataObjects\NewsletterRequestResult;
use Lacodix\MembergySdk\Requests\Content\ListNewsletterCategoriesRequest;
use Lacodix\MembergySdk\Requests\Content\RequestNewsletterUnsubscribeLinkRequest;
use Lacodix\MembergySdk\Requests\Content\SubscribeNewsletterRequest;
use Lacodix\MembergySdk\Support\Payload;

final class NewsletterResource extends AbstractResource
{
    /** @return list<NewsletterCategory> */
    public function categories(): array
    {
        $payload = Payload::fromResponse($this->send(
            new ListNewsletterCategoriesRequest($this->connector->tenant()),
        ));

        return array_map(NewsletterCategory::fromArray(...), Payload::collection($payload));
    }

    /** @param list<string> $categoryUuids */
    public function subscribe(string $email, array $categoryUuids): NewsletterRequestResult
    {
        $this->assertCategoryUuids($categoryUuids);
        $this->assertEmail($email);

        $response = $this->send(new SubscribeNewsletterRequest(
            $this->connector->tenant(),
            array_values(array_unique($categoryUuids)),
            $email,
        ));

        return NewsletterRequestResult::fromArray(Payload::fromResponse($response));
    }

    public function requestUnsubscribe(string $email): NewsletterRequestResult
    {
        $this->assertEmail($email);

        $response = $this->send(new RequestNewsletterUnsubscribeLinkRequest(
            $this->connector->tenant(),
            $email,
        ));

        return NewsletterRequestResult::fromArray(Payload::fromResponse($response));
    }

    /** @param list<string> $categoryUuids */
    private function assertCategoryUuids(array $categoryUuids): void
    {
        if ($categoryUuids === []) {
            throw new InvalidArgumentException('At least one newsletter category UUID is required.');
        }

        foreach ($categoryUuids as $uuid) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1) {
                throw new InvalidArgumentException("Invalid newsletter category UUID '{$uuid}'.");
            }
        }
    }

    private function assertEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid newsletter email '{$email}'.");
        }
    }
}
