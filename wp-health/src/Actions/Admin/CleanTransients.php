<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class CleanTransients implements ExecuteHooksBackend
{
    const TRANSIENT_KEYS = [
        'wp_umbrella_white_label_data_cache',
    ];

    public function hooks()
    {
        add_action('admin_post_wp_umbrella_clean_transients', [$this, 'handle']);
    }

    public function handle()
    {
        if (!isset($_POST['_wpnonce'])) {
            wp_redirect(admin_url());
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_redirect(admin_url());
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_clean_transients')) {
            wp_redirect(admin_url());
            return;
        }

        foreach (self::TRANSIENT_KEYS as $key) {
            delete_transient($key);
        }

        delete_site_transient('php_check_' . md5(PHP_VERSION));

        wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
        return;
    }
}
