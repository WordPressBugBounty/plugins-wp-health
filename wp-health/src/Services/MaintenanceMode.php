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
		$publicKey = wp_umbrella_get_public_key();

		$hasBearer = $bearer && function_exists('hash');
		$hasSignature = is_string($publicKey) && $publicKey !== '';

		wp_umbrella_debug_log('MaintenanceMode: bypass bearer=' . ($hasBearer ? 'on' : 'off')
			. ' signature=' . ($hasSignature ? 'on' : 'off')
			. ' key_state=' . wp_umbrella_get_key_state());

		$content = '<?php' . "\n" . '$upgrading = ' . time() . ';' . "\n";

		if ($hasBearer) {
			$content .= $this->bearerBypassSnippet(hash('sha256', $bearer));
		}

		if ($hasSignature) {
			$content .= $this->signatureBypassSnippet($publicKey);
		}

		return $content;
	}

	private function bearerBypassSnippet($expected)
	{
		return '$wpum_cands = array();' . "\n"
			. 'foreach (array(\'HTTP_X_SECRET_TOKEN\', \'HTTP_X_AUTHORIZATION\', \'HTTP_AUTHORIZATION\', \'REDIRECT_HTTP_AUTHORIZATION\') as $wpum_k) {' . "\n"
			. '    if (!empty($_SERVER[$wpum_k])) { $wpum_cands[] = $_SERVER[$wpum_k]; }' . "\n"
			. '}' . "\n"
			. 'if (function_exists(\'getallheaders\')) {' . "\n"
			. '    foreach ((array) getallheaders() as $wpum_hk => $wpum_hv) {' . "\n"
			. '        $wpum_lk = strtolower($wpum_hk);' . "\n"
			. '        if ($wpum_lk === \'x-authorization\' || $wpum_lk === \'authorization\' || $wpum_lk === \'x-secret-token\') { $wpum_cands[] = $wpum_hv; }' . "\n"
			. '    }' . "\n"
			. '}' . "\n"
			. 'if ($upgrading && function_exists(\'hash_equals\') && function_exists(\'hash\')) {' . "\n"
			. '    foreach ($wpum_cands as $wpum_c) {' . "\n"
			. '        if (stripos($wpum_c, \'Bearer \') === 0 && hash_equals(\'' . $expected . '\', hash(\'sha256\', substr($wpum_c, 7)))) { $upgrading = 0; break; }' . "\n"
			. '    }' . "\n"
			. '}' . "\n";
	}

	/**
	 * Signature bypass for NEW (signature-only) sites, where no Bearer travels
	 * with the request. Self-contained because .maintenance is executed by WP
	 * core before the plugin loads. Lets a request carrying a fresh Ed25519
	 * signature from our stored public key skip the maintenance splash so the
	 * update flow (and its disable-maintenance call) is never locked out.
	 */
	private function signatureBypassSnippet($publicKey)
	{
		$freshness = \WPUmbrella\Core\Constants\SignedRequest::FRESHNESS_WINDOW_SECONDS;

		return 'if ($upgrading && function_exists(\'sodium_crypto_sign_verify_detached\')) {' . "\n"
			. '    $wpum_h = array();' . "\n"
			. '    foreach ($_SERVER as $wpum_sk => $wpum_sv) {' . "\n"
			. '        if (strpos($wpum_sk, \'HTTP_\') === 0) { $wpum_h[strtolower(str_replace(\'_\', \'-\', substr($wpum_sk, 5)))] = $wpum_sv; }' . "\n"
			. '    }' . "\n"
			. '    if (function_exists(\'getallheaders\')) {' . "\n"
			. '        foreach ((array) getallheaders() as $wpum_hk => $wpum_hv) { $wpum_h[strtolower($wpum_hk)] = $wpum_hv; }' . "\n"
			. '    }' . "\n"
			. '    $wpum_sig = isset($wpum_h[\'x-umbrella-signature\']) ? $wpum_h[\'x-umbrella-signature\'] : \'\';' . "\n"
			. '    $wpum_ts = isset($wpum_h[\'x-umbrella-timestamp\']) ? $wpum_h[\'x-umbrella-timestamp\'] : \'\';' . "\n"
			. '    $wpum_nonce = isset($wpum_h[\'x-umbrella-nonce\']) ? $wpum_h[\'x-umbrella-nonce\'] : \'\';' . "\n"
			. '    if ($wpum_sig !== \'\' && ctype_digit((string) $wpum_ts) && $wpum_nonce !== \'\' && abs(time() - (int) $wpum_ts) <= ' . (int) $freshness . ') {' . "\n"
			. '        $wpum_pub = \'' . addslashes($publicKey) . '\';' . "\n"
			. '        $wpum_pub_raw = (strlen($wpum_pub) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES * 2 && ctype_xdigit($wpum_pub)) ? @hex2bin($wpum_pub) : base64_decode($wpum_pub, true);' . "\n"
			. '        $wpum_sig_raw = base64_decode($wpum_sig, true);' . "\n"
			. '        if ($wpum_pub_raw !== false && $wpum_sig_raw !== false && strlen($wpum_pub_raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && strlen($wpum_sig_raw) === SODIUM_CRYPTO_SIGN_BYTES) {' . "\n"
			. '            $wpum_body = file_get_contents(\'php://input\');' . "\n"
			. '            if ($wpum_body === false) { $wpum_body = \'\'; }' . "\n"
			. '            $wpum_method = isset($_SERVER[\'REQUEST_METHOD\']) ? strtoupper($_SERVER[\'REQUEST_METHOD\']) : \'GET\';' . "\n"
			. '            $wpum_path = isset($_SERVER[\'REQUEST_URI\']) ? parse_url($_SERVER[\'REQUEST_URI\'], PHP_URL_PATH) : \'\';' . "\n"
			. '            $wpum_canon = $wpum_method . "\n" . $wpum_path . "\n" . hash(\'sha256\', $wpum_body) . "\n" . $wpum_ts . "\n" . $wpum_nonce;' . "\n"
			. '            if (sodium_crypto_sign_verify_detached($wpum_sig_raw, $wpum_canon, $wpum_pub_raw)) { $upgrading = 0; }' . "\n"
			. '        }' . "\n"
			. '    }' . "\n"
			. '}' . "\n";
	}

}
