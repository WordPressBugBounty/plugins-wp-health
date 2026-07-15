<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class HardeningOptions implements ExecuteHooksBackend
{
    const ACTION = 'wp_umbrella_hardening_options';

    const NONCE = 'wp_umbrella_hardening_options';

    public function hooks()
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
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

        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            wp_redirect(admin_url());
            return;
        }

        $hardening = wp_umbrella_get_service('HardeningSettings');
        $submitted = isset($_POST['hardening']) && is_array($_POST['hardening']) ? $_POST['hardening'] : [];

        $params = [];

        foreach (array_keys($hardening->getDefaultSettings()) as $key) {
            $params[$key] = isset($submitted[$key]) && $submitted[$key] === '1';
        }

        $hardening->updateSettings($params);

        wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
        return;
    }
}
