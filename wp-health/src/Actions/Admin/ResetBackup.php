<?php

namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Core\Hooks\DeactivationHook;

class ResetBackup implements ExecuteHooksBackend
{
    public function hooks()
    {
		add_action('admin_post_wp_umbrella_reset_backup', [$this, 'handle']);
    }


	public function handle(){
		if(!isset($_POST['_wpnonce'])){
			wp_redirect(admin_url());
			return;
		}

		if (!current_user_can('manage_options')) {
            wp_redirect(admin_url());
			return;
        }

		if(!wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_reset_backup')){
			wp_redirect(admin_url());
			return;
		}


		wp_umbrella_get_service('BackupManageProcess')->unscheduledBatch();

		wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
		return;
	}

}
