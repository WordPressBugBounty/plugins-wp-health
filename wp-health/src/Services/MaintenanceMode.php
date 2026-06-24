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
		$bearer = \WPUmbrella\Core\UmbrellaRequest::createFromGlobals()->getAuthorizationBearer();

		wp_umbrella_debug_log('MaintenanceMode: bypass=' . ($bearer ? 'enabled' : 'DISABLED')
			. ' (incoming_bearer=' . ($bearer ? 'present' : 'absent') . ')');

		if (!$bearer || !function_exists('hash')) {
			return '<?php' . "\n" . '$upgrading = ' . time() . ';' . "\n";
		}

		$expected = hash('sha256', $bearer);

		return '<?php' . "\n"
			. '$upgrading = ' . time() . ';' . "\n"
			. '$wpum_cands = array();' . "\n"
			. 'foreach (array(\'HTTP_X_SECRET_TOKEN\', \'HTTP_X_AUTHORIZATION\', \'HTTP_AUTHORIZATION\', \'REDIRECT_HTTP_AUTHORIZATION\') as $wpum_k) {' . "\n"
			. '    if (!empty($_SERVER[$wpum_k])) { $wpum_cands[] = $_SERVER[$wpum_k]; }' . "\n"
			. '}' . "\n"
			. 'if (function_exists(\'getallheaders\')) {' . "\n"
			. '    foreach ((array) getallheaders() as $wpum_hk => $wpum_hv) {' . "\n"
			. '        $wpum_lk = strtolower($wpum_hk);' . "\n"
			. '        if ($wpum_lk === \'x-authorization\' || $wpum_lk === \'authorization\' || $wpum_lk === \'x-secret-token\') { $wpum_cands[] = $wpum_hv; }' . "\n"
			. '    }' . "\n"
			. '}' . "\n"
			. 'if (function_exists(\'hash_equals\') && function_exists(\'hash\')) {' . "\n"
			. '    foreach ($wpum_cands as $wpum_c) {' . "\n"
			. '        if (stripos($wpum_c, \'Bearer \') === 0 && hash_equals(\'' . $expected . '\', hash(\'sha256\', substr($wpum_c, 7)))) { $upgrading = 0; break; }' . "\n"
			. '    }' . "\n"
			. '}' . "\n";
	}

}
