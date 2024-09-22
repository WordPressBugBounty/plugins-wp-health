<?php
namespace WPUmbrella\Services\Provider\Compatibility;

use WPUmbrella\Services\Provider\Compatibility\SecuPressProAdminUpdater;

class SecuPressProUpdate
{
    public function checkUpdate()
    {
        if (!defined('SECUPRESS_WEB_MAIN') || !defined('SECUPRESS_FILE') || !defined('SECUPRESS_PRO_VERSION')) {
            return;
        }

        if (!function_exists('secupress_get_consumer_key')) {
            return;
        }

        try {
            $edd_updater = new SecuPressProAdminUpdater(
                SECUPRESS_WEB_MAIN,
                SECUPRESS_FILE,
                [
                    'version' => SECUPRESS_PRO_VERSION,
                    'license' => secupress_get_consumer_key(),
                    'item_name' => 'SecuPress',
                    'author' => 'SecuPress.me',
                    'url' => home_url(),
                ]
            );

            return $edd_updater->check_update();
        } catch (\Exception $e) {
            return;
        }
    }
}
