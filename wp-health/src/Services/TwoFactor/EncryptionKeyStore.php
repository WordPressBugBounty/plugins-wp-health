<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class EncryptionKeyStore
{
    const CONSTANT_KEY = 'WP_UMBRELLA_2FA_KEY';

    const KEY_BYTES = 32;

    const INFO = 'wp-umbrella-two-factor-v1';

    const SALT_CONSTANTS = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'];

    const PLACEHOLDER = 'put your unique phrase here';

    /**
     * @var string|null
     */
    protected $cached = null;

    /**
     * @return string|null
     */
    public function getKey()
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $key = $this->readConstant();

        if ($key === null) {
            $key = $this->deriveFromSalts();
        }

        if ($key !== null) {
            $this->cached = $key;
        }

        return $key;
    }

    /**
     * @return string|null
     */
    protected function readConstant()
    {
        if (!defined(self::CONSTANT_KEY)) {
            return null;
        }

        $value = constant(self::CONSTANT_KEY);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $this->decodeKey($value);
    }

    /**
     * @return string|null
     */
    protected function deriveFromSalts()
    {
        $material = $this->saltConstants();

        if ($material === null) {
            $material = wp_salt('secure_auth');
        }

        if (!is_string($material) || $material === '') {
            return null;
        }

        return hash_hkdf('sha256', $material, self::KEY_BYTES, self::INFO);
    }

    /**
     * @return string|null
     */
    protected function saltConstants()
    {
        $parts = [];

        foreach (self::SALT_CONSTANTS as $constant) {
            if (!defined($constant)) {
                continue;
            }

            $value = constant($constant);

            if (!is_string($value) || strlen($value) < 32 || strpos($value, self::PLACEHOLDER) !== false) {
                continue;
            }

            $parts[] = $constant . '=' . $value;
        }

        return $parts === [] ? null : implode('|', $parts);
    }

    /**
     * @param string $encoded
     *
     * @return string|null
     */
    protected function decodeKey($encoded)
    {
        $decoded = base64_decode($encoded, true);

        if (!is_string($decoded) || strlen($decoded) !== self::KEY_BYTES) {
            return null;
        }

        return $decoded;
    }
}
