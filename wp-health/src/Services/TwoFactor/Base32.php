<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class Base32
{
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * @param string $bytes
     *
     * @return string
     */
    public function encode($bytes)
    {
        if (!is_string($bytes) || $bytes === '') {
            return '';
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        $length = strlen($bytes);

        for ($i = 0; $i < $length; ++$i) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 31];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 31];
        }

        return $output;
    }

    /**
     * @param string $encoded
     *
     * @return string|null
     */
    public function decode($encoded)
    {
        if (!is_string($encoded)) {
            return null;
        }

        $encoded = strtoupper(str_replace(['=', ' ', '-'], '', $encoded));

        if ($encoded === '') {
            return '';
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        $length = strlen($encoded);

        for ($i = 0; $i < $length; ++$i) {
            $position = strpos(self::ALPHABET, $encoded[$i]);

            if ($position === false) {
                return null;
            }

            $buffer = ($buffer << 5) | $position;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 255);
            }
        }

        return $output;
    }
}
