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

		if($wp_filesystem === null){
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$message = '<?php $upgrading = '.time().'; ?>';

		$file = $wp_filesystem->abspath().'.maintenance';
		if ($enable) {
			$wp_filesystem->delete($file);
			$wp_filesystem->put_contents($file, $message, FS_CHMOD_FILE);
		} else {
			$wp_filesystem->delete($file);
		}
	}

}
