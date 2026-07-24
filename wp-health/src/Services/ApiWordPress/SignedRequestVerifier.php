<?php
namespace WPUmbrella\Services\ApiWordPress;

use WPUmbrella\Core\Constants\SignedRequest;

class SignedRequestVerifier
{
    const NONCE_TRANSIENT_PREFIX = 'wpu_srn_';

    const LOGIN_CANONICAL_PREFIX = 'wpu-login-v1';

    protected static $resultByNonce = [];

    public function hasSignatureHeaders(array $headers)
    {
        return isset($headers[strtolower(SignedRequest::SIGNATURE_HEADER)])
            && isset($headers[strtolower(SignedRequest::TIMESTAMP_HEADER)])
            && isset($headers[strtolower(SignedRequest::NONCE_HEADER)]);
    }

    /**
     * Verify a one-click login signature carried as request params (a browser
     * form cannot set headers). Binds only the user id, so a valid signature
     * authorizes a login for that user without the site's secret_token ever
     * travelling to the browser. Single-use nonce + freshness bound the replay
     * window; the private key needed to forge one never leaves the worker.
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

    public function verify(array $headers, $method, $path, $body)
    {
        $nonceKey = isset($headers[strtolower(SignedRequest::NONCE_HEADER)])
            ? $headers[strtolower(SignedRequest::NONCE_HEADER)] : null;

        if ($nonceKey !== null && array_key_exists($nonceKey, self::$resultByNonce)) {
            return self::$resultByNonce[$nonceKey];
        }

        $result = $this->doVerify($headers, $method, $path, $body);

        if ($nonceKey !== null) {
            self::$resultByNonce[$nonceKey] = $result;
        }

        return $result;
    }

    protected function doVerify(array $headers, $method, $path, $body)
    {
        $ctx = strtoupper($method) . ' ' . $path;

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            wp_umbrella_debug_log("signed request {$ctx}: sodium unavailable");
            return false;
        }

        $signature = isset($headers[strtolower(SignedRequest::SIGNATURE_HEADER)])
            ? $headers[strtolower(SignedRequest::SIGNATURE_HEADER)] : null;
        $timestamp = isset($headers[strtolower(SignedRequest::TIMESTAMP_HEADER)])
            ? $headers[strtolower(SignedRequest::TIMESTAMP_HEADER)] : null;
        $nonce = isset($headers[strtolower(SignedRequest::NONCE_HEADER)])
            ? $headers[strtolower(SignedRequest::NONCE_HEADER)] : null;

        if (!$signature || !$timestamp || !$nonce) {
            wp_umbrella_debug_log("signed request {$ctx}: missing signature headers");
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
            strtoupper($method),
            $path,
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
        $key = self::NONCE_TRANSIENT_PREFIX . md5($nonce);

        if (get_transient($key) !== false) {
            return false;
        }

        set_transient($key, 1, SignedRequest::FRESHNESS_WINDOW_SECONDS + 60);

        return true;
    }
}
