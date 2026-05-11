<?php

use WPUmbrella\Actions\Admin\Option;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;

if (!defined('ABSPATH')) {
    exit;
}
$options = wp_umbrella_get_service('Option')->getOptions([
    'secure' => false
]);

$apiKey = !empty($options['api_key']) ? Option::SECURED_VALUE : '';
$secretToken = !empty($options['secret_token']) ? Option::SECURED_VALUE : '';
$projectId = !empty($options['project_id']) ? Option::SECURED_VALUE : '';

$activityLogIntervalMinutes = (int) ceil(SyncScheduler::resolveInterval() / 60);
$activityLogIntervalMin = (int) ceil(SyncScheduler::MIN_INTERVAL_SECONDS / 60);
$activityLogIntervalMax = (int) floor(SyncScheduler::MAX_INTERVAL_SECONDS / 60);

global $wpdb;
$redirectsTableName = $wpdb->prefix . 'umbrella_redirects';

$redirectsCount = 0;

if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redirectsTableName)) === $redirectsTableName) {
    $redirectsCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$redirectsTableName}`");
}

$activityLogBufferTableName = $wpdb->prefix . 'umbrella_activity_log_buffer';
$activityLogBufferCount = 0;

if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $activityLogBufferTableName)) === $activityLogBufferTableName) {
    $activityLogBufferCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$activityLogBufferTableName}`");
}

?>

<style>
	.wrap.wpu-support-page {
		padding: 20px 20px 20px 20px;
	}
	.wpu-support-wrap {
		max-width: 800px;
		margin-top: 20px;
	}
	.wpu-support-section {
		background: #fff;
		border: 1px solid #c3c4c7;
		border-radius: 4px;
		padding: 20px 24px;
		margin-bottom: 24px;
	}
	.wpu-support-section h2 {
		margin: 0 0 12px;
		padding: 0;
		font-size: 16px;
		font-weight: 600;
		border-bottom: 1px solid #e0e0e0;
		padding-bottom: 12px;
	}
	.wpu-support-section .form-table th {
		width: 200px;
	}
	.wpu-support-action {
		display: flex;
		align-items: flex-start;
		gap: 16px;
		padding: 12px 0;
	}
	.wpu-support-action + .wpu-support-action {
		border-top: 1px solid #f0f0f0;
	}
	.wpu-support-action-info {
		flex: 1;
	}
	.wpu-support-action-info strong {
		display: block;
		margin-bottom: 4px;
	}
	.wpu-support-action-info .description {
		margin-top: 0;
	}
	.wpu-support-action-btn {
		flex-shrink: 0;
		padding-top: 2px;
	}
</style>

