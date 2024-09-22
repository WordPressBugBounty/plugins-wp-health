<?php

namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class PrepareErrorHandler implements ExecuteHooksBackend, DeactivationHook
{
    public function hooks()
    {
        $allowTracking = get_option('wp_health_allow_tracking');
        if (!$allowTracking) {
            return;
        }

		$versionGodHandler = get_option('wp_health_version_god_handler');
		if(file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php') && !$versionGodHandler){ // Prevent file copy manually
			update_option('wp_health_version_god_handler', WP_UMBRELLA_GOD_HANDLER_VERSION);
		}


        if (version_compare(WP_UMBRELLA_GOD_HANDLER_VERSION, $versionGodHandler) > 0 && !file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')) {
            $result = $this->createHandler();
            if ($result) {
                update_option('wp_health_version_god_handler', WP_UMBRELLA_GOD_HANDLER_VERSION);
            }
        }
    }

    protected function createHandler()
    {
        if (!file_exists(WPMU_PLUGIN_DIR) && !is_writable(dirname(WPMU_PLUGIN_DIR)) && !file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')) {
            add_action('admin_notices', [__CLASS__, 'adminNoticeNotWritable']);

            return false;
        }

        if (!file_exists(WPMU_PLUGIN_DIR) && is_writable(dirname(WPMU_PLUGIN_DIR))) {
            wp_mkdir_p(WPMU_PLUGIN_DIR);
        }

        try {
            if (!@copy(
                WP_UMBRELLA_DIR . '/src/God/_WPHealthHandlerMU.php',
                WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php'
            )) {
                add_action('admin_notices', [__CLASS__, 'adminNotice']);

                return false;
            }
        } catch (\Exception $e) {
            add_action('admin_notices', [__CLASS__, 'adminNoticeCopy']);

            return false;
        }

        return true;
    }

    public function deactivate()
    {
        delete_option('wp_health_version_god_handler');

        if (!file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')) {
            return;
        }

        if (!is_writable(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')) {
            return;
        }

        wp_umbrella_remove_file(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php');
    }

    public static function adminNoticeNotWritable()
    {
        echo '<div class="notice error is-dismissible">';
        echo '<p>' . esc_html(__('We have detected that it is impossible to write to your "mu-plugins" folder in your installation. In order for us to work properly, you need to change the rights to this folder. You can contact us at support for more information: support@wp-umbrella.com', 'wp-health')) . '</p>';

        echo '</div>';
    }

    public static function adminNoticeCopy()
    {
        echo '<div class="notice error is-dismissible">';
        echo '<p>' . esc_html(__('An error occurred while trying to create a mu-plugin. Your host seems to restrict write permissions. Please contact: support@wp-umbrella.com', 'wp-health')) . '</p>';

        echo '</div>';
    }
    public static function adminNotice()
    {
        echo '<div class="notice error is-dismissible">';
        echo '<p>' . esc_html(__('An error was caused when WP Umbrella attempted to create a file in your directory mu-plugins. Please contact: support@wp-umbrella.com', 'wp-health')) . '</p>';

        echo '</div>';
    }
}
