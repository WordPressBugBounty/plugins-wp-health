<?php

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($wpUmbrellaTwoFactor) && is_array($wpUmbrellaTwoFactor) ? $wpUmbrellaTwoFactor : [];

?>
<form name="wpu_two_factor_codes" id="wpu-two-factor-codes" method="post" action="<?php echo esc_url(site_url('wp-login.php?action=' . $context['action'], 'login_post')); ?>">
	<p><?php echo esc_html__('Two-factor authentication is on. Save these recovery codes now: they are shown once, and each one works a single time if you lose your phone.', 'wp-health'); ?></p>

	<ul style="margin:12px 0;padding:12px;background:#f6f7f7;list-style:none;font-family:monospace;font-size:14px;line-height:1.8;">
		<?php foreach ($context['codes'] as $code) : ?>
			<li><?php echo esc_html($code); ?></li>
		<?php endforeach; ?>
	</ul>

	<p>
		<label for="wpu_2fa_saved">
			<input type="checkbox" name="wpu_2fa_saved" id="wpu_2fa_saved" value="1" required="required" />
			<?php echo esc_html__('I saved these codes somewhere safe', 'wp-health'); ?>
		</label>
	</p>

	<input type="hidden" name="wpu_2fa_token" value="<?php echo esc_attr($context['token']); ?>" />
	<input type="hidden" name="wpu_2fa_step" value="confirm" />
	<input type="hidden" name="wpu_2fa_nonce" value="<?php echo esc_attr($context['nonce']); ?>" />

	<p class="submit">
		<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php echo esc_attr__('Continue to the dashboard', 'wp-health'); ?>" />
	</p>
</form>
