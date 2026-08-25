<?php

use WPUmbrella\Actions\TwoFactor\AdminReset;

if (!defined('ABSPATH')) {
    exit;
}

?>
<form id="<?php echo esc_attr($formId); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
	<?php wp_nonce_field($nonceAction); ?>
	<input type="hidden" name="action" value="<?php echo esc_attr(AdminReset::ACTION); ?>" />
	<input type="hidden" name="user_id" value="<?php echo esc_attr($targetUserId); ?>" />
</form>
