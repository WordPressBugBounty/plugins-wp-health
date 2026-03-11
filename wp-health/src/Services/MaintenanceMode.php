<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}


class MaintenanceMode
{
	public function toggleMaintenanceMode($enable = false)
	{
		global $wp_filesystem;

		if ($wp_filesystem === null) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$file = $wp_filesystem->abspath() . '.maintenance';
		if ($enable) {
			$content = $this->generateSmartMaintenanceContent();
			$wp_filesystem->delete($file);
			$wp_filesystem->put_contents($file, $content, FS_CHMOD_FILE);
		} else {
			$wp_filesystem->delete($file);
		}
	}

	private function generateSmartMaintenanceContent()
	{
		$apiKey = wp_umbrella_get_api_key();

		return '<?php' . "\n"
			. '$upgrading = ' . time() . ';' . "\n"
			. '// Allow WP Umbrella requests through during maintenance' . "\n"
			. 'if (isset($_SERVER[\'HTTP_X_UMBRELLA\']) && function_exists(\'hash_equals\')) {' . "\n"
			. '    if (hash_equals(\'' . addslashes($apiKey) . '\', $_SERVER[\'HTTP_X_UMBRELLA\'])) {' . "\n"
			. '        $upgrading = 0;' . "\n"
			. '    }' . "\n"
			. '}' . "\n";
	}

}