<div class="wrap wpu-support-page">
	<h1><?php echo esc_html__('WP Umbrella — Support', 'wp-health'); ?></h1>

	<div class="wpu-support-wrap">

	<!-- Settings -->
	<div class="wpu-support-section">
		<h2><?php echo esc_html__('Settings', 'wp-health'); ?></h2>
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="api_key"><?php echo esc_html__('API Key', 'wp-health'); ?></label></th>
						<td>
							<input name="api_key" type="password" id="api_key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text">
							<p class="description"><strong><?php echo esc_html__('Do not change this value unless you know what you are doing.', 'wp-health'); ?></strong> <?php echo esc_html__('Authentication key that links this WordPress site to your WP Umbrella account. Auto-generated when the site is connected.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="secret_token"><?php echo esc_html__('Secret Token', 'wp-health'); ?></label></th>
						<td>
							<input name="secret_token" type="password" id="secret_token" value="<?php echo esc_attr($secretToken); ?>" class="regular-text">
							<p class="description"><strong><?php echo esc_html__('Do not change this value unless you know what you are doing.', 'wp-health'); ?></strong> <?php echo esc_html__('Per-site secret used to verify requests coming from WP Umbrella. Auto-generated when the site is connected.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="project_id"><?php echo esc_html__('Project ID', 'wp-health'); ?></label></th>
						<td>
							<input name="project_id" type="password" id="project_id" value="<?php echo esc_attr($projectId); ?>" class="regular-text">
							<p class="description"><strong><?php echo esc_html__('Do not change this value unless you know what you are doing.', 'wp-health'); ?></strong> <?php echo esc_html__('Unique identifier of this site in your WP Umbrella account. Auto-assigned when the site is connected.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_health_allow_tracking"><?php echo esc_html__('Allow tracking errors', 'wp-health'); ?></label></th>
						<td>
							<input name="wp_health_allow_tracking" type="checkbox" id="wp_health_allow_tracking" value="1" <?php checked(get_option('wp_health_allow_tracking'), '1'); ?>>
							<p class="description"><?php echo esc_html__('Sends PHP errors and warnings detected on this site to your WP Umbrella dashboard for monitoring and debugging.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_umbrella_disallow_one_click_access"><?php echo esc_html__('Allow 1-click access', 'wp-health'); ?></label></th>
						<td>
							<input name="wp_umbrella_disallow_one_click_access" type="checkbox" id="wp_umbrella_disallow_one_click_access" value="1" <?php checked(get_option('wp_umbrella_disallow_one_click_access'), false); ?>>
							<p class="description"><?php echo esc_html__('Lets your WP Umbrella account open the WordPress admin of this site with a single click from the dashboard, without typing a password.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_umbrella_activity_log_enabled"><?php echo esc_html__('Enable activity log', 'wp-health'); ?></label></th>
						<td>
							<input name="wp_umbrella_activity_log_enabled" type="checkbox" id="wp_umbrella_activity_log_enabled" value="1" <?php checked((bool) get_option('wp_umbrella_activity_log_enabled'), true); ?>>
							<p class="description"><?php echo esc_html__('Records meaningful WordPress events (logins, content changes, plugin/theme/user lifecycle, option changes) and syncs them to your WP Umbrella dashboard.', 'wp-health'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_umbrella_activity_log_sync_interval_minutes"><?php echo esc_html__('Activity log sync interval (minutes)', 'wp-health'); ?></label></th>
						<td>
							<input name="wp_umbrella_activity_log_sync_interval_minutes" type="number" id="wp_umbrella_activity_log_sync_interval_minutes" min="<?php echo esc_attr($activityLogIntervalMin); ?>" max="<?php echo esc_attr($activityLogIntervalMax); ?>" step="1" value="<?php echo esc_attr($activityLogIntervalMinutes); ?>" class="small-text">
							<p class="description"><?php echo esc_html(sprintf(__('How often the plugin sends buffered events to WP Umbrella. Default: 5 minutes. Min: %1$d, max: %2$d.', 'wp-health'), $activityLogIntervalMin, $activityLogIntervalMax)); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php wp_nonce_field('wp_umbrella_support_option'); ?>
			<input type="hidden" name="action" value="wp_umbrella_support_option" />
			<?php submit_button(); ?>
		</form>
	</div>

	<!-- Maintenance -->
	<div class="wpu-support-section">
		<h2><?php echo esc_html__('Maintenance', 'wp-health'); ?></h2>
		<div class="wpu-support-action">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Regenerate Secret Token', 'wp-health'); ?></strong>
				<p class="description"><?php echo esc_html__('Generate a new secret token and reconnect the site to WP Umbrella.', 'wp-health'); ?></p>
			</div>
			<div class="wpu-support-action-btn">
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
					<?php wp_nonce_field('wp_umbrella_regenerate_secret_token'); ?>
					<input type="hidden" name="action" value="wp_umbrella_regenerate_secret_token" />
					<?php submit_button(esc_html__('Regenerate', 'wp-health'), 'secondary', 'submit', false); ?>
				</form>
			</div>
		</div>
		<div class="wpu-support-action">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Clean redirects', 'wp-health'); ?></strong>
				<p class="description">
					<?php echo esc_html__('Remove all redirect rules. They will be re-synced from WP Umbrella.', 'wp-health'); ?>
					<br /><strong><?php echo esc_html(sprintf(__('%d redirect rules', 'wp-health'), $redirectsCount)); ?></strong>
				</p>
			</div>
			<div class="wpu-support-action-btn">
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
					<?php wp_nonce_field('wp_umbrella_clean_redirect_table'); ?>
					<input type="hidden" name="action" value="wp_umbrella_clean_redirect_table" />
					<?php submit_button(esc_html__('Clean', 'wp-health'), 'delete', 'submit', false); ?>
				</form>
			</div>
		</div>
		<div class="wpu-support-action">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Clean activity log buffer', 'wp-health'); ?></strong>
				<p class="description">
					<?php echo esc_html__('Drop all events currently buffered on this site that have not been synced to WP Umbrella yet. Cleared events are lost.', 'wp-health'); ?>
					<br /><strong><?php echo esc_html(sprintf(__('%d buffered events', 'wp-health'), $activityLogBufferCount)); ?></strong>
				</p>
			</div>
			<div class="wpu-support-action-btn">
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
					<?php wp_nonce_field('wp_umbrella_clean_activity_log_buffer'); ?>
					<input type="hidden" name="action" value="wp_umbrella_clean_activity_log_buffer" />
					<?php submit_button(esc_html__('Clean', 'wp-health'), 'delete', 'submit', false); ?>
				</form>
			</div>
		</div>
	</div>

	</div>

</div>
