<?php

declare(strict_types=1);

namespace Contenir\Extension\CampaignMonitor\Service;

use JsonException;

use function array_filter;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Typed reads from Campaign Monitor's JSON, where anything may be missing.
 *
 * @api
 */
final class Json
{
    /**
     * The JSON object or array in $json, or null when it holds something else or isn't JSON.
     *
     * @return array<array-key, mixed>|null
     */
    public static function document(string $json): ?array
    {
        try {
            return self::array(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    public static function int(mixed $row, string $key): ?int
    {
        return is_array($row) && array_key_exists($key, $row) && is_int($row[$key]) ? $row[$key] : null;
    }

    /**
     * The object under $key, or an empty one when there isn't one.
     *
     * @return array<array-key, mixed>
     */
    public static function object(mixed $row, string $key): array
    {
        return is_array($row) && array_key_exists($key, $row) && is_array($row[$key]) ? $row[$key] : [];
    }

    /**
     * The objects in a JSON array, skipping anything else in it.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    public static function string(mixed $row, string $key): string
    {
        return is_array($row) && array_key_exists($key, $row) && is_string($row[$key]) ? $row[$key] : '';
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function array(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
}
