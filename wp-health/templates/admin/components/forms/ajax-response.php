<?php
$wpUmbrellaAjaxResponseI18n = array(
	'php_fatal' => esc_html__('Your site answered with a PHP fatal error instead of a valid response. Fix or disable the plugin or theme that causes it, then try again.', 'wp-health'),
	'blocked' => esc_html__('The request was blocked by a firewall or a security plugin on your site. Allow requests to wp-admin/admin-ajax.php from your WordPress admin, then try again.', 'wp-health'),
	/* translators: %s is the name of the firewall or security plugin, for example Wordfence. */
	'blocked_by' => esc_html__('The request was blocked by %s on your site. Allow requests to wp-admin/admin-ajax.php from your WordPress admin, then try again.', 'wp-health'),
	'session_expired' => esc_html__('Your session has expired. Reload the page and try again.', 'wp-health'),
	'server_error' => esc_html__('Your server took too long to answer or failed to process the request. Please try again in a moment.', 'wp-health'),
	'network' => esc_html__('The request could not reach your site. Check your internet connection and try again.', 'wp-health'),
	'unexpected' => esc_html__('Your site returned an unexpected response. Please send the excerpt below to WP Umbrella support at support@wp-umbrella.com.', 'wp-health'),
);
?>
<script type="text/javascript">
	var wpUmbrellaAjaxResponseI18n = <?php echo wp_json_encode($wpUmbrellaAjaxResponseI18n); ?>;

	function wpUmbrellaEscapeHtml(value) {
		return String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
	}

	function wpUmbrellaParseJsonObject(text) {
		try {
			const parsed = JSON.parse(text);
			return parsed && typeof parsed === 'object' && typeof parsed.success === 'boolean' ? parsed : null;
		} catch (error) {
			return null;
		}
	}

	function wpUmbrellaParseAjaxJson(text) {
		const direct = wpUmbrellaParseJsonObject(text);
		if (direct) {
			return direct;
		}

		const end = text.lastIndexOf('}');
		let start = text.indexOf('{');
		while (start !== -1 && start < end) {
			const parsed = wpUmbrellaParseJsonObject(text.slice(start, end + 1));
			if (parsed) {
				return parsed;
			}
			start = text.indexOf('{', start + 1);
		}

		return null;
	}

	function wpUmbrellaTextFromHtml(html) {
		const doc = new DOMParser().parseFromString(html, 'text/html');
		return (doc.body?.textContent || '').replace(/\s+/g, ' ').trim();
	}

	function wpUmbrellaExtractFatal(text) {
		const match = text.match(/Fatal error\s*:\s*([\s\S]*?)\s+in\s+(\S+\.php)(?:\s+on\s+line\s+|:)(\d+)/i);
		if (!match) {
			return {};
		}

		return { message: match[1], file: match[2], line: match[3] };
	}

	function wpUmbrellaDetectBlocker(text) {
		const blockers = [
			[/mod_?security/i, 'ModSecurity'],
			[/imunify/i, 'Imunify360'],
			[/wordfence/i, 'Wordfence'],
			[/sucuri/i, 'Sucuri'],
			[/cf-ray|cloudflare/i, 'Cloudflare'],
		];
		const found = blockers.find(([pattern]) => pattern.test(text));

		return found ? found[1] : null;
	}

	function wpUmbrellaClassifyAjaxFailure(status, body) {
		const raw = body.trim();
		const text = wpUmbrellaTextFromHtml(raw);

		if (/Fatal error|critical error on (this|your) website/i.test(text)) {
			return { type: 'php_fatal', critical_error: wpUmbrellaExtractFatal(text) };
		}

		if (raw === '-1' || raw === '0') {
			return { type: 'session_expired' };
		}

		const blocker = wpUmbrellaDetectBlocker(raw);
		const isError = status < 200 || status >= 300;
		const isServerError = status >= 500;
		if (status === 403 || status === 406 || /not acceptable/i.test(text) || (isError && blocker && !(blocker === 'Cloudflare' && isServerError))) {
			return { type: 'blocked', blocker };
		}

		if (isServerError) {
			return { type: 'server_error' };
		}

		return { type: 'unexpected', excerpt: text.slice(0, 200) };
	}

	async function wpUmbrellaPostAjax(url, body) {
		let response;
		try {
			response = await fetch(url, {
				method: 'POST',
				body: body
			});
		} catch (error) {
			console.error(error);
			const isTimeout = error?.name === 'AbortError' || error?.name === 'TimeoutError';
			return { failure: { type: isTimeout ? 'server_error' : 'network' } };
		}

		let text = '';
		try {
			text = await response.text();
		} catch (error) {
			console.error(error);
			return { failure: { type: 'network' } };
		}

		const json = wpUmbrellaParseAjaxJson(text);
		if (json) {
			return { json };
		}

		return { failure: wpUmbrellaClassifyAjaxFailure(response.status, text) };
	}

	function wpUmbrellaAjaxFailureHtml(failure) {
		const codeStyle = 'display:block; margin-top:8px; font-family:monospace; font-size:12px; background:#f3f4f6; padding:6px; border-radius:4px; word-break:break-word; text-align:left;';

		switch (failure.type) {
			case 'php_fatal': {
				const detail = failure.critical_error?.message;
				return wpUmbrellaAjaxResponseI18n.php_fatal + (detail ? `<code style="${codeStyle}">${wpUmbrellaEscapeHtml(detail)}</code>` : '');
			}
			case 'blocked':
				return failure.blocker ? wpUmbrellaAjaxResponseI18n.blocked_by.replace('%s', wpUmbrellaEscapeHtml(failure.blocker)) : wpUmbrellaAjaxResponseI18n.blocked;
			case 'session_expired':
				return wpUmbrellaAjaxResponseI18n.session_expired;
			case 'server_error':
				return wpUmbrellaAjaxResponseI18n.server_error;
			case 'network':
				return wpUmbrellaAjaxResponseI18n.network;
			default:
				return wpUmbrellaAjaxResponseI18n.unexpected + (failure.excerpt ? `<code style="${codeStyle}">${wpUmbrellaEscapeHtml(failure.excerpt)}</code>` : '');
		}
	}
</script>
