<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="error settings-error notice is-dismissible">
	<p>
		<?php
            // translators: 1 HTML Tag, 2 HTML Tag
            echo sprintf(esc_html__('WP Umbrella is installed but not configured yet. %s Go to the settings.%s It only takes 1 minute! ', 'wp-health'), '<a href="' . admin_url('admin.php?page=wp-umbrella-settings') . '"">', '</a>');
        ?>
	</p>
</div>
