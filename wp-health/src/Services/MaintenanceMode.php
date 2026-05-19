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
		$secretTokenHash = wp_umbrella_get_secret_token();

		if (!$secretTokenHash || !wp_umbrella_is_new_hash()) {
			return '<?php' . "\n" . '$upgrading = ' . time() . ';' . "\n";
		}

		return '<?php' . "\n"
			. '$upgrading = ' . time() . ';' . "\n"
			. '$auth = isset($_SERVER[\'HTTP_AUTHORIZATION\']) ? $_SERVER[\'HTTP_AUTHORIZATION\'] : \'\';' . "\n"
			. 'if (stripos($auth, \'Bearer \') === 0 && function_exists(\'hash_equals\') && function_exists(\'hash\')) {' . "\n"
			. '    $bearer = substr($auth, 7);' . "\n"
			. '    if (hash_equals(\'' . addslashes($secretTokenHash) . '\', hash(\'sha256\', $bearer))) {' . "\n"
			. '        $upgrading = 0;' . "\n"
			. '    }' . "\n"
			. '}' . "\n";
	}

}
