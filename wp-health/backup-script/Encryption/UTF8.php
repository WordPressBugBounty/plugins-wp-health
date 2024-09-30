<?php

if (!class_exists('UmbrellaUTF8', false)):
    class UmbrellaUTF8
    {
        public static function seemsUTF8($p)
        {
            static $first;
            if ($first === null) {
                $xx = 0xF1; // invalid: size 1
                $as = 0xF0; // ASCII: size 1
                $s1 = 0x02; // accept 0, size 2
                $s2 = 0x13; // accept 1, size 3
                $s3 = 0x03; // accept 0, size 3
                $s4 = 0x23; // accept 2, size 3
                $s5 = 0x34; // accept 3, size 4
                $s6 = 0x04; // accept 0, size 4
                $s7 = 0x44; // accept 4, size 4
                $first = [
                    //   1    2    3    4    5    6    7    8    9    A    B    C    D    E    F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x00-0x0F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x10-0x1F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x20-0x2F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x30-0x3F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x40-0x4F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x50-0x5F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x60-0x6F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x70-0x7F
                    //   1    2    3    4    5    6    7    8    9    A    B    C    D    E    F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0x80-0x8F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0x90-0x9F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xA0-0xAF
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xB0-0xBF
                    $xx, $xx, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, // 0xC0-0xCF
                    $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, // 0xD0-0xDF
                    $s2, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s4, $s3, $s3, // 0xE0-0xEF
                    $s5, $s6, $s6, $s6, $s7, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xF0-0xFF
                ];
            }
            static $xx = 0xF1;
            static $locb = 0x80;
            static $hicb = 0xBF;
            static $acceptRanges;
            if ($acceptRanges === null) {
                $acceptRanges = [
                    0 => [$locb, $hicb],
                    1 => [0xA0, $hicb],
                    2 => [$locb, 0x9F],
                    3 => [0x90, $hicb],
                    4 => [$locb, 0x8F],
                ];
            }
            $n = strlen($p);
            for ($i = 0; $i < $n;) {
                $pi = ord($p[$i]);
                if ($pi < 0x80) {
                    $i++;
                    continue;
                }
                $x = $first[$pi];
                if ($x === $xx) {
                    return false; // Illegal starter byte.
                }
                $size = $x & 7;
                if ($i + $size > $n) {
                    return false; // Short or invalid.
                }
                $accept = $acceptRanges[$x >> 4];
                if ((($c = ord($p[$i + 1])) < $accept[0]) || ($accept[1] < $c)) {
                    return false;
                } elseif ($size === 2) {
                } elseif ((($c = ord($p[$i + 2])) < $locb) || ($hicb < $c)) {
                    return false;
                } elseif ($size === 3) {
                } elseif ((($c = ord($p[$i + 3])) < $locb) || ($hicb < $c)) {
                    return false;
                }
                $i += $size;
            }
            return true;
        }

        public static function encodeNonUTF8($p)
        {
            static $first;
            if ($first === null) {
                $xx = 0xF1; // invalid: size 1
                $as = 0xF0; // ASCII: size 1
                $s1 = 0x02; // accept 0, size 2
                $s2 = 0x13; // accept 1, size 3
                $s3 = 0x03; // accept 0, size 3
                $s4 = 0x23; // accept 2, size 3
                $s5 = 0x34; // accept 3, size 4
                $s6 = 0x04; // accept 0, size 4
                $s7 = 0x44; // accept 4, size 4
                $first = [
                    //   1   2   3   4   5   6   7   8   9   A   B   C   D   E   F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x00-0x0F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x10-0x1F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x20-0x2F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x30-0x3F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x40-0x4F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x50-0x5F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x60-0x6F
                    $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, $as, // 0x70-0x7F
                    //   1   2   3   4   5   6   7   8   9   A   B   C   D   E   F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0x80-0x8F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0x90-0x9F
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xA0-0xAF
                    $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xB0-0xBF
                    $xx, $xx, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, // 0xC0-0xCF
                    $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, $s1, // 0xD0-0xDF
                    $s2, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s3, $s4, $s3, $s3, // 0xE0-0xEF
                    $s5, $s6, $s6, $s6, $s7, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, $xx, // 0xF0-0xFF
                ];
            }
            static $xx = 0xF1;
            static $locb = 0x80;
            static $hicb = 0xBF;
            static $acceptRanges;
            if ($acceptRanges === null) {
                $acceptRanges = [
                    0 => [$locb, $hicb],
                    1 => [0xA0, $hicb],
                    2 => [$locb, 0x9F],
                    3 => [0x90, $hicb],
                    4 => [$locb, 0x8F],
                ];
            }
            $percent = ord('%');
            $plus = ord('+');
            $encoded = false;
            $fixed = '';
            $n = strlen($p);
            $invalid = false;
            for ($i = 0; $i < $n;) {
                if ($invalid) {
                    if (!$encoded) {
                        // Make sure that "urldecode" call transforms the string to its original form.
                        // We don't encode printable characters, only invalid UTF-8; but these characters
                        // will always be processed by URL-decoder.
                        $fixed = strtr($fixed, ['%' => '%25', '+' => '%2B']);
                    }
                    $encoded = true;
                    $fixed .= urlencode($p[$i]);
                    $invalid = false;
                    $i++;
                    continue;
                }
                $pi = ord($p[$i]);
                if ($pi < 0x80) {
                    if ($encoded && $pi === $percent) {
                        $fixed .= '%25';
                    } elseif ($encoded && $pi === $plus) {
                        $fixed .= '%2B';
                    } else {
                        $fixed .= $p[$i];
                    }
                    $i++;
                    continue;
                }
                $x = $first[$pi];
                if ($x === $xx) {
                    $invalid = true;
                    continue;
                }
                $size = $x & 7;
                if ($i + $size > $n) {
                    $invalid = true;
                    continue;
                }
                $accept = $acceptRanges[$x >> 4];
                if ((($c = ord($p[$i + 1])) < $accept[0]) || ($accept[1] < $c)) {
                    $invalid = true;
                    continue;
                } elseif ($size === 2) {
                } elseif ((($c = ord($p[$i + 2])) < $locb) || ($hicb < $c)) {
                    $invalid = true;
                    continue;
                } elseif ($size === 3) {
                } elseif ((($c = ord($p[$i + 3])) < $locb) || ($hicb < $c)) {
                    $invalid = true;
                    continue;
                }
                $fixed .= substr($p, $i, $size);
                $i += $size;
            }
            return $fixed;
        }
    }
endif;
