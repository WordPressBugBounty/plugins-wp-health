<?php

use WPUmbrella\Actions\Admin\Option;
use WPUmbrella\Actions\Admin\TestPingWorker;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;

if (!defined('ABSPATH')) {
    exit;
}
$options = wp_umbrella_get_service('Option')->getOptions([
    'secure' => false
]);

$secretToken = !empty($options['secret_token']) ? Option::SECURED_VALUE : '';
$projectId = !empty($options['project_id']) ? Option::SECURED_VALUE : '';
$requestToken = !empty($options['request_token']) ? Option::SECURED_VALUE : '';
$hasApiKey = !empty($options['api_key']);
$hasRequestToken = !empty($options['request_token']);

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
				<tbody>					<tr>
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
						<th scope="row"><label for="request_token"><?php echo esc_html__('Request Token', 'wp-health'); ?></label></th>
						<td>
							<input name="request_token" type="password" id="request_token" value="<?php echo esc_attr($requestToken); ?>" class="regular-text">
							<p class="description"><strong><?php echo esc_html__('Do not change this value unless you know what you are doing.', 'wp-health'); ?></strong> <?php echo esc_html__('Per-site bearer token used to authenticate plugin → WP Umbrella requests after pairing. Auto-issued by WP Umbrella; leave blank to clear and trigger a new pairing.', 'wp-health'); ?></p>
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

	<!-- Diagnostics -->
	<?php $lastPing = TestPingWorker::getLastResult(); ?>
	<div class="wpu-support-section" id="wpu-test-ping">
		<h2><?php echo esc_html__('Diagnostics', 'wp-health'); ?></h2>
		<div class="wpu-support-action">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Test ping', 'wp-health'); ?></strong>
				<p class="description">
					<?php echo esc_html__('Send a signed request to WP Umbrella and report the HTTP status and round-trip time. Useful when you suspect a connection or authentication issue.', 'wp-health'); ?>
				</p>
				<?php if (is_array($lastPing)) : ?>
					<?php
                    $isOk = isset($lastPing['status']) && $lastPing['status'] === 'ok';
                    $ts = isset($lastPing['timestamp']) ? (int) $lastPing['timestamp'] : 0;
                    $bg = $isOk ? '#edf7ed' : '#fdecea';
                    $border = $isOk ? '#46b450' : '#dc3232';
                    $reason = isset($lastPing['reason']) ? (string) $lastPing['reason'] : '';
                    $httpCode = (int) ($lastPing['http_code'] ?? 0);
                    $durationMs = (int) ($lastPing['duration_ms'] ?? 0);

                    if ($isOk) {
                        $title = sprintf(__('OK — HTTP %1$d in %2$d ms', 'wp-health'), $httpCode, $durationMs);
                    } elseif ($reason === 'not_connected') {
                        $title = __('Not connected', 'wp-health');
                    } elseif ($reason === 'transport') {
                        $title = __('Network error', 'wp-health');
                    } elseif (in_array($reason, ['missing_nonce', 'invalid_nonce', 'forbidden'], true)) {
                        $title = __('Request rejected', 'wp-health');
                    } else {
                        $title = sprintf(__('HTTP %1$d in %2$d ms', 'wp-health'), $httpCode, $durationMs);
                    }
                    ?>
					<div style="margin: 12px 0 0; padding: 10px 12px; background: <?php echo esc_attr($bg); ?>; border-left: 4px solid <?php echo esc_attr($border); ?>;">
						<p style="margin: 0;">
							<strong><?php echo esc_html($title); ?></strong>
							<?php if (!empty($lastPing['error_code'])) : ?>
								— <code><?php echo esc_html($lastPing['error_code']); ?></code>
							<?php endif; ?>
							<?php if (!empty($lastPing['message'])) : ?>
								<br /><?php echo esc_html($lastPing['message']); ?>
							<?php endif; ?>
						</p>
						<?php if ($ts > 0) : ?>
							<p style="margin: 4px 0 0; font-size: 11px; color: #646970;">
								<?php echo esc_html(sprintf(__('Ran %s ago', 'wp-health'), human_time_diff($ts))); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<p style="margin: 8px 0 0; font-size: 12px; color: #646970;">
						<?php echo esc_html__('No ping run yet.', 'wp-health'); ?>
					</p>
				<?php endif; ?>
			</div>
			<div class="wpu-support-action-btn">
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
					<?php wp_nonce_field(TestPingWorker::NONCE); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr(TestPingWorker::ACTION); ?>" />
					<?php submit_button(esc_html__('Run ping', 'wp-health'), 'secondary', 'submit', false); ?>
				</form>
			</div>
		</div>
	</div>

	<!-- Maintenance -->
	<div class="wpu-support-section">
		<h2><?php echo esc_html__('Maintenance', 'wp-health'); ?></h2>
		<div class="wpu-support-action" id="wpu-repair" data-paired="<?php echo $hasRequestToken ? '1' : '0'; ?>" data-has-api-key="<?php echo $hasApiKey ? '1' : '0'; ?>">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Reconnect to WP Umbrella', 'wp-health'); ?></strong>
				<p class="description wpu-repair-message">
					<?php if ($hasRequestToken) : ?>
						<?php echo esc_html__('This site is connected to WP Umbrella.', 'wp-health'); ?>
					<?php elseif ($hasApiKey) : ?>
						<?php echo esc_html__('This site has a valid API key but is not yet connected. Click to retry the connection.', 'wp-health'); ?>
					<?php else : ?>
						<?php echo esc_html__('This site has no API key configured. Reinstall WP Umbrella from your account dashboard.', 'wp-health'); ?>
					<?php endif; ?>
				</p>
			</div>
			<div class="wpu-support-action-btn">
				<button type="button" class="button button-secondary wpu-repair-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_umbrella_repair_ajax')); ?>" <?php disabled(true, !$hasApiKey || $hasRequestToken); ?>>
					<span class="wpu-repair-btn-label"><?php echo esc_html($hasRequestToken ? __('Connected', 'wp-health') : __('Reconnect', 'wp-health')); ?></span>
					<span class="spinner wpu-repair-spinner" style="float:none;display:none;margin:0 0 0 6px;visibility:visible;"></span>
				</button>
			</div>
		</div>
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

