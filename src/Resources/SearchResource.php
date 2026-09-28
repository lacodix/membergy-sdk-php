<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\SearchResult;
use Lacodix\MembergySdk\Enums\SearchResultType;
use Lacodix\MembergySdk\Requests\Content\SearchContentRequest;
use Lacodix\MembergySdk\Support\Payload;

final class SearchResource extends AbstractResource
{
    private const INVISIBLE_CHARACTERS = '\x{0009}\x{0020}\x{00A0}\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2000}\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}\x{202F}\x{205F}\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}\x{2065}\x{206A}\x{206B}\x{206C}\x{206D}\x{206E}\x{206F}\x{3000}\x{2800}\x{3164}\x{FEFF}\x{FFA0}\x{1D159}\x{1D173}\x{1D174}\x{1D175}\x{1D176}\x{1D177}\x{1D178}\x{1D179}\x{1D17A}\x{E0020}';

    private const DEFAULT_TRIM_CHARACTERS = " \n\r\t\v\0";

    private const MAX_QUERY_CHARACTERS = 320;

    private const MAX_QUERY_TERMS = 16;

    private const MAX_PAGE = 1000;

    public function query(string $query): self
    {
        $query = $this->normalizeQuery($query);

        $characters = preg_match_all('/./us', $query);
        if ($characters === false || $characters > self::MAX_QUERY_CHARACTERS) {
            throw new InvalidArgumentException('Search query must not exceed 320 characters.');
        }

        if (count(explode(' ', $query)) > self::MAX_QUERY_TERMS) {
            throw new InvalidArgumentException('Search query must not exceed 16 terms.');
        }

        return $this->withQuery(['q' => $query]);
    }

    public function type(SearchResultType $type): self
    {
        return $this->withQuery(['type' => $type->value]);
    }

    public function page(int $page): self
    {
        if ($page < 1 || $page > self::MAX_PAGE) {
            throw new InvalidArgumentException('Page must be between 1 and 1000.');
        }

        return $this->withQuery(['page' => $page]);
    }

    public function get(): SearchResult
    {
        if (! is_string($this->query['q'] ?? null) || $this->query['q'] === '') {
            throw new InvalidArgumentException('Search query is required.');
        }

        $page = $this->query['page'] ?? 1;
        if (! is_int($page) || $page < 1 || $page > self::MAX_PAGE) {
            throw new InvalidArgumentException('Page must be between 1 and 1000.');
        }

        $type = $this->query['type'] ?? null;
        $expectedType = is_string($type) ? SearchResultType::tryFrom($type) : null;
        if ($type !== null && $expectedType === null) {
            throw new InvalidArgumentException('Search result type is invalid.');
        }

        if ($page > 1 && $expectedType === null) {
            throw new InvalidArgumentException('Search result pages after the first require a result type.');
        }

        $response = $this->send(new SearchContentRequest(
            $this->connector->tenant(),
            $this->query,
        ));

        return SearchResult::fromArray(Payload::fromResponse($response), $expectedType);
    }

    private function normalizeQuery(string $query): string
    {
        $trimCharacters = self::INVISIBLE_CHARACTERS.self::DEFAULT_TRIM_CHARACTERS;
        $query = preg_replace(
            '~^[\s'.$trimCharacters.']+|[\s'.$trimCharacters.']+$~u',
            '',
            $query,
        );

        if ($query === null) {
            throw new InvalidArgumentException('Search query must be valid UTF-8.');
        }

        $query = preg_replace('~(\s|\x{3164}|\x{1160})+~u', ' ', $query);

        if ($query === null) {
            throw new InvalidArgumentException('Search query must be valid UTF-8.');
        }

        if (preg_match('/[\p{L}\p{N}]/u', $query) !== 1) {
            throw new InvalidArgumentException(
                'Search query must contain at least one Unicode letter or number.',
            );
        }

        return $query;
    }
}
