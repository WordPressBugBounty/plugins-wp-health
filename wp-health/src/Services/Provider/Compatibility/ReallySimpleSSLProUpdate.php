<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class ReallySimpleSSLProUpdate
{
    public function checkUpdate()
    {
        if (!class_exists('RSSSL_SL_Plugin_Updater')) {
            return null;
        }

        if (!defined('REALLY_SIMPLE_SSL_URL') || !defined('rsssl_version') || !defined('RSSSL_ITEM_ID') || !defined('rsssl_plugin')) {
            return null;
        }

        if (!function_exists('rsssl_get_option')) {
            return null;
        }

        try {
            $license = trim(rsssl_get_option('license'));

            if (empty($license)) {
                return null;
            }

            $licenseKey = $this->decryptLicense($license);

            if (empty($licenseKey)) {
                return null;
            }

            $eddUpdater = new \RSSSL_SL_Plugin_Updater(REALLY_SIMPLE_SSL_URL, rsssl_plugin, [
                'version' => rsssl_version,
                'license' => $licenseKey,
                'author' => 'Really Simple Plugins',
                'item_id' => RSSSL_ITEM_ID,
            ]);

            $transient = new \stdClass();
            $transient->last_checked = time();
            $transient->checked = [];
            $transient->response = [];

            return $eddUpdater->check_update($transient);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function decryptLicense($license)
    {
        if (!class_exists('RSSSL\lib\admin\Encryption')) {
            return $license;
        }

        $encryptor = new \RSSSL\lib\admin\Encryption();

        return $encryptor->decrypt_if_prefixed($license, 'really_simple_ssl_');
    }
}
