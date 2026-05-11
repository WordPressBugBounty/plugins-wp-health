<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Actions\ActivityLog\Framework\EventBuffer;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class CleanActivityLogBuffer implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('admin_post_wp_umbrella_clean_activity_log_buffer', [$this, 'handle']);
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

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_clean_activity_log_buffer')) {
            wp_redirect(admin_url());
            return;
        }

        (new EventBuffer())->clear();

        wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
        return;
    }
}
