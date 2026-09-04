<?php
namespace WPUmbrella\Services\Cache;

if (!defined('ABSPATH')) {
    exit;
}

class Varnish
{
    public function purge()
    {
        if (!class_exists('VarnishPurger')) {
            return;
        }
        try {
            $url = home_url('/?vhp-regex');
            $p = wp_parse_url($url);

            if (!is_array($p) || !isset($p['host']) || !is_string($p['host']) || '' === $p['host']) {
                return;
            }

            $path = '';
            $pregex = '.*';

            $hosts = $this->getVarnishHosts();

            if (null === $hosts) {
                return;
            }

            if (isset($p['path'])) {
                $path = $p['path'];
            }

            $schema = apply_filters('varnish_http_purge_schema', 'http://');

            if (0 === count($hosts)) {
                $hosts = [$p['host']];
            }

            foreach ($hosts as $host) {
                wp_remote_request(
                    $schema . $host . $path . $pregex,
                    [
                        'method' => 'PURGE',
                        'blocking' => false,
                        'headers' => [
                            'host' => $p['host'],
                            'X-Purge-Method' => 'regex',
                        ],
                    ]
                );
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @return array|null the configured hosts, an empty array when nothing is
     *                    configured, null when the configured value yields none
     */
    protected function getVarnishHosts()
    {
        $varniship = $this->getConfiguredValue();

        if (null === $varniship) {
            return [];
        }

        if (!is_string($varniship)) {
            return null;
        }

        if ('' === trim($varniship)) {
            return [];
        }

        $hosts = [];

        foreach (explode(',', $varniship) as $entry) {
            $host = $this->normalizeHost($entry);

            if ('' !== $host) {
                $hosts[] = $host;
            }
        }

        if (0 === count($hosts)) {
            return null;
        }

        return $hosts;
    }

    /**
     * The constant wins over the option whenever it holds a value. `false`,
     * `null` and `''` mean the constant is not set, every other value is a
     * configured value and is handed to the host validation as it stands.
     *
     * @return mixed null when neither the constant nor the option holds a value
     */
    protected function getConfiguredValue()
    {
        if (defined('VHP_VARNISH_IP')) {
            $configured = constant('VHP_VARNISH_IP');

            if (false !== $configured && null !== $configured && '' !== $configured) {
                return $configured;
            }
        }

        $option = get_option('vhp_varnish_ip');

        if (false === $option || null === $option) {
            return null;
        }

        return $option;
    }

    /**
     * @param mixed $entry
     *
     * @return string a bare host or IP with an optional port, IPv6 bracketed,
     *                an empty string when the entry is not one
     */
    protected function normalizeHost($entry)
    {
        if (!is_string($entry)) {
            return '';
        }

        $entry = trim($entry);

        if ('' === $entry) {
            return '';
        }

        if (filter_var($entry, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '[' . $entry . ']';
        }

        if (preg_match('/^\[[0-9A-Fa-f:.]+\](:[0-9]{1,5})?\z/', $entry)) {
            return $entry;
        }

        $port = '';

        if (preg_match('/^(.*)(:[0-9]{1,5})\z/', $entry, $matches)) {
            $entry = $matches[1];
            $port = $matches[2];
        }

        $host = $this->toAsciiHost($entry);

        if ('' === $host) {
            return '';
        }

        if (preg_match('/^[0-9]+\z/', $host)) {
            return '';
        }

        if (!preg_match('/^[A-Za-z0-9_]([A-Za-z0-9._-]*[A-Za-z0-9_])?\z/', $host)) {
            return '';
        }

        return $host . $port;
    }

    /**
     * Drops the root label of a fully qualified name and converts an
     * internationalised name to its punycode form. Returns an empty string
     * when the value holds a byte a host name cannot hold, or when the host
     * needs a conversion the runtime cannot perform.
     *
     * @param string $host
     *
     * @return string
     */
    protected function toAsciiHost($host)
    {
        if (preg_match('/[\x00-\x20\x7F]/', $host)) {
            return '';
        }

        if (strlen($host) > 1 && '.' === substr($host, -1)) {
            $host = substr($host, 0, -1);
        }

        if (!preg_match('/[\x80-\xFF]/', $host)) {
            return $host;
        }

        if (!function_exists('idn_to_ascii')) {
            return '';
        }

        $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
        $converted = idn_to_ascii($host, 0, $variant);

        return is_string($converted) ? $converted : '';
    }
}
