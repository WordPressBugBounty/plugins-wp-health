<?php

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['support']) && $_GET['support'] === '1') {
    require_once __DIR__ . '/_support.php';
    return;
}

if (isset($_GET['logs']) && $_GET['logs'] === '1') {
    require_once __DIR__ . '/_logs.php';
    return;
}

$data = wp_umbrella_get_service('GetSettingsData')->getData();

?>

<div id="wrap-wphealth">
	<div class="wrap">
		<h1 class="screen-reader-text"><?php echo WP_UMBRELLA_NAME . ' – ' . __('Settings'); ?>
		</h1>
		<div class="module-wp-health">
			<?php

                if (empty($data['api_key']) && empty($data['secret_token'])) {
                    include_once __DIR__ . '/../components/no-configuration.php';
                } else {
                    include_once __DIR__ . '/../components/connected.php';
                }
?>
		</div>
	</div>
</div>


