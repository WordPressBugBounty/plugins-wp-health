<?php

use WPUmbrella\Actions\Admin\Option;
use WPUmbrella\Actions\Admin\TestPingWorker;
use WPUmbrella\Actions\Admin\HardeningOptions;
use WPUmbrella\Actions\Admin\HtaccessClean;
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

$keyState = wp_umbrella_get_key_state();
$publicKey = wp_umbrella_get_public_key();
$keyId = wp_umbrella_get_key_id();
$isSignedSystem = in_array($keyState, ['dual', 'new'], true) && !empty($publicKey);

$activityLogIntervalMinutes = (int) ceil(SyncScheduler::resolveInterval() / 60);
$activityLogIntervalMin = (int) ceil(SyncScheduler::MIN_INTERVAL_SECONDS / 60);
$activityLogIntervalMax = (int) floor(SyncScheduler::MAX_INTERVAL_SECONDS / 60);

$hardeningStates = wp_umbrella_get_service('HardeningSettings')->getStates();
$hardeningOptions = [
    'hide_wp_version' => [
        __('Hide WordPress version', 'wp-health'),
        __('Removes the WordPress version from the site markup and feeds.', 'wp-health'),
    ],
    'block_user_enumeration' => [
        __('Block user enumeration', 'wp-health'),
        __('Blocks author scans that try to list your usernames.', 'wp-health'),
    ],
    'mask_login_errors' => [
        __('Mask login errors', 'wp-health'),
        __('Returns a generic message instead of revealing whether the username or the password was wrong.', 'wp-health'),
    ],
    'disable_file_editor' => [
        __('Disable file editor', 'wp-health'),
        __('Turns off the built-in theme and plugin file editor in wp-admin.', 'wp-health'),
    ],
    'security_headers' => [
        __('Security headers', 'wp-health'),
        __('Adds recommended HTTP security headers to responses.', 'wp-health'),
    ],
    'login_rate_limit' => [
        __('Login rate limiting', 'wp-health'),
        __('Temporarily blocks an IP address after 8 failed login attempts within 5 minutes.', 'wp-health'),
    ],
    'login_ip_blocklist' => [
        __('Community login IP blocklist', 'wp-health'),
        __('Blocks IP addresses flagged for brute-forcing logins across the WP Umbrella network from reaching your login page.', 'wp-health'),
    ],
    'disable_file_mods' => [
        __('Block plugin and theme changes', 'wp-health'),
        __('Blocks installing, updating or editing plugins and themes from wp-admin. Manage updates through WP Umbrella instead.', 'wp-health'),
    ],
    'disable_xmlrpc' => [
        __('Disable XML-RPC', 'wp-health'),
        __('Turns off XML-RPC and pingbacks. Leave disabled unless this site uses the WordPress mobile app or Jetpack.', 'wp-health'),
    ],
];

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
					<?php if ($isSignedSystem) : ?>
					<tr>
						<th scope="row"><label for="signing_public_key"><?php echo esc_html__('Public Key', 'wp-health'); ?></label></th>
						<td>
							<input type="text" id="signing_public_key" value="<?php echo esc_attr($publicKey); ?>" class="regular-text code" readonly>
							<p class="description">
								<?php echo esc_html__('This site verifies that requests genuinely come from WP Umbrella using this public key. It is issued and managed automatically, there is nothing to configure here.', 'wp-health'); ?>
								<?php if (!empty($keyId)) : ?>
									<br><?php echo esc_html(sprintf(__('Key ID: %s', 'wp-health'), $keyId)); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<?php else : ?>
					<tr>
						<th scope="row"><label for="request_token"><?php echo esc_html__('Request Token', 'wp-health'); ?></label></th>
						<td>
							<input name="request_token" type="password" id="request_token" value="<?php echo esc_attr($requestToken); ?>" class="regular-text">
							<p class="description"><strong><?php echo esc_html__('Do not change this value unless you know what you are doing.', 'wp-health'); ?></strong> <?php echo esc_html__('Per-site bearer token used to authenticate plugin requests to WP Umbrella after pairing. Auto-issued by WP Umbrella; leave blank to clear and trigger a new pairing.', 'wp-health'); ?></p>
						</td>
					</tr>
					<?php endif; ?>
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

	<!-- Hardening -->
	<div class="wpu-support-section">
		<h2><?php echo esc_html__('Hardening', 'wp-health'); ?></h2>
		<p class="description" style="margin-top:0;"><?php echo esc_html__('Current state of the security hardening options on this site. Uncheck an option and save to disable it if it is causing trouble.', 'wp-health'); ?></p>
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ($hardeningOptions as $hardeningKey => $hardeningMeta) : ?>
					<?php $hardeningEnabled = !empty($hardeningStates[$hardeningKey]); ?>
					<tr>
						<th scope="row"><label for="hardening_<?php echo esc_attr($hardeningKey); ?>"><?php echo esc_html($hardeningMeta[0]); ?></label></th>
						<td>
							<label>
								<input name="hardening[<?php echo esc_attr($hardeningKey); ?>]" type="checkbox" id="hardening_<?php echo esc_attr($hardeningKey); ?>" value="1" <?php checked($hardeningEnabled, true); ?>>
								<span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;<?php echo $hardeningEnabled ? 'background:#edf7ed;color:#1e6b23;' : 'background:#f0f0f1;color:#646970;'; ?>"><?php echo $hardeningEnabled ? esc_html__('Enabled', 'wp-health') : esc_html__('Disabled', 'wp-health'); ?></span>
							</label>
							<p class="description"><?php echo esc_html($hardeningMeta[1]); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr>
						<th scope="row"><?php echo esc_html__('File editor constant', 'wp-health'); ?></th>
						<td>
							<?php $fileEditorDisabled = !empty($hardeningStates['file_editor_disabled']); ?>
							<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;<?php echo $fileEditorDisabled ? 'background:#edf7ed;color:#1e6b23;' : 'background:#f0f0f1;color:#646970;'; ?>"><?php echo $fileEditorDisabled ? esc_html__('DISALLOW_FILE_EDIT active', 'wp-health') : esc_html__('DISALLOW_FILE_EDIT not set', 'wp-health'); ?></span>
							<p class="description"><?php echo esc_html__('Read-only diagnostic: whether the DISALLOW_FILE_EDIT constant is currently active on this site.', 'wp-health'); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php wp_nonce_field(HardeningOptions::NONCE); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr(HardeningOptions::ACTION); ?>" />
			<?php submit_button(esc_html__('Save hardening options', 'wp-health')); ?>
		</form>
	</div>

	<!-- htaccess -->
	<?php
    $htaccessFile = wp_umbrella_get_service('HtaccessFile');
    $htaccessPath = $htaccessFile->getPath();
    $htaccessExists = $htaccessFile->exists();
    $htaccessContents = $htaccessFile->getContents();
    $htaccessHasBlock = $htaccessFile->hasUmbrellaBlock();
    $htaccessClean = HtaccessClean::getLastResult();
    ?>
	<div class="wpu-support-section" id="wpu-htaccess">
		<h2><?php echo esc_html__('.htaccess', 'wp-health'); ?></h2>
		<p class="description" style="margin-top:0;"><?php echo esc_html__('Read-only view of this site\'s .htaccess. You can remove the WP Umbrella block when one is present; the rest of the file is never touched.', 'wp-health'); ?></p>
		<?php if (is_array($htaccessClean)) : ?>
			<?php
            $cleanStatus = isset($htaccessClean['status']) ? $htaccessClean['status'] : 'error';
            $cleanReason = isset($htaccessClean['reason']) ? $htaccessClean['reason'] : '';

            if ($cleanStatus === 'ok') {
                $cleanMessage = __('The WP Umbrella block was removed from .htaccess.', 'wp-health');
            } elseif ($cleanReason === 'no_file') {
                $cleanMessage = __('No .htaccess file to clean.', 'wp-health');
            } elseif ($cleanReason === 'no_block') {
                $cleanMessage = __('No WP Umbrella block was present.', 'wp-health');
            } elseif ($cleanReason === 'not_writable') {
                $cleanMessage = __('The .htaccess file is not writable. Remove the block manually or fix the file permissions.', 'wp-health');
            } else {
                $cleanMessage = __('Could not update the .htaccess file.', 'wp-health');
            }

            $cleanBg = $cleanStatus === 'ok' ? '#edf7ed' : ($cleanStatus === 'noop' ? '#f0f0f1' : '#fdecea');
            $cleanBorder = $cleanStatus === 'ok' ? '#46b450' : ($cleanStatus === 'noop' ? '#c3c4c7' : '#dc3232');
            ?>
			<div style="margin: 0 0 12px; padding: 10px 12px; background: <?php echo esc_attr($cleanBg); ?>; border-left: 4px solid <?php echo esc_attr($cleanBorder); ?>;">
				<p style="margin: 0;"><?php echo esc_html($cleanMessage); ?></p>
			</div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__('File', 'wp-health'); ?></th>
					<td>
						<code><?php echo esc_html($htaccessPath); ?></code>
						<span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;<?php echo $htaccessExists ? 'background:#edf7ed;color:#1e6b23;' : 'background:#f0f0f1;color:#646970;'; ?>"><?php echo $htaccessExists ? esc_html__('Present', 'wp-health') : esc_html__('Missing', 'wp-health'); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__('WP Umbrella block', 'wp-health'); ?></th>
					<td>
						<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;<?php echo $htaccessHasBlock ? 'background:#edf7ed;color:#1e6b23;' : 'background:#f0f0f1;color:#646970;'; ?>"><?php echo $htaccessHasBlock ? esc_html__('Present', 'wp-health') : esc_html__('Not present', 'wp-health'); ?></span>
					</td>
				</tr>
			</tbody>
		</table>
		<?php if ($htaccessExists) : ?>
			<textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px;" onclick="this.select();"><?php echo esc_textarea($htaccessContents); ?></textarea>
		<?php else : ?>
			<p class="description"><?php echo esc_html__('No .htaccess file found. This is expected on Nginx.', 'wp-health'); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate" style="margin-top:12px;">
			<?php wp_nonce_field(HtaccessClean::NONCE); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr(HtaccessClean::ACTION); ?>" />
			<?php submit_button(esc_html__('Clean WP Umbrella block', 'wp-health'), 'delete', 'submit', false, $htaccessHasBlock ? null : ['disabled' => 'disabled']); ?>
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
			<?php if (wp_umbrella_get_key_state() === 'new') : ?>
				<div class="wpu-support-action-info">
					<strong><?php echo esc_html__('Secret Token', 'wp-health'); ?></strong>
					<p class="description"><?php echo esc_html__('This site uses signed requests (signature-only), so the secret token no longer controls the connection and regenerating it has no effect. To rotate credentials, regenerate the signing key from your WP Umbrella account.', 'wp-health'); ?></p>
				</div>
				<div class="wpu-support-action-btn">
					<?php submit_button(esc_html__('Regenerate', 'wp-health'), 'secondary', 'submit', false, ['disabled' => 'disabled']); ?>
				</div>
			<?php else : ?>
				<div class="wpu-support-action-info">
					<strong><?php echo esc_html__('Regenerate connection credentials', 'wp-health'); ?></strong>
					<p class="description"><?php echo esc_html__('Rotates all of this site\'s security credentials: a brand-new secret token AND a brand-new signing key are generated, then the site is reconnected to WP Umbrella. Use this if you suspect the credentials were leaked. Your data and settings are untouched.', 'wp-health'); ?></p>
				</div>
				<div class="wpu-support-action-btn">
					<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
						<?php wp_nonce_field('wp_umbrella_regenerate_secret_token'); ?>
						<input type="hidden" name="action" value="wp_umbrella_regenerate_secret_token" />
						<?php submit_button(esc_html__('Regenerate', 'wp-health'), 'secondary', 'submit', false); ?>
					</form>
				</div>
			<?php endif; ?>
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
		<div class="wpu-support-action">
			<div class="wpu-support-action-info">
				<strong><?php echo esc_html__('Clear WP Umbrella cache', 'wp-health'); ?></strong>
				<p class="description">
					<?php echo esc_html__('Clear cached data stored by WP Umbrella (white label settings, etc) and refresh WordPress\' cached PHP version check. Useful when your white label customisations are not reflected, or when the PHP version recommendation looks out of date.', 'wp-health'); ?>
				</p>
			</div>
			<div class="wpu-support-action-btn">
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" novalidate="novalidate">
					<?php wp_nonce_field('wp_umbrella_clean_transients'); ?>
					<input type="hidden" name="action" value="wp_umbrella_clean_transients" />
					<?php submit_button(esc_html__('Clear cache', 'wp-health'), 'secondary', 'submit', false); ?>
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
