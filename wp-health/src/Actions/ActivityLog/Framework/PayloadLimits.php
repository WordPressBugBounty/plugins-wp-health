<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Size bounds shared by everything that writes into the activity log buffer.
 *
 * Every bound is expressed in bytes so the guard and the cut use the same
 * unit, and so the result does not depend on which string extensions the
 * host has loaded.
 */
class PayloadLimits
{
    /**
     * Maximum size, in bytes, of a single string stored in an event payload.
     */
    const MAX_STRING_BYTES = 500;

    /**
     * Maximum size, in bytes, of one JSON encoded payload row.
     */
    const MAX_PAYLOAD_BYTES = 16384;

    /**
     * Maximum total size, in bytes, of the payloads loaded by one drain.
     */
    const MAX_BATCH_BYTES = 2097152;

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function truncate($value)
    {
        if (is_string($value)) {
            return self::truncateString($value);
        }

        if (is_array($value)) {
            return self::truncateArray($value);
        }

        return $value;
    }

    /**
     * @param mixed    $value
     * @param int|null $maxBytes
     *
     * @return mixed
     */
    public static function truncateString($value, $maxBytes = null)
    {
        $maxBytes = $maxBytes === null ? self::MAX_STRING_BYTES : max(0, (int) $maxBytes);

        if (!is_string($value) || strlen($value) <= $maxBytes) {
            return $value;
        }

        return self::cutOnCodepointBoundary($value, $maxBytes);
    }

    /**
     * Applies the string bound to every value of an array, recursively.
     * Integer keys keep their type so JSON lists stay lists.
     *
     * @param array $values
     *
     * @return array
     */
    public static function truncateArray(array $values)
    {
        $bounded = [];

        foreach ($values as $key => $value) {
            $boundedKey = is_string($key) ? self::truncateString($key) : $key;
            $bounded[$boundedKey] = self::truncate($value);
        }

        return $bounded;
    }

    /**
     * Cuts a string to at most $maxBytes bytes without splitting a UTF-8
     * sequence, so the result stays encodable by wp_json_encode().
     *
     * @param string $value
     * @param int    $maxBytes
     *
     * @return string
     */
    protected static function cutOnCodepointBoundary($value, $maxBytes)
    {
        $cut = substr($value, 0, $maxBytes);
        $cutLength = strlen($cut);
        $index = $cutLength - 1;

        while ($index >= 0 && (ord($cut[$index]) & 0xC0) === 0x80) {
            $index--;
        }

        if ($index < 0) {
            return '';
        }

        $lead = ord($cut[$index]);
        $expected = 1;

        if (($lead & 0xF8) === 0xF0) {
            $expected = 4;
        } elseif (($lead & 0xF0) === 0xE0) {
            $expected = 3;
        } elseif (($lead & 0xE0) === 0xC0) {
            $expected = 2;
        }

        if (($index + $expected) <= $cutLength) {
            return $cut;
        }

        return substr($cut, 0, $index);
    }
}
