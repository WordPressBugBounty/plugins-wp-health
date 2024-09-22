<?php

use WPUmbrella\Core\Constants\BackupStatus;
use WPUmbrella\Services\Repository\BackupRepository;
use WPUmbrella\Services\Repository\TaskBackupRepository;
use WPUmbrella\Actions\Admin\Option;

if (!defined('ABSPATH')) {
    exit;
}
$options = wp_umbrella_get_service('Option')->getOptions([
    'secure' => false
]);

$apiKey = !empty($options['api_key']) ? Option::SECURED_VALUE : '';
$secretToken = !empty($options['secret_token']) ? Option::SECURED_VALUE : '';
$projectId = !empty($options['project_id']) ? Option::SECURED_VALUE : '';

?>


<div id="wrap-wphealth">
	<div class="wrap">
		<div class="module-wp-health">
			<h1>WP Umbrella Settings - Support</h1>
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="api_key">API Key</label></th>
							<td>
								<input name="api_key" type="password" id="api_key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="secret_token">Secret Token</label></th>
							<td>
								<input name="secret_token" type="password" id="secret_token" value="<?php echo esc_attr($secretToken); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="project_id">Project ID</label></th>
							<td>
								<input name="project_id" type="password" id="project_id" value="<?php echo esc_attr($projectId); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp_health_allow_tracking">Allow tracking errors</label></th>
							<td>
								<input name="wp_health_allow_tracking" type="checkbox" id="wp_health_allow_tracking" value="1" <?php checked(get_option('wp_health_allow_tracking'), '1'); ?>>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wp_umbrella_disallow_one_click_access">Allow 1-click access</label></th>
							<td>

								<input name="wp_umbrella_disallow_one_click_access" type="checkbox" id="wp_umbrella_disallow_one_click_access" value="1" <?php checked(get_option('wp_umbrella_disallow_one_click_access'), false); ?>>
							</td>
						</tr>
					</tbody>
				</table>

				<?php wp_nonce_field('wp_umbrella_support_option'); ?>
				<input type="hidden" name="action" value="wp_umbrella_support_option" />
				<?php submit_button(); ?>
			</form>

			<h2 class="font-bold text-lg mb-2">WP Umbrella Regenerate Secret Token</h2>
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
				<?php wp_nonce_field('wp_umbrella_regenerate_secret_token'); ?>
				<input type="hidden" name="action" value="wp_umbrella_regenerate_secret_token" />
				<?php submit_button('Regenerate Secret Token'); ?>
			</form>
			<h2 class="font-bold text-lg mb-2">Reset backup</h2>
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
				<?php wp_nonce_field('wp_umbrella_reset_backup'); ?>
				<input type="hidden" name="action" value="wp_umbrella_reset_backup" />
				<?php submit_button('Reset backup'); ?>
			</form>
		</div>
	</div>
</div>
