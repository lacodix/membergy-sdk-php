<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Support;

use DateTimeImmutable;
use Lacodix\MembergySdk\Exceptions\HydrationException;

final class Data
{
    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw self::invalid($key, 'string');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw self::invalid($key, 'string|null');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw self::invalid($key, 'int');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw self::invalid($key, 'int|null');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (! is_bool($value)) {
            throw self::invalid($key, 'bool');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function value(array $data, string $key): mixed
    {
        if (! array_key_exists($key, $data)) {
            throw self::invalid($key, 'present');
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (! is_float($value) && ! is_int($value)) {
            throw self::invalid($key, 'float');
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_float($value) && ! is_int($value)) {
            throw self::invalid($key, 'float|null');
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw self::invalid($key, 'object');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function nullableObject(array $data, string $key): ?array
    {
        if (($data[$key] ?? null) === null) {
            return null;
        }

        return self::object($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function objectList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key, 'list<object>');
        }

        $objects = [];

        foreach ($value as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw self::invalid($key, 'list<object>');
            }

            /** @var array<string, mixed> $item */
            $objects[] = $item;
        }

        return $objects;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key, 'list<string>');
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw self::invalid($key, 'list<string>');
            }
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function intList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key, 'list<int>');
        }

        foreach ($value as $item) {
            if (! is_int($item)) {
                throw self::invalid($key, 'list<int>');
            }
        }

        /** @var list<int> $value */
        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function date(array $data, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::string($data, $key));
    }

    /** @param array<string, mixed> $data */
    public static function nullableDate(array $data, string $key): ?DateTimeImmutable
    {
        $value = self::nullableString($data, $key);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $known
     * @return array<string, mixed>
     */
    public static function extra(array $data, array $known): array
    {
        return array_diff_key($data, array_flip($known));
    }

    private static function invalid(string $key, string $expected): HydrationException
    {
        return new HydrationException("Invalid API payload: '{$key}' must be {$expected}.");
    }
}
