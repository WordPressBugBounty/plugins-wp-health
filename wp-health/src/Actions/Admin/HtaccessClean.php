<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class HtaccessClean implements ExecuteHooksBackend
{
    const ACTION = 'wp_umbrella_clean_htaccess';

    const NONCE = 'wp_umbrella_clean_htaccess';

    const OPTION_KEY = 'wp_umbrella_last_htaccess_clean';

    public function hooks()
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle()
    {
        $redirect = admin_url('options-general.php?page=wp-umbrella-settings&support=1#wpu-htaccess');

        if (!isset($_POST['_wpnonce'])) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (!current_user_can('manage_options')) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            wp_safe_redirect($redirect);
            exit;
        }

        $result = wp_umbrella_get_service('HtaccessFile')->cleanUmbrellaBlock();
        $this->storeResult($result);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function getLastResult()
    {
        wp_cache_delete(self::OPTION_KEY, 'options');
        $value = get_option(self::OPTION_KEY);

        if (!is_array($value)) {
            return null;
        }

        delete_option(self::OPTION_KEY);

        return $value;
    }

    protected function storeResult(array $result)
    {
        $result['timestamp'] = time();
        update_option(self::OPTION_KEY, $result, false);
    }
}
