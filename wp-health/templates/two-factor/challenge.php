<?php

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($wpUmbrellaTwoFactor) && is_array($wpUmbrellaTwoFactor) ? $wpUmbrellaTwoFactor : [];

?>
<form name="wpu_two_factor_challenge" id="wpu-two-factor-challenge" method="post" action="<?php echo esc_url(site_url('wp-login.php?action=' . $context['action'], 'login_post')); ?>">
	<?php if (!empty($context['error'])) : ?>
		<div id="login_error"><?php echo esc_html($context['error']); ?></div>
	<?php endif; ?>

	<p>
		<label for="wpu_2fa_code"><?php echo esc_html__('Six-digit code from your authenticator app', 'wp-health'); ?></label>
		<input type="text" name="wpu_2fa_code" id="wpu_2fa_code" class="input" value="" size="20" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9 ]*" autofocus="autofocus" />
	</p>

	<p>
		<label for="wpu_2fa_recovery_code"><?php echo esc_html__('Lost your phone? Use a recovery code instead', 'wp-health'); ?></label>
		<input type="text" name="wpu_2fa_recovery_code" id="wpu_2fa_recovery_code" class="input" value="" size="20" autocomplete="off" />
	</p>

	<input type="hidden" name="wpu_2fa_token" value="<?php echo esc_attr($context['token']); ?>" />
	<input type="hidden" name="wpu_2fa_nonce" value="<?php echo esc_attr($context['nonce']); ?>" />

	<p class="submit">
		<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php echo esc_attr__('Log in', 'wp-health'); ?>" />
	</p>
</form>
