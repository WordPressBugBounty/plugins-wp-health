<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="error settings-error notice is-dismissible">
	<p>
		<?php
            /* translators: 1 is a plugin name, 2 is WP Umbrella version, 3 is current php version. */
            echo sprintf(esc_html__('%1$s  requires PHP %2$s minimum, your website is actually running version %3$s.', 'wp-health'), '<strong>WP Umbrella</strong>', '<code>' . WP_UMBRELLA_PHP_MIN . '</code>', '<code>' . esc_attr(phpversion()) . '</code>');
        ?>
	</p>
</div>
