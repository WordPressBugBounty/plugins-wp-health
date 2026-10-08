<?php
namespace WPUmbrella\Services\Tls;

if (!defined('ABSPATH')) {
    exit;
}

class CertificateFallback
{
    const OPTION_KEY = 'wp_umbrella_tls_fallback';

    const CONNECTION_ARG = 'wp_umbrella_connection';

    const MAX_ERROR_LENGTH = 300;

    /**
     * @return boolean
     */
    public function isActive()
    {
        return is_array(get_option(self::OPTION_KEY));
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
     * @param string $error
     * @return void
     */
    public function record($error)
    {
        update_option(self::OPTION_KEY, [
            'recorded_at' => gmdate('c'),
            'error' => substr((string) $error, 0, self::MAX_ERROR_LENGTH),
            'plugin_version' => defined('WP_UMBRELLA_VERSION') ? WP_UMBRELLA_VERSION : null,
        ], true);
    }

    /**
     * @return void
     */
    public function clear()
    {
        delete_option(self::OPTION_KEY);
    }

    /**
     * @return string|null
     */
    public function getBundlePath()
    {
        $path = WP_UMBRELLA_DIR . '/certificates/cacert.pem';

        if (!file_exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param string $message
     * @return boolean
     */
    public function isCertificateError($message)
    {
        $message = strtolower((string) $message);

        if (preg_match('/curl error (60|77|83):/', $message)) {
            return true;
        }

        return strpos($message, 'certificate') !== false;
    }

    /**
     * @param string $message
     * @return boolean
     */
    public function isHostnameMismatch($message)
    {
        $message = strtolower((string) $message);

        foreach (['subject name', 'subjectaltname', 'does not match target host'] as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
