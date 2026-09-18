<?php
namespace WPUmbrella\Services\Tls;

if (!defined('ABSPATH')) {
    exit;
}

class CertificateProbe
{
    const OPTION_KEY = 'wp_umbrella_tls_probe';

    const ENDPOINT_PATH = '/v1/me';

    const TIMEOUT_SECONDS = 15;

    const MAX_ERROR_LENGTH = 300;

    /**
     * @return array|null
     */
    public function run()
    {
        if (!$this->canProbe()) {
            return null;
        }

        $result = $this->measure();

        update_option(self::OPTION_KEY, $result, false);

        return $result;
    }

    /**
     * @return array|null
     */
    public function getState()
    {
        $state = get_option(self::OPTION_KEY);

        if (!is_array($state)) {
            return null;
        }

        return $state;
    }

    /**
     * @return boolean
     */
    protected function canProbe()
    {
        return !empty(wp_umbrella_get_project_id());
    }

    /**
     * @return array
     */
    protected function checkedRequestArgs()
    {
        $args = [
            'timeout' => self::TIMEOUT_SECONDS,
            'sslverify' => true,
        ];

        $bearer = wp_umbrella_get_outbound_bearer();

        if (empty($bearer)) {
            return $args;
        }

        $args['headers'] = wp_umbrella_get_service('Owner')->getHeadersV2($bearer);

        return $args;
    }

    /**
     * @return array
     */
    protected function measure()
    {
        $url = WP_UMBRELLA_NEW_API_URL . self::ENDPOINT_PATH;

        $start = microtime(true);
        $response = wp_remote_get($url, $this->checkedRequestArgs());
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if (!is_wp_error($response)) {
            return $this->buildResult([
                'verdict' => 'verified',
                'http_code' => (int) wp_remote_retrieve_response_code($response),
                'duration_ms' => $durationMs,
                'clock_skew_seconds' => $this->clockSkew($response),
            ]);
        }

        $message = (string) $response->get_error_message();
        $kind = $this->classify($message);
        $control = $this->requestWithoutVerification($url);

        if ($control === null) {
            return $this->buildResult([
                'verdict' => 'unreachable',
                'duration_ms' => $durationMs,
                'error' => $this->truncate($message),
                'error_kind' => $kind,
            ]);
        }

        return $this->buildResult([
            'verdict' => $kind === 'timeout' ? 'inconclusive' : 'tls_failure',
            'http_code' => (int) wp_remote_retrieve_response_code($control),
            'duration_ms' => $durationMs,
            'error' => $this->truncate($message),
            'error_kind' => $kind,
            'clock_skew_seconds' => $this->clockSkew($control),
        ]);
    }

    /**
     * @param string $url
     * @return array|null
     */
    protected function requestWithoutVerification($url)
    {
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT_SECONDS,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @param array $response
     * @return integer|null
     */
    protected function clockSkew($response)
    {
        $date = wp_remote_retrieve_header($response, 'date');

        if (!is_string($date) || $date === '') {
            return null;
        }

        $remote = strtotime($date);

        if (!$remote) {
            return null;
        }

        return (int) (time() - $remote);
    }

    /**
     * @param string $message
     * @return string
     */
    protected function classify($message)
    {
        $message = strtolower($message);

        $kinds = [
            'expired_certificate' => ['certificate has expired', 'certificate is not yet valid'],
            'hostname_mismatch' => ['subjectaltname', 'does not match target host', 'no alternative certificate subject name'],
            'untrusted_chain' => ['unable to get local issuer certificate', 'self signed certificate', 'self-signed certificate', 'certificate verify failed', 'ssl certificate problem'],
            'handshake' => ['ssl connect error', 'wrong version number', 'unsupported protocol', 'handshake'],
            'timeout' => ['operation timed out', 'timed out after', 'connection timed out', 'timeout after', 'timeout was reached', 'ssl connection timeout'],
            'dns' => ['could not resolve host', 'name or service not known'],
        ];

        foreach ($kinds as $kind => $needles) {
            foreach ($needles as $needle) {
                if (strpos($message, $needle) !== false) {
                    return $kind;
                }
            }
        }

        return 'other';
    }

    /**
     * @param string $message
     * @return string
     */
    protected function truncate($message)
    {
        if (strlen($message) <= self::MAX_ERROR_LENGTH) {
            return $message;
        }

        return substr($message, 0, self::MAX_ERROR_LENGTH);
    }

    /**
     * @param array $result
     * @return array
     */
    protected function buildResult($result)
    {
        $defaults = [
            'verdict' => 'other',
            'http_code' => null,
            'duration_ms' => null,
            'error' => null,
            'error_kind' => null,
            'clock_skew_seconds' => null,
        ];

        $context = [
            'checked_at' => gmdate('c'),
            'plugin_version' => defined('WP_UMBRELLA_VERSION') ? WP_UMBRELLA_VERSION : null,
            'ssl_backend' => $this->sslBackend(),
        ];

        return array_merge($defaults, $result, $context);
    }

    /**
     * @return string|null
     */
    protected function sslBackend()
    {
        if (function_exists('curl_version')) {
            $version = curl_version();

            if (is_array($version) && !empty($version['ssl_version'])) {
                return (string) $version['ssl_version'];
            }
        }

        if (defined('OPENSSL_VERSION_TEXT')) {
            return (string) OPENSSL_VERSION_TEXT;
        }

        return null;
    }
}
