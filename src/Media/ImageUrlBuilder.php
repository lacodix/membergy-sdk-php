<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Media;

use InvalidArgumentException;

final readonly class ImageUrlBuilder
{
    /** @param array<string, int|float|string> $parameters */
    public function __construct(
        private string $baseUrl,
        private array $parameters = [],
    ) {
        $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Membergy image URLs must use HTTP or HTTPS.');
        }
    }

    public function width(int $width): self
    {
        return $this->with('w', $this->dimension($width));
    }

    public function height(int $height): self
    {
        return $this->with('h', $this->dimension($height));
    }

    public function fit(string $fit): self
    {
        if (! in_array($fit, [
            'contain',
            'fill',
            'max',
            'stretch',
            'fill-max',
            'cover',
            'crop',
            'crop-center',
            'crop-top',
            'crop-right',
            'crop-bottom',
            'crop-left',
        ], true)) {
            throw new InvalidArgumentException("Unsupported Membergy image fit '{$fit}'.");
        }

        return $this->with('fit', $fit);
    }

    public function format(string $format): self
    {
        if (! in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
            throw new InvalidArgumentException("Unsupported Membergy image format '{$format}'.");
        }

        return $this->with('fm', $format);
    }

    public function quality(int $quality): self
    {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException('Membergy image quality must be between 1 and 100.');
        }

        return $this->with('q', $quality);
    }

    public function dpr(float $dpr): self
    {
        if ($dpr < 1 || $dpr > 4) {
            throw new InvalidArgumentException('Membergy image DPR must be between 1 and 4.');
        }

        return $this->with('dpr', $dpr);
    }

    public function focus(float $x, float $y): self
    {
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1) {
            throw new InvalidArgumentException('Membergy image focus values must be between 0 and 1.');
        }

        $fit = sprintf('crop-%d-%d', (int) round($x * 100), (int) round($y * 100));

        return $this->with('fit', $fit);
    }

    public function url(): string
    {
        if ($this->parameters === []) {
            return $this->baseUrl;
        }

        $parameters = $this->parameters;
        ksort($parameters);
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return $this->baseUrl.(str_contains($this->baseUrl, '?') ? '&' : '?').$query;
    }

    /**
     * @param  list<int>  $widths
     */
    public function srcset(array $widths): string
    {
        $widths = array_values(array_unique($widths));
        sort($widths);

        return implode(', ', array_map(
            fn (int $width): string => $this->width($width)->url().' '.$width.'w',
            $widths,
        ));
    }

    private function dimension(int $dimension): int
    {
        if ($dimension < 1 || $dimension > 8192) {
            throw new InvalidArgumentException('Membergy image dimensions must be between 1 and 8192.');
        }

        return $dimension;
    }

    private function with(string $name, int|float|string $value): self
    {
        return new self($this->baseUrl, [...$this->parameters, $name => $value]);
    }
}
