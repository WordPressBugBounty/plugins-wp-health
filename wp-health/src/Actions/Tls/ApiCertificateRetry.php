<?php
namespace WPUmbrella\Actions\Tls;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Tls\CertificateFallback;

class ApiCertificateRetry implements ExecuteHooks
{
    const HANDLED_ARG = 'wp_umbrella_certificate_retry';

    const RUNG_DIRECT = 'direct';

    const RUNG_BUNDLE = 'bundle';

    const RUNG_UNVERIFIED = 'unverified';

    /**
     * @var string|null
     */
    protected static $lastRung = null;

    /**
     * @return string|null
     */
    public static function consumeLastRung()
    {
        $rung = self::$lastRung;
        self::$lastRung = null;

        return $rung;
    }

    public function hooks()
    {
        add_filter('pre_http_request', [$this, 'request'], 10, 3);
    }

    /**
     * @param false|array|\WP_Error $preempt
     * @param array $args
     * @param string $url
     * @return false|array|\WP_Error
     */
    public function request($preempt, $args, $url)
    {
        if ($preempt !== false || !empty($args[self::HANDLED_ARG]) || empty($args['sslverify'])) {
            return $preempt;
        }

        if (!$this->targetsApi($url)) {
            return $preempt;
        }

        self::$lastRung = null;

        $fallback = wp_umbrella_get_service('CertificateFallback');

        $args[self::HANDLED_ARG] = true;

        $response = wp_remote_request($url, $args);

        if (!$this->failedOnCertificate($fallback, $response)) {
            $this->rememberRung($response, self::RUNG_DIRECT);

            return $response;
        }

        $bundlePath = $fallback->getBundlePath();

        if ($bundlePath !== null) {
            $response = wp_remote_request($url, array_merge($args, [
                'sslcertificates' => $bundlePath,
            ]));

            if (!$this->failedOnCertificate($fallback, $response)) {
                $this->rememberRung($response, self::RUNG_BUNDLE);

                return $response;
            }
        }

        if (empty($args[CertificateFallback::CONNECTION_ARG])) {
            return $response;
        }

        if ($fallback->isHostnameMismatch($response->get_error_message())) {
            return $response;
        }

        $unverified = wp_remote_request($url, array_merge($args, [
            'sslverify' => false,
        ]));

        if (is_wp_error($unverified)) {
            return $response;
        }

        $fallback->record($response->get_error_message());

        self::$lastRung = self::RUNG_UNVERIFIED;

        return $unverified;
    }

    /**
     * @param array|\WP_Error $response
     * @param string $rung
     * @return void
     */
    protected function rememberRung($response, $rung)
    {
        self::$lastRung = is_wp_error($response) ? null : $rung;
    }

    /**
     * @param CertificateFallback $fallback
     * @param array|\WP_Error $response
     * @return boolean
     */
    protected function failedOnCertificate($fallback, $response)
    {
        return is_wp_error($response) && $fallback->isCertificateError($response->get_error_message());
    }

    /**
     * @param string $url
     * @return boolean
     */
    protected function targetsApi($url)
    {
        foreach ([WP_UMBRELLA_NEW_API_URL, WP_UMBRELLA_PUBLIC_API_URL] as $base) {
            if (strpos((string) $url, rtrim($base, '/') . '/') === 0) {
                return true;
            }
        }

        return false;
    }
}
