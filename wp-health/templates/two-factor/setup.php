<?php

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($wpUmbrellaTwoFactor) && is_array($wpUmbrellaTwoFactor) ? $wpUmbrellaTwoFactor : [];
$secretGroups = implode(' ', str_split($context['secret'], 4));

?>
<form name="wpu_two_factor_setup" id="wpu-two-factor-setup" method="post" action="<?php echo esc_url(site_url('wp-login.php?action=' . $context['action'], 'login_post')); ?>">
	<?php if (!empty($context['error'])) : ?>
		<div id="login_error"><?php echo esc_html($context['error']); ?></div>
	<?php endif; ?>

	<p><?php echo esc_html__('This site requires two-factor authentication for administrators. Set up an authenticator app to continue.', 'wp-health'); ?></p>

	<div id="wpu-2fa-qr" data-wpu-qr-uri="<?php echo esc_attr($context['provisioningUri']); ?>" data-wpu-qr-label="<?php echo esc_attr__('QR code for two-factor setup', 'wp-health'); ?>" style="text-align:center;margin:16px 0;" hidden>
		<p style="margin:0 0 8px;"><?php echo esc_html__('Scan this code with your authenticator app.', 'wp-health'); ?></p>
		<div id="wpu-2fa-qr-canvas"></div>
	</div>

	<p>
		<label><?php echo esc_html__('Setup key', 'wp-health'); ?></label>
		<code style="display:block;padding:10px;margin:6px 0;background:#f6f7f7;word-break:break-all;font-size:14px;letter-spacing:1px;"><?php echo esc_html($secretGroups); ?></code>
		<span class="description"><?php echo esc_html__('Add this key to Google Authenticator, 1Password, Bitwarden or any other authenticator app.', 'wp-health'); ?></span>
	</p>

	<p>
		<label for="wpu_2fa_code"><?php echo esc_html__('Six-digit code from the app', 'wp-health'); ?></label>
		<input type="text" name="wpu_2fa_code" id="wpu_2fa_code" class="input" value="" size="20" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9 ]*" autofocus="autofocus" />
	</p>

	<input type="hidden" name="wpu_2fa_token" value="<?php echo esc_attr($context['token']); ?>" />
	<input type="hidden" name="wpu_2fa_step" value="verify" />
	<input type="hidden" name="wpu_2fa_nonce" value="<?php echo esc_attr($context['nonce']); ?>" />

	<p class="submit">
		<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php echo esc_attr__('Confirm and continue', 'wp-health'); ?>" />
	</p>
</form>
