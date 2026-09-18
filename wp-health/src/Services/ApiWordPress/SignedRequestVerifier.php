<?php
namespace WPUmbrella\Services\ApiWordPress;

use WPUmbrella\Core\Constants\SignedRequest;

class SignedRequestVerifier
{
    const NONCE_OPTION_PREFIX = 'wpu_srn_';

    const LOGIN_CANONICAL_PREFIX = 'wpu-login-v1';

    protected static $resultByNonce = [];

    public function hasSignatureHeaders(array $headers)
    {
        $hasSignature = isset($headers[strtolower(SignedRequest::SIGNATURE_V2_HEADER)])
            || isset($headers[strtolower(SignedRequest::SIGNATURE_HEADER)]);

        return $hasSignature
            && isset($headers[strtolower(SignedRequest::TIMESTAMP_HEADER)])
            && isset($headers[strtolower(SignedRequest::NONCE_HEADER)]);
    }

    /**
     * @param int|string $userId
     * @param string $signature
     * @param int|string $timestamp
     * @param string $nonce
     * @param string|null $keyId
     * @return boolean
     */
    public function verifyLogin($userId, $signature, $timestamp, $nonce, $keyId = null)
    {
        // Idempotent within the request: canExecute and the REST permission
        // callback both verify the same login, and the single-use nonce would
        // make the second pass reject the first. Memoize by nonce.
        if ($nonce !== null && $nonce !== '' && array_key_exists('login_' . $nonce, self::$resultByNonce)) {
            return self::$resultByNonce['login_' . $nonce];
        }

        $result = $this->doVerifyLogin($userId, $signature, $timestamp, $nonce, $keyId);

        if ($nonce !== null && $nonce !== '') {
            self::$resultByNonce['login_' . $nonce] = $result;
        }

        return $result;
    }

