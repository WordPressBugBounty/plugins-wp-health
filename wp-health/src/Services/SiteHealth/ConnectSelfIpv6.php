<?php
namespace WPUmbrella\Services\SiteHealth;

/**
 * This code provide by Wordfence plugin
 */
class ConnectSelfIpv6
{
    public function curlIsSupported()
    {
        $path = ABSPATH . 'wp-includes/class-wp-http-curl.php';
        if (!class_exists('WP_Http_Curl') && file_exists($path)) {
            require_once $path;
        }

        if (class_exists('WP_Http_Curl')) {
            return \WP_Http_Curl::test();
        }

        return false;
    }

    private function _detectBlockedByCloudflare($result)
    {
        $headers = $result['headers'];
        if (isset($headers['cf-mitigated']) && strtolower($headers['cf-mitigated']) == 'challenge' /* managed challenge */) {
            return true;
        }

        $body = $result['body'];
        $search = [
            '/cdn-cgi/styles/challenges.css', //managed challenge
            '/cdn-cgi/challenge-platform', //managed challenge
            '/cdn-cgi/styles/cf.errors.css', //block
            'cf-error-details', //block
            'Cloudflare Ray ID', //block
        ];
        foreach ($search as $s) {
            if (stripos($body, $s) !== false) {
                return true;
            }
        }
        return false;
    }

    public function connectToSelf($ipVersion = null)
    {
        $adminAJAX = admin_url('admin-ajax.php?action=umbrella_scantest');

        $result = wp_remote_post($adminAJAX, [
            'timeout' => 10,
            'blocking' => true,
            'headers' => [],
            'body' => [
                'action' => 'umbrella_scantest',
            ],
        ]);

        if ((!is_wp_error($result)) && $result['response']['code'] == 200 && strpos($result['body'], 'SCANTESTOK') !== false) {
            $host = parse_url($adminAJAX, PHP_URL_HOST);
            if ($host !== null) {
                $ips = wp_umbrella_get_service('HostResolver')->resolveDomainName($host, $ipVersion);
                if (!empty($ips)) {
                    $ips = implode(', ', $ips);
                    return ['test' => true];
                }
            }
            return true;
        }

        $code = '';
        $message = '';
        if (is_wp_error($result)) {
            $code = 'wp_remote_post_error';
            $message = 'wp_remote_post() test back to this server failed! Response was: ' . $result->get_error_message();
        } else {
            $code = 'wp_remote_post_failed';
            $message = 'wp_remote_post() test back to this server failed! Response was: ' . '<br>' . $result['response']['code'] . ' ' . $result['response']['message'] . '<br><br>';

            if ($this->_detectBlockedByCloudflare($result)) {
                $code = 'cloudflare_blocking';
                $message .= 'Cloudflare appears to be blocking your site from connecting to itself.';
            }
        }

        return [
            'test' => false,
            'code' => $code,
            'message' => $message,
        ];
    }

    public function trySelfIpv6()
    {
        if (!$this->curlIsSupported()) {
            return [
                'test' => false,
                'code' => 'curl_not_supported',
                'message' => 'Curl is not supported on this server.'
            ];
        }

        $interceptor = wp_umbrella_get_service('CurlInterceptor');
        $interceptor->setOption(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
        try {
            $instance = $this;
            $result = $interceptor->intercept(function () use ($instance) {
                return $instance->connectToSelf(6);
            });

            if ($result !== true && !$result['test']) {
                $handle = $interceptor->getHandle();
                $errorNumber = curl_errno($handle);
                if ($errorNumber === 6 /* COULDNT_RESOLVE_HOST */) {
                    return [
                        'test' => false,
                        'code' => 'ipv6_dns_resolution_failed',
                        'message' => 'DNS resolution for IPv6 failed.'
                    ];
                }
            }
            return $result;
        } catch (\Exception $e) {
            return [
                'test' => false,
                'code' => 'curl_interception_failed',
                'message' => $e->getMessage()
            ];
        }
    }
}
