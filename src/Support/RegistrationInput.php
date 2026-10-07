<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Support;

use InvalidArgumentException;
use SensitiveParameter;

final class RegistrationInput
{
    public static function tenant(string $value): void
    {
        if (strlen($value) > 220 || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Registration requires the canonical tenant public slug.');
        }
    }

    public static function slug(string $value): void
    {
        if ($value === '' || in_array($value, ['.', '..'], true) || strpbrk($value, '\\/?#%') !== false || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw new InvalidArgumentException('Invalid registration offer slug.');
        }
    }

    public static function uuid(#[SensitiveParameter] string $value): void
    {
        if (preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-4[a-fA-F0-9]{3}-[89abAB][a-fA-F0-9]{3}-[a-fA-F0-9]{12}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Registration identifiers must be UUID v4.');
        }
    }

    public static function email(#[SensitiveParameter] string $value): void
    {
        if (self::length($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid registration email address.');
        }
    }

    public static function token(#[SensitiveParameter] string $value, bool $landing = false): void
    {
        if (($landing && preg_match('/^[a-fA-F0-9]{64}$/D', $value) !== 1) || (! $landing && ($value === '' || self::length($value) > 128))) {
            throw new InvalidArgumentException('Invalid registration link token.');
        }
    }

    /** JSON Schema lengths count Unicode code points; the SDK requires no mbstring extension. */
    public static function length(#[SensitiveParameter] string $value): int
    {
        $length = preg_match_all('/./us', $value);
        if ($length === false) {
            throw new InvalidArgumentException('Registration input must contain valid UTF-8.');
        }

        return $length;
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $allowed
     */
    public static function keys(#[SensitiveParameter] array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed) !== []) {
            throw new InvalidArgumentException('Unsupported registration input fields.');
        }
    }
}
