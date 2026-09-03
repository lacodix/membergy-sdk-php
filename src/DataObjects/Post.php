<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;

/**
 * Data transfer object for a Membergy CMS post.
 *
 * Fields are modeled after the Post resource shipped with Membergy
 * as of writing. Unknown keys from the API are kept in $extra so
 * the SDK doesn't break on non-breaking API additions.
 */
final class Post
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $category,
        public readonly ?string $categoryUuid,
        public readonly ?string $visibility,
        public readonly bool $published,
        public readonly ?DateTimeImmutable $publishedAt,
        /** The content body. Membergy stores it as a JSON structure. */
        public readonly mixed $content,
        public readonly ?MediaReference $media,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $known = [
            'uuid', 'title', 'slug', 'category', 'category_uuid',
            'visibility', 'published', 'published_at', 'content',
            'media', 'media_id',
        ];

        $extra = array_diff_key($data, array_flip($known));

        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            category: self::nullableString($data, 'category'),
            categoryUuid: self::nullableString($data, 'category_uuid'),
            visibility: self::nullableString($data, 'visibility'),
            published: (bool) ($data['published'] ?? false),
            publishedAt: self::nullableDate($data, 'published_at'),
            content: $data['content'] ?? null,
            media: isset($data['media']) && is_array($data['media'])
                ? MediaReference::fromArray($data['media'])
                : null,
            extra: $extra,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableDate(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