<script>
(function () {
	var container = document.getElementById('wpu-repair');
	if (!container) { return; }
	var btn = container.querySelector('.wpu-repair-btn');
	if (!btn) { return; }
	var spinner = container.querySelector('.wpu-repair-spinner');
	var label = container.querySelector('.wpu-repair-btn-label');
	var message = container.querySelector('.wpu-repair-message');

	btn.addEventListener('click', function () {
		if (btn.disabled) { return; }
		btn.disabled = true;
		spinner.style.display = 'inline-block';
		var originalLabel = label.textContent;
		label.textContent = <?php echo wp_json_encode(__('Connecting...', 'wp-health')); ?>;

		var data = new FormData();
		data.append('action', 'wp_umbrella_repair_ajax');
		data.append('_ajax_nonce', btn.dataset.nonce);

		fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json().catch(function () { return { success: false }; }); })
			.then(function (json) {
				spinner.style.display = 'none';
				if (json && json.success) {
					label.textContent = <?php echo wp_json_encode(__('Connected', 'wp-health')); ?>;
					message.textContent = <?php echo wp_json_encode(__('This site is connected to WP Umbrella.', 'wp-health')); ?>;
					btn.disabled = true;
				} else {
					var reason = json && json.data && json.data.message
						? json.data.message
						: <?php echo wp_json_encode(__('Reconnection failed. The site stays connected via the legacy path; you can retry.', 'wp-health')); ?>;
					message.textContent = reason;
					label.textContent = originalLabel;
					btn.disabled = false;
				}
			})
			.catch(function () {
				spinner.style.display = 'none';
				message.textContent = <?php echo wp_json_encode(__('Network error during reconnection.', 'wp-health')); ?>;
				label.textContent = originalLabel;
				btn.disabled = false;
			});
	});
})();
</script>
