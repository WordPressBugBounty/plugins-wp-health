<?php
namespace WPUmbrella\Actions\Hardening\LoginGuard;

if (!defined('ABSPATH')) {
    exit;
}

class BloomFilter
{
    const MAGIC = 'UBLM';

    const VERSION = 1;

    const HEADER_LENGTH = 20;

    const CHECKSUM_LENGTH = 32;

    public static function canonicalizeIp($ip)
    {
        if (!is_string($ip)) {
            return null;
        }

        $ip = strtolower(trim($ip));

        if ($ip === '') {
            return null;
        }

        if (strpos($ip, '::ffff:') === 0) {
            $mapped = substr($ip, 7);

            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ip = $mapped;
            }
        }

        return $ip;
    }

    public static function isValidBlob($blob)
    {
        if (!is_string($blob)) {
            return false;
        }

        if (strlen($blob) < self::HEADER_LENGTH + self::CHECKSUM_LENGTH) {
            return false;
        }

        if (substr($blob, 0, 4) !== self::MAGIC) {
            return false;
        }

        if (ord($blob[4]) !== self::VERSION) {
            return false;
        }

        $header = self::readHeader($blob);

        if ($header === null) {
            return false;
        }

        $expectedBitsetLength = (int) ceil($header['m'] / 8);
        $expectedTotalLength = self::HEADER_LENGTH + $expectedBitsetLength + self::CHECKSUM_LENGTH;

        if (strlen($blob) !== $expectedTotalLength) {
            return false;
        }

        $payload = substr($blob, 0, self::HEADER_LENGTH + $expectedBitsetLength);
        $checksum = substr($blob, self::HEADER_LENGTH + $expectedBitsetLength);

        return hash_equals(hash('sha256', $payload, true), $checksum);
    }

    public static function isMember($blob, $ip)
    {
        $header = self::readHeader($blob);

        if ($header === null) {
            return false;
        }

        $m = $header['m'];
        $k = $header['k'];

        if ($m < 1 || $k < 1) {
            return false;
        }

        $bitsetLength = (int) ceil($m / 8);
        $bitset = substr($blob, self::HEADER_LENGTH, $bitsetLength);

        if (strlen($bitset) !== $bitsetLength) {
            return false;
        }

        list($h1, $h2) = self::hashes($ip);

        for ($i = 0; $i < $k; $i++) {
            $combined = ($h1 + (($i * $h2) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $position = $combined % $m;

            $byteIndex = intdiv($position, 8);
            $bitMask = 1 << (7 - ($position % 8));

            if ((ord($bitset[$byteIndex]) & $bitMask) === 0) {
                return false;
            }
        }

        return true;
    }

    protected static function readHeader($blob)
    {
        if (!is_string($blob) || strlen($blob) < self::HEADER_LENGTH) {
            return null;
        }

        $unpacked = unpack('Nm/Nk/Nn', substr($blob, 8, 12));

        if (!is_array($unpacked)) {
            return null;
        }

        return $unpacked;
    }

    protected static function hashes($ip)
    {
        $digest = hash('sha256', $ip, true);
        $bytes = array_values(unpack('C*', $digest));

        $h1 = (($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3]) & 0xFFFFFFFF;
        $h2 = (($bytes[4] << 24) | ($bytes[5] << 16) | ($bytes[6] << 8) | $bytes[7]) & 0xFFFFFFFF;

        return [$h1, $h2];
    }
}
