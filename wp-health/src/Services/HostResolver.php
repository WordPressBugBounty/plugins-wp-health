<?php
namespace WPUmbrella\Services;

use WPUmbrella\Helpers\Host;

class HostResolver
{
    public function getCurrentHost()
    {
        try {
            if (isset($_SERVER['KINSTA_CACHE_ZONE'])) {
                return Host::KINSTA;
            }

            if ((defined('DB_HOST') && strpos(DB_HOST, '.wpserveur.net') !== false)) {
                return Host::WP_SERVER;
            }

            if (class_exists('FlywheelNginxCompat') || wp_umbrella_get_service('WordPressContext')->hasConstant('FLYWHEEL_CONFIG_DIR')) {
                return Host::FLYWHEEL;
            }

            if (isset($_SERVER['cw_allowed_ip'])) {
                return Host::CLOUDWAYS;
            }

            if (wp_umbrella_get_service('WordPressContext')->getConstant('IS_PRESSABLE')) {
                return Host::PRESSABLE;
            }

            if (getenv('SPINUPWP_CACHE_PATH')) {
                return Host::SPINUPWP;
            }

            if ((class_exists('WpeCommon') && function_exists('wpe_param'))) {
                return Host::WPENGINE;
            }

            if (wp_umbrella_get_service('WordPressContext')->hasConstant('O2SWITCH_VARNISH_PURGE_KEY')) {
                return Host::O2SW;
            }

            if (wp_umbrella_get_service('WordPressContext')->getConstant('WPCOMSH_VERSION')) {
                return Host::WORDPRESSCOM;
            }

            if (
                wp_umbrella_get_service('WordPressContext')->getConstant('\Savvii\CacheFlusherPlugin::NAME_FLUSH_NOW')
                &&
                wp_umbrella_get_service('WordPressContext')->getConstant('\Savvii\CacheFlusherPlugin::NAME_DOMAINFLUSH_NOW')
            ) {
                return Host::SAVVII;
            }

            if ($this->isDreampress()) {
                return Host::DREAMPRESS;
            }

            if (class_exists('\WPaas\Plugin')) {
                return Host::GODADDY;
            }

            $hostname = function_exists('gethostname') ? gethostname() : Host::OTHER;
            return $hostname;
        } catch (\Exception $e) {
            return Host::OTHER;
        }
    }

    protected function isDreampress()
    {
        if (!isset($_SERVER['DH_USER'])) {
            return false;
        }

        return 'wp_' === substr(sanitize_key(wp_unslash($_SERVER['DH_USER'])), 0, 3);
    }

    /**
     * @param string $host
     * @return array
     */
    public function resolveDomainName($host, $ipVersion = null)
    {
        if (!function_exists('dns_get_record')) {
            if ($ipVersion === 4 || $ipVersion === null) {
                $ips = gethostbynamel($host);
                if ($ips !== false) {
                    return $ips;
                }
            }
            return [];
        }
        $recordTypes = [];
        if ($ipVersion === 4 || $ipVersion === null) {
            $recordTypes[DNS_A] = 'ip';
        }
        if ($ipVersion === 6 || $ipVersion === null) {
            $recordTypes[DNS_AAAA] = 'ipv6';
        }
        $ips = [];
        foreach ($recordTypes as $type => $key) {
            $records = @dns_get_record($host, $type);
            if ($records !== false) {
                foreach ($records as $record) {
                    $ips[] = $record[$key];
                }
            }
        }
        return $ips;
    }
}
