<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<?php foreach ($forms as $form) : ?>
	<form id="<?php echo esc_attr($form['id']); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field($form['action']); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr($form['action']); ?>" />
	</form>
<?php endforeach; ?>
