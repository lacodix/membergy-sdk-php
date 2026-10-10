<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\NewsletterCategory;
use Lacodix\MembergySdk\DataObjects\NewsletterRequestResult;
use Lacodix\MembergySdk\Requests\Content\ConfirmNewsletterSubscriptionRequest;
use Lacodix\MembergySdk\Requests\Content\ListNewsletterCategoriesRequest;
use Lacodix\MembergySdk\Requests\Content\RequestNewsletterUnsubscribeLinkRequest;
use Lacodix\MembergySdk\Requests\Content\SubscribeNewsletterRequest;
use Lacodix\MembergySdk\Requests\Content\UnsubscribeAllNewsletterRequest;
use Lacodix\MembergySdk\Support\Payload;
use SensitiveParameter;

final class NewsletterResource extends AbstractResource
{
    private const MAX_NEWSLETTER_TOKEN_BYTES = 4096;

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

    public function confirm(#[SensitiveParameter] string $token): NewsletterRequestResult
    {
        $this->assertOpaqueToken($token, 'confirmation');

        $response = $this->send(new ConfirmNewsletterSubscriptionRequest(
            $this->connector->tenant(),
            $token,
        ));

        return NewsletterRequestResult::fromArray(Payload::fromResponse($response));
    }

    public function unsubscribeAll(#[SensitiveParameter] string $token): NewsletterRequestResult
    {
        $this->assertOpaqueToken($token, 'unsubscribe');

        $response = $this->send(new UnsubscribeAllNewsletterRequest(
            $this->connector->tenant(),
            $token,
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

    private function assertOpaqueToken(#[SensitiveParameter] string $token, string $purpose): void
    {
        $hasOuterWhitespace = preg_match('/(?:\A\s|\s\z)/u', $token);

        if (
            $token === ''
            || strlen($token) > self::MAX_NEWSLETTER_TOKEN_BYTES
            || $hasOuterWhitespace !== 0
        ) {
            throw new InvalidArgumentException("Invalid newsletter {$purpose} token.");
        }
    }
}
