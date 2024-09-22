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
            $path = '';
            $pregex = '.*';

            // Build a varniship.
            if (defined('VHP_VARNISH_IP') && VHP_VARNISH_IP) {
                $varniship = VHP_VARNISH_IP;
            } else {
                $varniship = get_option('vhp_varnish_ip');
            }

            if (isset($p['path'])) {
                $path = $p['path'];
            }

            $schema = apply_filters('varnish_http_purge_schema', 'http://');

            // If we made varniship, let it sail.
            if (!empty($varniship)) {
                $purgeme = $schema . $varniship . $path . $pregex;
            } else {
                $purgeme = $schema . $p['host'] . $path . $pregex;
            }

            wp_remote_request(
                $purgeme,
                [
                    'method' => 'PURGE',
                    'blocking' => false,
                    'headers' => [
                        'host' => $p['host'],
                        'X-Purge-Method' => 'regex',
                    ],
                ]
            );
        } catch (\Exception $e) {
        }
    }
}
