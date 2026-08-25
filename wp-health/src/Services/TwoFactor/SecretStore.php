<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class SecretStore
{
    const META_SECRET = 'wp_umbrella_2fa_secret';

    const META_ENROLLED_AT = 'wp_umbrella_2fa_enrolled_at';

    const PREFIX_SODIUM = 's2:';

    const PREFIX_OPENSSL = 'o2:';

    const OPENSSL_CIPHER = 'aes-256-gcm';

    /**
     * @var EncryptionKeyStore
     */
    protected $keyStore;

    public function __construct(?EncryptionKeyStore $keyStore = null)
    {
        $this->keyStore = $keyStore !== null ? $keyStore : new EncryptionKeyStore();
    }

    /**
     * @param int $userId
     *
     * @return bool
     */
    public function isEnrolled($userId)
    {
        $blob = get_user_meta((int) $userId, self::META_SECRET, true);

        return is_string($blob) && $blob !== '';
    }

    /**
     * @param int $userId
     *
     * @return bool
     */
    public function hasSecret($userId)
    {
        return $this->getSecret($userId) !== null;
    }

    /**
     * @param int $userId
     *
     * @return string|null
     */
    public function getSecret($userId)
    {
        $blob = get_user_meta((int) $userId, self::META_SECRET, true);

        if (!is_string($blob) || $blob === '') {
            return null;
        }

        $key = $this->keyStore->getKey();

        if ($key === null) {
            return null;
        }

        return $this->decrypt($blob, $key, (int) $userId);
    }

    /**
     * @param int    $userId
     * @param string $secret
     *
     * @return bool
     */
    public function setSecret($userId, $secret)
    {
        if (!is_string($secret) || $secret === '') {
            return false;
        }

        $key = $this->keyStore->getKey();

        if ($key === null) {
            return false;
        }

        $blob = $this->encrypt($secret, $key, (int) $userId);

        if ($blob === null) {
            return false;
        }

        update_user_meta((int) $userId, self::META_SECRET, $blob);
        update_user_meta((int) $userId, self::META_ENROLLED_AT, time());

        return true;
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    public function clear($userId)
    {
        delete_user_meta((int) $userId, self::META_SECRET);
        delete_user_meta((int) $userId, self::META_ENROLLED_AT);
    }

    /**
     * @param int $userId
     *
     * @return int|null
     */
    public function getEnrolledAt($userId)
    {
        $value = get_user_meta((int) $userId, self::META_ENROLLED_AT, true);

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param int    $userId
     * @param string $plaintext
     *
     * @return string|null
     */
    public function protect($userId, $plaintext)
    {
        $key = $this->keyStore->getKey();

        if ($key === null || !is_string($plaintext) || $plaintext === '') {
            return null;
        }

        return $this->encrypt($plaintext, $key, (int) $userId);
    }

    /**
     * @param int    $userId
     * @param string $blob
     *
     * @return string|null
     */
    public function reveal($userId, $blob)
    {
        $key = $this->keyStore->getKey();

        if ($key === null || !is_string($blob) || $blob === '') {
            return null;
        }

        return $this->decrypt($blob, $key, (int) $userId);
    }

    /**
     * @param string $plaintext
     * @param string $key
     * @param int    $userId
     *
     * @return string|null
     */
    protected function encrypt($plaintext, $key, $userId)
    {
        $associatedData = $this->associatedData($userId);

        if ($this->hasSodium()) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $associatedData, $nonce, $key);

            return self::PREFIX_SODIUM . base64_encode($nonce . $cipher);
        }

        if ($this->hasOpenssl()) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plaintext, self::OPENSSL_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $associatedData);

            if ($cipher === false) {
                return null;
            }

            return self::PREFIX_OPENSSL . base64_encode($iv . $tag . $cipher);
        }

        return null;
    }

    /**
     * @param string $blob
     * @param string $key
     * @param int    $userId
     *
     * @return string|null
     */
    protected function decrypt($blob, $key, $userId)
    {
        $associatedData = $this->associatedData($userId);

        if (strpos($blob, self::PREFIX_SODIUM) === 0) {
            return $this->decryptSodium(substr($blob, strlen(self::PREFIX_SODIUM)), $key, $associatedData);
        }

        if (strpos($blob, self::PREFIX_OPENSSL) === 0) {
            return $this->decryptOpenssl(substr($blob, strlen(self::PREFIX_OPENSSL)), $key, $associatedData);
        }

        return null;
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    protected function associatedData($userId)
    {
        return 'wp-umbrella-2fa|user:' . (int) $userId;
    }

    /**
     * @param string $encoded
     * @param string $key
     * @param string $associatedData
     *
     * @return string|null
     */
    protected function decryptSodium($encoded, $key, $associatedData)
    {
        if (!$this->hasSodium()) {
            return null;
        }

        $raw = base64_decode($encoded, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if (!is_string($raw) || strlen($raw) <= $nonceLength) {
            return null;
        }

        $nonce = substr($raw, 0, $nonceLength);
        $cipher = substr($raw, $nonceLength);

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $associatedData, $nonce, $key);
        } catch (\Exception $exception) {
            return null;
        }

        if ($plaintext === false || $plaintext === '') {
            return null;
        }

        return $plaintext;
    }

    /**
     * @param string $encoded
     * @param string $key
     * @param string $associatedData
     *
     * @return string|null
     */
    protected function decryptOpenssl($encoded, $key, $associatedData)
    {
        if (!$this->hasOpenssl()) {
            return null;
        }

        $raw = base64_decode($encoded, true);

        if (!is_string($raw) || strlen($raw) <= 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plaintext = openssl_decrypt($cipher, self::OPENSSL_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $associatedData);

        if ($plaintext === false || $plaintext === '') {
            return null;
        }

        return $plaintext;
    }

    /**
     * @return bool
     */
    protected function hasSodium()
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt');
    }

    /**
     * @return bool
     */
    protected function hasOpenssl()
    {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && in_array(self::OPENSSL_CIPHER, openssl_get_cipher_methods(), true);
    }
}
