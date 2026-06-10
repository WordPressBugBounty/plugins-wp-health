<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Provider\Compatibility\ReallySimpleSSLProUpdate;

class PluginInformationFallback implements ExecuteHooks
{
    public function hooks()
    {
        add_filter('wp_umbrella_plugin_information', [$this, 'resolveReallySimpleSSLPro'], 10, 2);
    }

    public function resolveReallySimpleSSLPro($result, $slug)
    {
        if ($result !== null) {
            return $result;
        }

        if (!defined('rsssl_plugin') || !defined('rsssl_pro')) {
            return $result;
        }

        $pluginSlug = explode('/', $slug)[0] ?? '';
        if ($pluginSlug !== 'really-simple-ssl-pro') {
            return $result;
        }

        return (new ReallySimpleSSLProUpdate())->getPluginInformation();
    }
}