    protected function doVerifyLogin($userId, $signature, $timestamp, $nonce, $keyId = null)
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            wp_umbrella_debug_log('signed login: sodium unavailable');
            return false;
        }

        if ($userId === null || $userId === '' || !$signature || !$timestamp || !$nonce) {
            return false;
        }

        if (!ctype_digit((string) $timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > SignedRequest::FRESHNESS_WINDOW_SECONDS) {
            wp_umbrella_debug_log('signed login: timestamp out of window');
            return false;
        }

        $storedKeyId = wp_umbrella_get_key_id();
        if ($keyId !== null && $keyId !== '' && $storedKeyId && (string) $keyId !== (string) $storedKeyId) {
            wp_umbrella_debug_log('signed login: key_id mismatch');
            return false;
        }

        $publicKey = wp_umbrella_get_public_key();
        if (!$publicKey || !is_string($publicKey)) {
            wp_umbrella_debug_log('signed login: no public key stored');
            return false;
        }

        $publicKeyRaw = $this->decodePublicKey($publicKey);
        if ($publicKeyRaw === null || strlen($publicKeyRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        $signatureRaw = base64_decode($signature, true);
        if ($signatureRaw === false || strlen($signatureRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $canonical = implode("\n", [
            self::LOGIN_CANONICAL_PREFIX,
            (string) $userId,
            (string) $timestamp,
            $nonce,
        ]);

        if (!sodium_crypto_sign_verify_detached($signatureRaw, $canonical, $publicKeyRaw)) {
            wp_umbrella_debug_log('signed login: signature INVALID');
            return false;
        }

        if (!$this->consumeNonce($nonce)) {
            wp_umbrella_debug_log('signed login: nonce replay rejected');
            return false;
        }

        wp_umbrella_debug_log('signed login: signature OK key_id=' . $storedKeyId);
        return true;
    }

    public function verify(array $headers, $method, $path, $body, $query, $xAction)
    {
        $nonceKey = isset($headers[strtolower(SignedRequest::NONCE_HEADER)])
            ? $headers[strtolower(SignedRequest::NONCE_HEADER)] : null;

        if ($nonceKey !== null && array_key_exists($nonceKey, self::$resultByNonce)) {
            return self::$resultByNonce[$nonceKey];
        }

        $result = $this->doVerify($headers, $method, $path, $body, $query, $xAction);

        if ($nonceKey !== null) {
            self::$resultByNonce[$nonceKey] = $result;
        }

        return $result;
    }

    /**
     * Raw query string (no leading "?") to its canonical form: split on "&",
     * drop empty segments, split each on the first "=" (no "=" means an empty
     * value), urldecode then rawurlencode key and value, sort the "key=value"
     * pairs by byte order, join with "&".
     *
     * @param string|null $rawQuery
     * @return string
     */
    public function canonicalizeQuery($rawQuery)
    {
        if (!is_string($rawQuery) || $rawQuery === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $rawQuery) as $segment) {
            if ($segment === '') {
                continue;
            }

            $parts = explode('=', $segment, 2);
            $key = rawurlencode(urldecode($parts[0]));
            $value = isset($parts[1]) ? rawurlencode(urldecode($parts[1])) : '';

            $pairs[] = $key . '=' . $value;
        }

        sort($pairs, SORT_STRING);

        return implode('&', $pairs);
    }

    protected function doVerify(array $headers, $method, $path, $body, $query, $xAction)
    {
        $ctx = strtoupper($method) . ' ' . $path;

        if ($this->isMultipartRequest($headers)) {
            wp_umbrella_debug_log("signed request {$ctx}: unsupported content type");
            return false;
        }

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            wp_umbrella_debug_log("signed request {$ctx}: sodium unavailable");
            return false;
        }

        $signature = isset($headers[strtolower(SignedRequest::SIGNATURE_V2_HEADER)])
            ? $headers[strtolower(SignedRequest::SIGNATURE_V2_HEADER)] : null;
        $timestamp = isset($headers[strtolower(SignedRequest::TIMESTAMP_HEADER)])
            ? $headers[strtolower(SignedRequest::TIMESTAMP_HEADER)] : null;
        $nonce = isset($headers[strtolower(SignedRequest::NONCE_HEADER)])
            ? $headers[strtolower(SignedRequest::NONCE_HEADER)] : null;

        if (!$signature || !$timestamp || !$nonce) {
            wp_umbrella_debug_log("signed request {$ctx}: missing or empty v2 signature");
            return false;
        }

        if (!ctype_digit((string) $timestamp)) {
            wp_umbrella_debug_log("signed request {$ctx}: non-numeric timestamp");
            return false;
        }

        if (abs(time() - (int) $timestamp) > SignedRequest::FRESHNESS_WINDOW_SECONDS) {
            wp_umbrella_debug_log("signed request {$ctx}: timestamp out of window ts={$timestamp} now=" . time());
            return false;
        }

        $keyId = isset($headers[strtolower(SignedRequest::KEY_ID_HEADER)])
            ? $headers[strtolower(SignedRequest::KEY_ID_HEADER)] : null;
        $storedKeyId = wp_umbrella_get_key_id();
        if ($keyId !== null && $storedKeyId && (string) $keyId !== (string) $storedKeyId) {
            wp_umbrella_debug_log("signed request {$ctx}: key_id mismatch header={$keyId} stored={$storedKeyId}");
            return false;
        }

        $publicKey = wp_umbrella_get_public_key();
        if (!$publicKey || !is_string($publicKey)) {
            wp_umbrella_debug_log("signed request {$ctx}: no public key stored key_state=" . wp_umbrella_get_key_state());
            return false;
        }

        $publicKeyRaw = $this->decodePublicKey($publicKey);
        if ($publicKeyRaw === null || strlen($publicKeyRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            wp_umbrella_debug_log("signed request {$ctx}: public key decode failed");
            return false;
        }

        $signatureRaw = base64_decode($signature, true);
        if ($signatureRaw === false || strlen($signatureRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            wp_umbrella_debug_log("signed request {$ctx}: signature decode failed");
            return false;
        }

        $canonical = implode(SignedRequest::CANONICAL_SEPARATOR, [
            SignedRequest::CANONICAL_V2_PREFIX,
            strtoupper($method),
            $path,
            $this->canonicalizeQuery($query),
            $xAction === null ? '' : (string) $xAction,
            hash('sha256', $body === null ? '' : $body),
            (string) $timestamp,
            $nonce,
        ]);

        $valid = sodium_crypto_sign_verify_detached($signatureRaw, $canonical, $publicKeyRaw);
        if (!$valid) {
            wp_umbrella_debug_log("signed request {$ctx}: signature INVALID key_id={$storedKeyId}");
            return false;
        }

        if (!$this->consumeNonce($nonce)) {
            wp_umbrella_debug_log("signed request {$ctx}: nonce replay rejected");
            return false;
        }

        wp_umbrella_debug_log("signed request {$ctx}: signature OK key_id={$storedKeyId}");
        return true;
    }

    protected function isMultipartRequest(array $headers)
    {
        $contentTypes = [
            isset($headers['content-type']) ? $headers['content-type'] : null,
            isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null,
        ];

        foreach ($contentTypes as $contentType) {
            if (!is_string($contentType) || $contentType === '') {
                continue;
            }

            if (stripos($contentType, 'multipart/form-data') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function decodePublicKey($publicKey)
    {
        if (strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES * 2 && ctype_xdigit($publicKey)) {
            $raw = @hex2bin($publicKey);
            return $raw === false ? null : $raw;
        }

        $raw = base64_decode($publicKey, true);
        return $raw === false ? null : $raw;
    }

    protected function consumeNonce($nonce)
    {
        global $wpdb;

        $key = self::NONCE_OPTION_PREFIX . md5($nonce);
        $expiresAt = time() + SignedRequest::FRESHNESS_WINDOW_SECONDS + 60;

        $claimed = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $key,
                (string) $expiresAt
            )
        );

        if ($claimed !== 1) {
            return false;
        }

        $this->purgeConsumedNonces();

        return true;
    }

    protected function purgeConsumedNonces()
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                $wpdb->esc_like(self::NONCE_OPTION_PREFIX) . '%',
                time()
            )
        );
    }
}
