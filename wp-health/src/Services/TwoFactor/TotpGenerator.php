<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class TotpGenerator
{
    const STEP = 30;

    const DIGITS = 6;

    const DEFAULT_WINDOW = 1;

    const SECRET_BYTES = 20;

    /**
     * @var Base32
     */
    protected $base32;

    public function __construct(?Base32 $base32 = null)
    {
        $this->base32 = $base32 !== null ? $base32 : new Base32();
    }

    /**
     * @return string
     */
    public function generateSecret()
    {
        return $this->base32->encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * @param string   $secret
     * @param int|null $timestamp
     *
     * @return string|null
     */
    public function codeAt($secret, $timestamp = null)
    {
        $key = $this->base32->decode($secret);

        if ($key === null || $key === '') {
            return null;
        }

        $timestamp = $timestamp === null ? time() : (int) $timestamp;
        $counter = (int) floor($timestamp / self::STEP);

        return $this->hotp($key, $counter);
    }

    /**
     * @param string   $secret
     * @param string   $code
     * @param int|null $timestamp
     *
     * @return bool
     */
    public function verify($secret, $code, $timestamp = null)
    {
        return $this->matchCounter($secret, $code, $timestamp) !== null;
    }

    /**
     * @param string   $secret
     * @param string   $code
     * @param int|null $timestamp
     *
     * @return int|null
     */
    public function matchCounter($secret, $code, $timestamp = null)
    {
        if (!is_string($code)) {
            return null;
        }

        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== self::DIGITS) {
            return null;
        }

        $key = $this->base32->decode($secret);

        if ($key === null || $key === '') {
            return null;
        }

        $timestamp = $timestamp === null ? time() : (int) $timestamp;
        $counter = (int) floor($timestamp / self::STEP);
        $window = $this->getWindow();

        for ($offset = -$window; $offset <= $window; ++$offset) {
            $candidate = $this->hotp($key, $counter + $offset);

            if ($candidate !== null && hash_equals($candidate, $code)) {
                return $counter + $offset;
            }
        }

        return null;
    }

    /**
     * @param string $secret
     * @param string $accountName
     * @param string $issuer
     *
     * @return string
     */
    public function provisioningUri($secret, $accountName, $issuer)
    {
        $label = rawurlencode($this->sanitizeLabelPart($issuer))
            . ':'
            . rawurlencode($this->sanitizeLabelPart($accountName));

        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::STEP,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * The label is issuer and account joined by a colon. Authenticator apps
     * decode it before splitting, so a colon inside either part lands the split
     * in the wrong place and the entry shows up under a mangled name.
     *
     * @param string $value
     *
     * @return string
     */
    protected function sanitizeLabelPart($value)
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', str_replace(':', ' ', $value)));
    }

    /**
     * @return int
     */
    protected function getWindow()
    {
        $window = self::DEFAULT_WINDOW;

        if (function_exists('apply_filters')) {
            $window = apply_filters('wp_umbrella_two_factor_window', $window);
        }

        $window = (int) $window;

        if ($window < 0) {
            return 0;
        }

        if ($window > 10) {
            return 10;
        }

        return $window;
    }

    /**
     * @param string $key
     * @param int    $counter
     *
     * @return string|null
     */
    protected function hotp($key, $counter)
    {
        if ($counter < 0) {
            return null;
        }

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[19]) & 0xf;

        $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $modulo = $truncated % pow(10, self::DIGITS);

        return str_pad((string) $modulo, self::DIGITS, '0', STR_PAD_LEFT);
    }
}
